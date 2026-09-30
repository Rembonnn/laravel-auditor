<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Lottery;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\CorrelationId;
use Rembon\LaravelAuditor\Support\IpAnonymizer;
use Rembon\LaravelAuditor\Support\PendingEntry;
use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts an entry when the request comes in and completes it in terminate(),
 * which runs after the response has been sent (PHP-FPM).
 */
final readonly class RecordRequest
{
    public const string ATTRIBUTE = '_auditor_entry';

    public function __construct(
        private Auditor $auditor,
        private Redactor $redactor,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has(self::ATTRIBUTE) || ! $this->shouldRecord($request)) {
            return $next($request);
        }

        $entry = $this->auditor->guard(fn (): PendingEntry => $this->auditor->recorder()->start(
            type: EntryType::Http,
            attributes: ['http_method' => $request->getMethod(), 'hostname' => gethostname() ?: null],
            correlationId: CorrelationId::fromRequest($request),
        ));

        if ($entry === null) {
            return $next($request);
        }

        $request->attributes->set(self::ATTRIBUTE, $entry);

        $response = $next($request);

        if ($header = Settings::nullableString('auditor.http.correlation_header')) {
            $response->headers->set($header, $entry->correlationId);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $entry = $request->attributes->get(self::ATTRIBUTE);

        if (! $entry instanceof PendingEntry) {
            return;
        }

        $request->attributes->remove(self::ATTRIBUTE);

        $this->auditor->guard(function () use ($entry, $request, $response): void {
            $route = $request->route();
            $entry->name = is_object($route) ? $route->getName() : null;
            $status = $response->getStatusCode();

            $this->auditor->recorder()->finish($entry, [
                // Raw request URI: fullUrl() would reorder the query string.
                'url' => $this->redactor->redactUrl($request->getSchemeAndHttpHost().$request->getRequestUri()),
                'route_action' => is_object($route) ? ltrim($route->getActionName(), '\\') : null,
                'status_code' => $status,
                'failed' => $status >= 500,
                'ip' => $this->ip($request),
                'user_agent' => config('auditor.http.capture.user_agent', true) ? $request->userAgent() : null,
                'input' => config('auditor.http.capture.input', false) ? $this->input($request) : null,
                'sampled_out' => $this->sampledOut(),
            ]);
        });
    }

    private function shouldRecord(Request $request): bool
    {
        if (! config('auditor.enabled', true) || ! config('auditor.http.enabled', true)) {
            return false;
        }

        $exceptMethods = array_map(strtoupper(...), Settings::strings('auditor.http.except_methods'));

        if (in_array($request->getMethod(), $exceptMethods, true)) {
            return false;
        }

        // The dashboard never audits itself, wherever it is mounted.
        if ($request->route() !== null && $request->routeIs('auditor.*')) {
            return false;
        }

        return ! $request->is(...Settings::strings('auditor.http.except'));
    }

    private function ip(Request $request): ?string
    {
        if (! config('auditor.http.capture.ip', true)) {
            return null;
        }

        return config('auditor.http.capture.anonymize_ip', false)
            ? IpAnonymizer::anonymize($request->ip())
            : $request->ip();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function input(Request $request): ?array
    {
        $input = $request->all();

        array_walk_recursive($input, function (mixed &$value): void {
            if ($value instanceof UploadedFile) {
                $value = ['file' => $value->getClientOriginalName(), 'size' => $value->getSize()];
            }
        });

        return $input === [] ? null : Values::stringKeys($this->redactor->redact($input));
    }

    /**
     * Uses Laravel's Lottery, so tests can fix the outcome.
     */
    private function sampledOut(): bool
    {
        $rate = Settings::float('auditor.http.sample_rate', 1.0);

        return $rate < 1.0 && ! Lottery::odds(max(0.0, $rate))->choose();
    }
}
