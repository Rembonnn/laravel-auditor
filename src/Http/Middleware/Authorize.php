<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Rembon\LaravelAuditor\Auditor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard access:
 *  1. Auditor::auth() callback, when registered, decides alone;
 *  2. otherwise the "viewAuditor" gate, when defined;
 *  3. otherwise only the "local" environment.
 */
final readonly class Authorize
{
    public function __construct(private Auditor $auditor) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->allowed($request)) {
            return response()->view('auditor::errors.403', [
                'showHint' => app()->isLocal(),
            ], 403);
        }

        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        if ($callback = $this->auditor->authCallback()) {
            return (bool) $callback($request);
        }

        if (Gate::has('viewAuditor')) {
            return Gate::forUser($request->user())->allows('viewAuditor');
        }

        return app()->isLocal();
    }
}
