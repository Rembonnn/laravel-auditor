<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\UlidPost;
use Rembon\LaravelAuditor\Tests\Fixtures\UuidPost;

it('works with integer, UUID and ULID keys', function (string $class): void {
    $model = $class::query()->create(['title' => 'a']);
    $model->update(['title' => 'b']);

    $fresh = $class::query()->with('audits')->find($model->getKey());

    expect($model->audits()->count())->toBe(2)
        ->and($fresh->audits)->toHaveCount(2)
        ->and($model->audits()->first()->auditable_id)->toBe((string) $model->getKey())
        ->and($model->audits()->first()->auditable)->toBeInstanceOf($class);
})->with([Post::class, UuidPost::class, UlidPost::class])->group('db');
