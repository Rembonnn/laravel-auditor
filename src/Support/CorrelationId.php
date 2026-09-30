<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The correlation id ties a request to the jobs it dispatched. It lives in
 * Laravel's hidden Context, which the framework carries into queued jobs.
 */
final class CorrelationId
{
    public const string CONTEXT_KEY = 'auditor.correlation_id';

    public const string CAUSER_CONTEXT_KEY = 'auditor.causer';

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $id) === 1;
    }

    public static function generate(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Use the incoming header only when explicitly trusted and well formed.
     */
    public static function fromRequest(Request $request): string
    {
        $header = Settings::nullableString('auditor.http.correlation_header');

        if ($header !== null && config('auditor.http.trust_incoming_correlation_id')) {
            $incoming = $request->headers->get($header);

            if (self::isValid($incoming)) {
                return (string) $incoming;
            }
        }

        return self::generate();
    }

    public static function current(): ?string
    {
        $id = Context::getHidden(self::CONTEXT_KEY);

        return is_string($id) && self::isValid($id) ? $id : null;
    }

    public static function set(string $id): void
    {
        Context::addHidden(self::CONTEXT_KEY, $id);
    }
}
