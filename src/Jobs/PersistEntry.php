<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;

/**
 * Persists an entry from the queue. The payload only holds arrays of
 * scalars, never Eloquent models.
 */
final class PersistEntry implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>|null  $entry
     * @param  list<array<string, mixed>>  $changes
     */
    public function __construct(
        public readonly ?array $entry,
        public readonly array $changes = [],
    ) {}

    public function handle(Auditor $auditor): void
    {
        $auditor->storeNow(
            $this->entry === null ? null : EntryData::fromArray($this->entry),
            array_map(ModelChangeData::fromArray(...), $this->changes),
        );
    }
}
