<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('B4: records denied abilities and counts them', function (): void {
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));
    $user = Auditor::withoutAuditing(fn (): User => User::make());

    $this->actingAs($user)->delete("/posts/{$post->id}")->assertForbidden();

    $entry = entries('http')->sole();

    expect($entry->denied_abilities_count)->toBe(1)
        ->and(sortedKeys($entry->abilities))->toBe(sortedKeys([[
            'ability' => 'delete',
            'result' => false,
            'arguments' => [['type' => $post->getMorphClass(), 'id' => (string) $post->id]],
            'count' => 1,
        ]]))
        ->and(Entry::query()->withDeniedAbilities()->count())->toBe(1);
});

it('records granted abilities', function (): void {
    $user = Auditor::withoutAuditing(fn (): User => User::make());
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x', 'user_id' => $user->id]));

    $this->actingAs($user)->put("/posts/{$post->id}", ['title' => 'y'])->assertOk();

    expect(entries('http')->sole())
        ->denied_abilities_count->toBe(0)
        ->abilities->{0}->toMatchArray(['ability' => 'update', 'result' => true]);
});

it('deduplicates repeated checks with a counter', function (): void {
    Gate::define('export', fn (?User $user): false => false);
    $recorder = app(Recorder::class);
    $entry = $recorder->start(EntryType::Command, 'x');

    Gate::forUser(null)->denies('export');
    Gate::forUser(null)->denies('export');
    Gate::forUser(null)->denies('export', ['scalar-arg', new stdClass]);

    $recorder->finish($entry);

    expect(sortedKeys(entries()->sole()->abilities))->toBe(sortedKeys([
        ['ability' => 'export', 'result' => false, 'arguments' => [], 'count' => 2],
        ['ability' => 'export', 'result' => false, 'arguments' => ['scalar-arg', 'stdClass'], 'count' => 1],
    ]))->and(entries()->sole()->denied_abilities_count)->toBe(2);
});

it('can be switched off', function (): void {
    config(['auditor.listeners.gate' => false]);
    Gate::define('x', fn (?User $user): false => false);
    $recorder = app(Recorder::class);
    $entry = $recorder->start(EntryType::Command, 'x');

    Gate::forUser(null)->denies('x');
    $recorder->finish($entry);

    expect(entries()->sole()->abilities)->toBeNull();
});

it('ignores checks outside any entry', function (): void {
    Gate::define('x', fn (): false => false);

    Gate::forUser(null)->denies('x');

    expect(entries())->toBeEmpty();
});
