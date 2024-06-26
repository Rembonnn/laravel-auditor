<?php

namespace Rembon\LaravelAuditor;

use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Rembon\LaravelAuditor\Contracts\Auditor;
use Rembon\LaravelAuditor\Services\AuditorService;
use Rembon\LaravelAuditor\Http\Middleware\AuditRequest;

class LaravelAuditorServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Auditor::class, AuditorService::class);

        $this->mergeConfigFrom(
            __DIR__.'/Config/laravel-auditor.php', 'laravel-auditor'
        );
    }

    /**
     * @return void
     */
    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('web', AuditRequest::class);

        $this->loadRoutesFrom(__DIR__. '/Routes/web.php');

        $this->loadViewsFrom(__DIR__. '/Views/', 'auditor');

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations/');

        $this->publishes([
            __DIR__.'/Database/Migrations/' => database_path('migrations')
        ], 'migrations');

        $this->publishes([
            __DIR__.'/Config/laravel-auditor.php' => config_path('laravel-auditor.php'),
        ], 'config');

        $this->publishes([
            __DIR__. '/Views' => resource_path('views/vendor/auditor'),
        ], 'views');

        Gate::define('auth:check', function (User $user) {
            return Auth::check();
        });
    }
}
