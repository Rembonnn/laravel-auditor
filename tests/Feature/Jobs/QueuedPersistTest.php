<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Rembon\LaravelAuditor\Jobs\PersistEntry;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;

it('dispatches PersistEntry with a scalar-only payload', function (): void {
    config(['auditor.queue.enabled' => true, 'auditor.queue.queue' => 'audit']);
    Queue::fake();

    $this->post('/posts', ['title' => 'Queued'])->assertCreated();

    Queue::assertPushedOn('audit', PersistEntry::class, function (PersistEntry $job): bool {
        $serialized = serialize($job);

        return $job->entry['type'] === 'http'
            && count($job->changes) === 1
            && ! str_contains($serialized, 'Illuminate\\Database\\Eloquent');
    });

    expect(Entry::query()->count())->toBe(0);
});

it('produces the same result as sync mode', function (): void {
    $this->post('/posts', ['title' => 'Sync'])->assertCreated();
    $sync = entries('http')->sole()->only('type', 'name', 'status_code', 'model_changes_count', 'properties', 'tags');

    Entry::query()->delete();
    ModelChange::query()->delete();

    config(['auditor.queue.enabled' => true]); // sync queue connection runs it immediately
    $this->post('/posts', ['title' => 'Sync'])->assertCreated();

    expect(entries('http')->sole()->only('type', 'name', 'status_code', 'model_changes_count', 'properties', 'tags'))->toBe($sync)
        ->and(entries('job'))->toBeEmpty();
});
