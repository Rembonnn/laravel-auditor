<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Rembon\LaravelAuditor\Facades\Auditor;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'auth.providers.users.model' => User::class,
            'app.name' => 'Acme',
        ]);
    }

    public function boot(): void
    {
        // The workbench is a local playground: everyone may look.
        Auditor::auth(fn () => true);

        Gate::define('update-post', fn ($user) => $user !== null);
        Gate::define('delete-post', fn ($user) => (bool) $user?->is_admin);
        Gate::define('view-report', fn ($user) => (bool) $user?->is_admin);
    }
}
