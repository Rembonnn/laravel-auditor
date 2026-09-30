<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Rembon\LaravelAuditor\AuditorServiceProvider;

// Work in a private copy of the app paths so parallel test processes that
// share the Testbench skeleton never see the published files.
beforeEach(function (): void {
    $this->sandbox = sys_get_temp_dir().'/auditor-install-'.getmypid().'-'.uniqid();
    File::ensureDirectoryExists($this->sandbox.'/config');
    File::ensureDirectoryExists($this->sandbox.'/database/migrations');

    app()->useConfigPath($this->sandbox.'/config');
    app()->useDatabasePath($this->sandbox.'/database');
    app()->useEnvironmentPath($this->sandbox);

    // Publish targets are computed when the provider boots: compute them again.
    (fn () => $this->registerPublishing())->call(app()->getProvider(AuditorServiceProvider::class));

    $this->env = app()->environmentFilePath();
    File::put($this->env, "APP_NAME=Test\n");
    File::put($this->env.'.example', "APP_NAME=\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

it('publishes config and migrations and prints the next steps', function (): void {
    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain("Gate::define('viewAuditor'")
        ->expectsOutputToContain("Schedule::command('auditor:prune')")
        ->assertSuccessful();

    expect(File::exists(config_path('auditor.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_create_auditor_*.php')))->toHaveCount(3);
});

it('is idempotent', function (): void {
    $this->artisan('auditor:install', ['--no-interaction' => true])->assertSuccessful();
    $this->artisan('auditor:install', ['--no-interaction' => true])->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_auditor_*.php')))->toHaveCount(3);
});

it('generates an integrity key once', function (): void {
    $this->artisan('auditor:install', ['--integrity' => true, '--no-interaction' => true])
        ->expectsOutputToContain('auditor:seal')
        ->assertSuccessful();

    $env = File::get($this->env);
    preg_match('/^AUDITOR_INTEGRITY_KEY=base64:(.+)$/m', $env, $m);

    expect($env)->toContain('AUDITOR_INTEGRITY=true')
        ->and(strlen(base64_decode($m[1])))->toBe(32)
        ->and(File::get($this->env.'.example'))->toContain("AUDITOR_INTEGRITY_KEY=\n");

    $this->artisan('auditor:install', ['--integrity' => true, '--no-interaction' => true])
        ->expectsOutputToContain('already exists');

    expect(File::get($this->env))->toBe($env);
});

it('asks before migrating when interactive', function (): void {
    $this->artisan('auditor:install')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->assertSuccessful();
});
