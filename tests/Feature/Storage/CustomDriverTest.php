<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Events\EntryRecorded;
use Rembon\LaravelAuditor\Events\ModelChangeRecorded;
use Rembon\LaravelAuditor\Facades\Auditor;

it('supports custom drivers through Auditor::extend()', function (): void {
    $store = new class implements Storage
    {
        public array $calls = [];

        public function store(?EntryData $entry, array $changes = []): void
        {
            $this->calls[] = [$entry?->name, count($changes)];
        }
    };

    Auditor::extend('memory', fn ($app): object => $store);
    config(['auditor.storage.driver' => 'memory']);

    $this->post('/posts', ['title' => 'Custom'])->assertCreated();

    expect($store->calls)->toBe([['posts.store', 1]]);
});

it('dispatches EntryRecorded and ModelChangeRecorded after persisting', function (): void {
    Event::fake([EntryRecorded::class, ModelChangeRecorded::class]);

    $this->post('/posts', ['title' => 'Evented'])->assertCreated();

    Event::assertDispatched(EntryRecorded::class, fn ($e): bool => $e->entry->name === 'posts.store');
    Event::assertDispatched(ModelChangeRecorded::class, fn ($e): bool => $e->change->newValues['title'] === 'Evented');
});
