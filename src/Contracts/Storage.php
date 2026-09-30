<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Contracts;

use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;

interface Storage
{
    /**
     * Persist an entry together with model changes that belong to it.
     *
     * The same entry (same ulid) can arrive more than once: first as a
     * partial entry when buffered changes are flushed early, then complete.
     * Implementations must treat the call as an upsert and must never
     * replace a complete entry with a partial one.
     *
     * @param  EntryData|null  $entry  null when only detached changes are stored
     * @param  list<ModelChangeData>  $changes
     */
    public function store(?EntryData $entry, array $changes = []): void;
}
