<?php

namespace Rembon\LaravelAuditor\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Contracts\Auditor;

class AuditRequest
{
    /**
     * @return void
     */
    public function __construct(private Auditor $auditor) {}

    /**
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next)
    {
        if (Route::current()) {
            $this->auditor->onRoute(
                Route::currentRouteName() ?? Route::currentRouteAction()
            );
        }

        if ($request->user() instanceof User) {
            $this->auditor->addUser($request->user());
        }

        $response = $next($request);

        $this->auditor->onUrl($request->fullUrl());

        $this->auditor->finish();

        return $response;
    }
}
