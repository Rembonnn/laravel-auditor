<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Rembon\LaravelAuditor\Contracts\UserResolver as UserResolverContract;
use Rembon\LaravelAuditor\Http\Middleware\RecordRequest;
use Rembon\LaravelAuditor\Listeners\CommandLifecycle;
use Rembon\LaravelAuditor\Listeners\JobLifecycle;
use Rembon\LaravelAuditor\Listeners\RecordAbilityCheck;
use Rembon\LaravelAuditor\Listeners\RecordMail;
use Rembon\LaravelAuditor\Listeners\RecordNotification;
use Rembon\LaravelAuditor\Observers\AuditableObserver;
use Rembon\LaravelAuditor\Storage\StorageManager;
use Rembon\LaravelAuditor\Support\CorrelationId;
use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\UserResolver;

final class AuditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auditor.php', 'auditor');

        $this->app->singleton(Auditor::class);
        $this->app->scoped(Recorder::class);
        $this->app->singleton(StorageManager::class);
        $this->app->singleton(Redactor::class);
        $this->app->singleton(UserResolverContract::class, UserResolver::class);
        $this->app->singleton(AuditableObserver::class);
        $this->app->singleton(CommandLifecycle::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerResources();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerListeners();
        $this->registerContextPropagation();
        $this->registerRoutes();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/auditor.php' => config_path('auditor.php')], 'auditor-config');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'auditor-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/auditor')], 'auditor-views');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/auditor')], 'auditor-lang');
    }

    private function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'auditor');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'auditor');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'auditor');
        View::composer(['auditor::layout', 'auditor::overview', 'auditor::entries.index'], Http\ViewComposers\LayoutComposer::class);
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\InstallCommand::class,
                Console\PruneCommand::class,
                Console\SealCommand::class,
                Console\VerifyCommand::class,
                Console\ImportV2Command::class,
            ]);
        }
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('auditor.record', RecordRequest::class);

        if (! config('auditor.http.enabled', true)) {
            return;
        }

        // Through the HTTP kernel when possible: it owns the groups and
        // would overwrite a change made on the router alone.
        $kernel = $this->app->make(HttpKernel::class);

        foreach (Settings::strings('auditor.http.middleware_groups') as $group) {
            if ($kernel instanceof FoundationHttpKernel) {
                try {
                    $kernel->appendMiddlewareToGroup((string) $group, RecordRequest::class);
                } catch (InvalidArgumentException) {
                    // Group not defined in this application.
                }
            } else {
                $router->pushMiddlewareToGroup((string) $group, RecordRequest::class);
            }
        }
    }

    private function registerListeners(): void
    {
        // Always registered; each listener checks its config switch at runtime.
        $events = $this->app->make(Dispatcher::class);

        $events->listen(GateEvaluated::class, [RecordAbilityCheck::class, 'handle']);
        $events->listen(MessageSent::class, [RecordMail::class, 'handle']);
        $events->listen(NotificationSent::class, [RecordNotification::class, 'handle']);

        $events->listen(JobProcessing::class, [JobLifecycle::class, 'processing']);
        $events->listen(JobProcessed::class, [JobLifecycle::class, 'processed']);
        $events->listen(JobFailed::class, [JobLifecycle::class, 'failed']);
        $events->listen(JobExceptionOccurred::class, [JobLifecycle::class, 'exceptionOccurred']);

        $events->listen(CommandStarting::class, [CommandLifecycle::class, 'starting']);
        $events->listen(CommandFinished::class, [CommandLifecycle::class, 'finished']);
    }

    /**
     * Every queued job carries the correlation id and the user who
     * dispatched it, without any change to the job class.
     */
    private function registerContextPropagation(): void
    {
        Context::dehydrating(function (ContextRepository $context): void {
            $auditor = $this->app->make(Auditor::class);

            $auditor->guard(function () use ($context, $auditor): void {
                $recorder = $auditor->recorder();

                if (! $recorder->enabled()) {
                    return;
                }

                $context->addHidden(CorrelationId::CONTEXT_KEY, $recorder->correlationId());

                if ($causer = $recorder->causer()) {
                    $context->addHidden(CorrelationId::CAUSER_CONTEXT_KEY, $causer);
                }
            });
        });
    }

    private function registerRoutes(): void
    {
        if (! config('auditor.dashboard.enabled', true) || $this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('auditor.dashboard.domain'),
            'prefix' => trim(Settings::string('auditor.dashboard.path', 'auditor'), '/'),
            'middleware' => array_merge((array) config('auditor.dashboard.middleware', ['web']), [
                Http\Middleware\Authorize::class,
                Http\Middleware\EnsureStorageIsReady::class,
            ]),
            'as' => 'auditor.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }
}
