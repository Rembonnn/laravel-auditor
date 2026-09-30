<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A strict script CSP, as a security-conscious application would send.
 */
final class StrictCsp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "script-src 'self' 'nonce-test-nonce'; object-src 'none'; base-uri 'none'");

        return $response;
    }
}
