<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Settings;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The dashboard reads the database driver's tables. Explain what is missing
 * instead of failing with a 500.
 */
final class EnsureStorageIsReady
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('auditor.storage.driver', 'database') !== 'database') {
            return $this->unavailable('driver');
        }

        try {
            $schema = (new Entry)->getConnection()->getSchemaBuilder();
            $ready = $schema->hasTable((new Entry)->getTable()) && $schema->hasTable((new ModelChange)->getTable());
        } catch (Throwable) {
            $ready = false;
        }

        return $ready ? $next($request) : $this->unavailable('tables');
    }

    private function unavailable(string $reason): Response
    {
        return response()->view('auditor::errors.unavailable', [
            'reason' => $reason,
            'driver' => Settings::string('auditor.storage.driver'),
        ], 503);
    }
}
