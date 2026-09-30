<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Support\Dashboard\Assets;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

// Teardown may run artisan commands, which would ask for confirmation in "production".
afterEach(fn () => app()->detectEnvironment(fn (): string => 'testing'));

it('S1: is closed outside local without a gate', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/auditor')->assertForbidden()->assertDontSee('viewAuditor');
});

it('is open in local without a gate', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $this->get('/auditor')->assertOk();
});

it('shows how to define the gate only in local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Gate::define('viewAuditor', fn (?User $user): false => false);

    $this->get('/auditor')->assertForbidden()->assertSee("Gate::define('viewAuditor'", false);
});

it('uses the viewAuditor gate when defined', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    Gate::define('viewAuditor', fn (User $user) => $user->is_admin);

    $this->get('/auditor')->assertForbidden();
    $this->actingAs(User::make())->get('/auditor')->assertForbidden();
    $this->actingAs(User::make(['is_admin' => true]))->get('/auditor')->assertOk();
});

it('lets Auditor::auth() override the gate and the environment', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Gate::define('viewAuditor', fn (): true => true);
    Auditor::auth(fn ($request): bool => $request->header('X-Admin') === 'yes');

    $this->get('/auditor')->assertForbidden();
    $this->get('/auditor', ['X-Admin' => 'yes'])->assertOk();
});

it('protects every dashboard route', function (string $uri): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get($uri)->assertForbidden();
})->with(['/auditor/entries', '/auditor/changes', '/auditor/integrity', '/auditor/search?q=ab', '/auditor/poll/entries', '/auditor/export/entries']);

it('serves assets without authorization', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $file = Assets::manifest()['resources/css/auditor.css']['file'];

    $this->get('/auditor/assets/'.$file)->assertOk();
});

it('accepts truthy values from the auth callback', function (): void {
    Auditor::auth(fn (): int => 1);
    $this->get('/auditor')->assertOk();

    Auditor::auth(fn (): int => 0);
    $this->get('/auditor')->assertForbidden();
});
