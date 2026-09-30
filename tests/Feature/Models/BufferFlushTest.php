<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

it('flushes buffered changes early and completes the same entry', function (): void {
    config(['auditor.buffer_size' => 3]);
    $recorder = app(Recorder::class);
    $entry = $recorder->start(EntryType::Command, 'import:posts');

    foreach (range(1, 7) as $i) {
        Post::query()->create(['title' => "p{$i}"]);
    }

    // Two flushes of 3 happened; 1 change is still buffered.
    expect(changes())->toHaveCount(6)
        ->and(entries()->sole()->completed_at)->toBeNull()
        ->and($entry->changes)->toHaveCount(1);

    $recorder->finish($entry);

    $stored = entries()->sole();

    expect(changes())->toHaveCount(7)
        ->and($stored->completed_at)->not->toBeNull()
        ->and($stored->model_changes_count)->toBe(7)
        ->and($stored->name)->toBe('import:posts')
        ->and(changes()->pluck('entry_id')->unique()->all())->toBe([$stored->id]);
});

it('keeps flushed changes detached when the entry is ignored', function (): void {
    config(['auditor.buffer_size' => 2]);
    $recorder = app(Recorder::class);
    $entry = $recorder->start(EntryType::Command, 'x');
    $recorder->ignore();

    Post::query()->create(['title' => 'a']);
    Post::query()->create(['title' => 'b']);
    $recorder->finish($entry);

    expect(entries())->toBeEmpty()->and(changes())->toHaveCount(2)->and(changes()->pluck('entry_id')->filter())->toBeEmpty();
});
