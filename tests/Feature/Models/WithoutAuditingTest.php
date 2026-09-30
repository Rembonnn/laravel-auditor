<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

it('is safe to nest', function (): void {
    Auditor::withoutAuditing(function (): void {
        Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'inner']));
        Post::query()->create(['title' => 'outer']);
    });

    Post::query()->create(['title' => 'after']);

    expect(changes())->toHaveCount(1)->and(changes()->first()->new_values['title'])->toBe('after');
});

it('resets after an exception', function (): void {
    try {
        Auditor::withoutAuditing(fn () => throw new RuntimeException('x'));
    } catch (RuntimeException) {
    }

    Post::query()->create(['title' => 'recorded']);

    expect(changes())->toHaveCount(1);
});

it('returns the callback result', function (): void {
    expect(Auditor::withoutAuditing(fn (): int => 42))->toBe(42);
});
