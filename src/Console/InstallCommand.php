<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Rembon\LaravelAuditor\Support\Settings;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'auditor:install')]
final class InstallCommand extends Command
{
    protected $signature = 'auditor:install
        {--integrity : Generate AUDITOR_INTEGRITY_KEY and enable the hash chain}
        {--migrate : Run the migrations without asking}
        {--force : Overwrite the published config and an existing integrity key}';

    protected $description = 'Install Laravel Auditor: publish config & migrations, optionally enable integrity';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Installing Laravel Auditor.');

        $this->components->task('Publishing configuration', fn (): bool => $this->callSilently('vendor:publish', [
            '--tag' => 'auditor-config',
            '--force' => (bool) $this->option('force'),
        ]) === 0);

        // Already published migrations are detected by name and skipped.
        $this->components->task('Publishing migrations', fn (): bool => $this->callSilently('vendor:publish', [
            '--tag' => 'auditor-migrations',
        ]) === 0);

        if ($this->option('integrity')) {
            $this->components->task('Writing AUDITOR_INTEGRITY_KEY to .env', fn (): bool => $this->writeIntegrityKey($files));
        }

        $migrate = $this->option('migrate')
            || ($this->input->isInteractive() && confirm('Run the migrations now?', default: true));

        if ($migrate) {
            $this->call('migrate');
        }

        $this->printNextSteps();

        return self::SUCCESS;
    }

    private function writeIntegrityKey(Filesystem $files): bool
    {
        $env = $this->laravel->environmentFilePath();
        $contents = $files->exists($env) ? $files->get($env) : '';

        if (preg_match('/^AUDITOR_INTEGRITY_KEY=.+$/m', $contents) && ! $this->option('force')) {
            $this->components->warn('AUDITOR_INTEGRITY_KEY already exists. Use --force to replace it (this invalidates the existing chain).');

            return true;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        $contents = self::setEnvValue($contents, 'AUDITOR_INTEGRITY', 'true');
        $contents = self::setEnvValue($contents, 'AUDITOR_INTEGRITY_KEY', $key);
        $files->put($env, $contents);

        $example = $env.'.example';

        if ($files->exists($example)) {
            $exampleContents = $files->get($example);

            foreach (['AUDITOR_INTEGRITY' => 'true', 'AUDITOR_INTEGRITY_KEY' => ''] as $name => $value) {
                if (! preg_match("/^{$name}=/m", $exampleContents)) {
                    $exampleContents = self::setEnvValue($exampleContents, $name, $value);
                }
            }

            $files->put($example, $exampleContents);
        }

        config(['auditor.integrity.enabled' => true, 'auditor.integrity.key' => $key]);

        return true;
    }

    private static function setEnvValue(string $contents, string $name, string $value): string
    {
        $line = "{$name}={$value}";

        if (preg_match("/^{$name}=.*$/m", $contents)) {
            return (string) preg_replace("/^{$name}=.*$/m", $line, $contents);
        }

        return rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
    }

    private function printNextSteps(): void
    {
        $path = trim(Settings::string('auditor.dashboard.path', 'auditor'), '/');

        $this->newLine();
        $this->components->info('Next steps');

        $this->line('  1. Add the trait to the models you want to audit:');
        $this->line('     <fg=gray>use Rembon\LaravelAuditor\Traits\Auditable;</>');
        $this->newLine();
        $this->line('  2. Allow access to the dashboard outside "local" (AppServiceProvider::boot):');
        $this->line("     <fg=gray>Gate::define('viewAuditor', fn (\$user) => \$user->is_admin);</>");
        $this->newLine();
        $this->line('  3. Schedule maintenance (routes/console.php):');
        $this->line("     <fg=gray>Schedule::command('auditor:prune')->daily();</>");

        if (config('auditor.integrity.enabled')) {
            $this->line("     <fg=gray>Schedule::command('auditor:seal')->everyMinute()->withoutOverlapping();</>");
            $this->line("     <fg=gray>Schedule::command('auditor:verify')->daily();</>");
        }

        $this->newLine();
        $this->line('  Dashboard: <fg=green>'.url($path).'</>');
    }
}
