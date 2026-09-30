<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

it('P3: keeps at most max_ids_per_model ids but counts everything', function (): void {
    config(['auditor.models.max_ids_per_model' => 5]);
    Auditor::withoutAuditing(fn () => collect(range(1, 30))->each(fn ($i) => Post::query()->create(['title' => "p{$i}"])));

    $this->get('/posts')->assertOk();

    $accessed = entries('http')->sole()->models_accessed[(new Post)->getMorphClass()];

    expect($accessed['count'])->toBe(30)
        ->and($accessed['ids'])->toBe(Post::query()->orderBy('id')->limit(5)->pluck('id')->map(fn ($id): string => (string) $id)->all());
});

it('counts repeated loads of the same record', function (): void {
    Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    $this->get('/posts');

    expect(entries('http')->sole()->models_accessed[(new Post)->getMorphClass()])->toBe(['ids' => [(string) Post::query()->value('id')], 'count' => 1]);
});

it('can be disabled globally or per model', function (): void {
    Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    config(['auditor.models.track_retrieved' => false]);
    $this->get('/posts');

    expect(entries('http')->last()->models_accessed)->toBeNull();

    config(['auditor.models.track_retrieved' => true]);
    $model = new class extends Post
    {
        protected bool $auditRetrieved = false;
    };

    expect($model->shouldAuditRetrieved())->toBeFalse();
});

it('handles 10,000 loaded models without keeping them all', function (): void {
    config(['auditor.models.max_ids_per_model' => 50]);
    $rows = array_map(fn ($i): array => ['title' => "p{$i}", 'created_at' => now(), 'updated_at' => now()], range(1, 10_000));
    foreach (array_chunk($rows, 500) as $chunk) {
        Post::query()->insert($chunk);
    }

    $this->get('/posts/stream')->assertOk()->assertSee('10000');

    $accessed = entries('http')->sole()->models_accessed[(new Post)->getMorphClass()];

    expect($accessed['count'])->toBe(10_000)->and($accessed['ids'])->toHaveCount(50);
});
