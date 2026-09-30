<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\PendingEntry;
use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * One entry per artisan command (who ran `tinker`, `migrate --force`, ...).
 */
final class CommandLifecycle
{
    /** @var list<PendingEntry|null> */
    private array $running = [];

    public function __construct(private readonly Auditor $auditor) {}

    public function starting(CommandStarting $event): void
    {
        $this->running[] = $this->auditor->guard(function () use ($event): ?PendingEntry {
            $name = $event->command;

            if (! $this->shouldRecord($name)) {
                return null;
            }

            return $this->auditor->recorder()->start(
                type: EntryType::Command,
                name: $name,
                attributes: [
                    'os_user' => config('auditor.console.record_os_user', true) ? self::osUser() : null,
                    'hostname' => gethostname() ?: null,
                    'input' => $this->input($event),
                ],
                key: 'command:'.spl_object_id($event->input),
            );
        });
    }

    public function finished(CommandFinished $event): void
    {
        $entry = array_pop($this->running);

        if ($entry === null) {
            return;
        }

        $this->auditor->guard(fn () => $this->auditor->recorder()->finish($entry, [
            'status_code' => max(0, min($event->exitCode, 65535)),
            'failed' => $event->exitCode !== 0,
        ]));
    }

    private function shouldRecord(?string $name): bool
    {
        return $name !== null
            && config('auditor.enabled', true)
            && config('auditor.console.enabled', true)
            && ! Str::is(Settings::strings('auditor.console.except'), $name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function input(CommandStarting $event): ?array
    {
        if (! config('auditor.console.capture_input', true)) {
            return null;
        }

        $input = array_filter(
            [...$event->input->getArguments(), ...$event->input->getOptions()],
            fn (mixed $value, string|int $key): bool => $key !== 'command' && $value !== null && $value !== false && $value !== [],
            ARRAY_FILTER_USE_BOTH,
        );

        return $input === [] ? null : Values::stringKeys(app(Redactor::class)->redact($input));
    }

    private static function osUser(): ?string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && $info['name'] !== '') {
                return $info['name'];
            }
        }

        $user = getenv('USER') ?: getenv('USERNAME') ?: get_current_user();

        return $user === '' ? null : $user;
    }
}
