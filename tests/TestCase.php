<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as Orchestra;
use Rembon\LaravelAuditor\AuditorServiceProvider;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\PostPolicy;
use Rembon\LaravelAuditor\Tests\Fixtures\TestRoutes;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AuditorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config): void {
            $config->set('database.default', env('DB_CONNECTION', 'testing'));
            $config->set('auditor.throw_exceptions', true);
            $config->set('auth.providers.users.model', Fixtures\User::class);
            $config->set('queue.default', 'sync');
            $config->set('mail.default', 'array');

            // Test infrastructure commands are not what we audit here.
            $config->set('auditor.console.except', [...$config->get('auditor.console.except'), 'migrate*', 'db:*']);
        });

        // Laravel only turns Symfony console events into CommandStarting /
        // CommandFinished outside unit tests; do it here like a real CLI run.
        $app->booted(fn ($app) => $app->make(ConsoleKernel::class)->rerouteSymfonyCommandEvents());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        TestRoutes::register($router);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Post::class, PostPolicy::class);
    }
}
