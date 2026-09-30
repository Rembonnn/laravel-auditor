<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Tests\Fixtures\Post;

it('ignores touch()', function (): void {
    $post = Post::query()->create(['title' => 'x']);
    $this->travel(1)->minutes();

    $post->touch();

    expect(changes())->toHaveCount(1);
});

it('ignores updates that only change excluded attributes', function (): void {
    $post = Post::query()->create(['title' => 'x']);

    $post->increment('view_count');
    $post->update(['view_count' => 10]);

    expect(changes())->toHaveCount(1);
});

it('drops excluded attributes from recorded values', function (): void {
    $post = Post::query()->create(['title' => 'x', 'view_count' => 3]);

    $post->update(['title' => 'y', 'view_count' => 4]);

    expect(changes()->first()->new_values)->not->toHaveKey('view_count')
        ->and(changes()->last()->new_values)->toBe(['title' => 'y']);
});

it('honours the global exclude list', function (): void {
    config(['auditor.models.exclude' => ['updated_at', 'body']]);
    $post = Post::query()->create(['title' => 'x', 'body' => 'b']);

    $post->update(['body' => 'c']);

    expect(changes())->toHaveCount(1)->and(changes()->first()->new_values)->not->toHaveKey('body');
});
