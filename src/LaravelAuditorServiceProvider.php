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
use Rembon\LaravelAuditor\Http\Middleware\PerformanceMetrics;
use Illuminate\Contracts\Http\Kernel as HttpKernel;

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
     * @param Router $router
     * @param HttpKernel $kernel
     * @return void
     */
    public function boot(Router $router, HttpKernel $kernel): void
    {
        $this->loadRoutesFrom(__DIR__. '/Routes/web.php');

        $this->loadViewsFrom(__DIR__. '/Views/', 'auditor');

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations/');

        $this->publishes([

        ], 'migrations');

        $this->publishes([
            __DIR__.'/Config/laravel-auditor.php' => config_path('laravel-auditor.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/Database/Migrations/' => database_path('migrations'),
        ], 'migration');

        $this->publishes([
            __DIR__ . '/../dist/assets' => public_path('vendor/laravel-auditor'),
        ], 'public');

        $this->publishes([
            __DIR__. '/Views' => resource_path('views/vendor/auditor'),
        ], 'view');

        Gate::define('auth:check', function (User $user) {
            return Auth::check();
        });

        $router->pushMiddlewareToGroup('web', AuditRequest::class);

        if (config('laravel-auditor.performance_metrics')) $kernel->pushMiddleware(PerformanceMetrics::class);
    }
}
