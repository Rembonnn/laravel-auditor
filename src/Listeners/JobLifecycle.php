<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Jobs\PersistEntry;
use Rembon\LaravelAuditor\Support\Settings;

/**
 * One entry per processed job. The correlation id and the causer come from
 * the Context the job inherited from whoever dispatched it.
 */
final readonly class JobLifecycle
{
    public function __construct(private Auditor $auditor) {}

    public function processing(JobProcessing $event): void
    {
        $this->auditor->guard(function () use ($event): void {
            $name = $event->job->resolveName();

            if (! $this->shouldRecord($name)) {
                return;
            }

            $this->auditor->recorder()->start(
                type: EntryType::Job,
                name: $name,
                attributes: [
                    'hostname' => gethostname() ?: null,
                    'input' => [
                        'connection' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'attempts' => $event->job->attempts(),
                        'uuid' => $event->job->uuid(),
                    ],
                ],
                key: self::key($event->job),
            );
        });
    }

    public function processed(JobProcessed $event): void
    {
        $this->finish($event->job, failed: $event->job->hasFailed());
    }

    public function failed(JobFailed $event): void
    {
        $this->finish($event->job, failed: true);
    }

    public function exceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->finish($event->job, failed: true);
    }

    private function finish(Job $job, bool $failed): void
    {
        $this->auditor->guard(function () use ($job, $failed): void {
            $recorder = $this->auditor->recorder();

            if ($entry = $recorder->find(self::key($job))) {
                $recorder->finish($entry, ['failed' => $failed]);
            }
        });
    }

    private function shouldRecord(string $name): bool
    {
        if (! config('auditor.enabled', true) || ! config('auditor.jobs.enabled', true)) {
            return false;
        }

        $except = [PersistEntry::class, ...Settings::strings('auditor.jobs.except')];

        return ! Str::is($except, $name);
    }

    private static function key(Job $job): string
    {
        return 'job:'.($job->uuid() ?? $job->getJobId() ?? spl_object_id($job)).':'.$job->attempts();
    }
}
