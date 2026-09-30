<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\ProcessPost;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('D5: a sync job inside a request gets its own entry with the same correlation id', function (): void {
    $user = Auditor::withoutAuditing(fn (): User => User::make());
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    $this->actingAs($user)->post("/posts/{$post->id}/process")->assertOk();

    $http = entries('http')->sole();
    $job = entries('job')->sole();

    expect($job->name)->toBe(ProcessPost::class)
        ->and($job->correlation_id)->toBe($http->correlation_id)
        ->and($job->user_id)->toBe((string) $user->id)
        ->and($job->failed)->toBeFalse()
        ->and($job->duration_ms)->toBeInt()
        ->and($job->input)->toMatchArray(['connection' => 'sync', 'attempts' => 1])
        ->and($job->model_changes_count)->toBe(1)
        ->and($http->model_changes_count)->toBe(0)
        ->and(changes()->sole()->entry_id)->toBe($job->id);
});

it('marks failed jobs', function (): void {
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    try {
        ProcessPost::dispatchSync($post->id, fail: true);
    } catch (RuntimeException) {
    }

    expect(entries('job')->sole())->failed->toBeTrue()->completed_at->not->toBeNull();
});

it('respects jobs.except and never audits PersistEntry', function (): void {
    config(['auditor.jobs.except' => [ProcessPost::class]]);
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    ProcessPost::dispatchSync($post->id);

    expect(entries('job'))->toBeEmpty()->and(entries(EntryType::Other->value))->toHaveCount(1);
});

it('can be disabled', function (): void {
    config(['auditor.jobs.enabled' => false]);
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    ProcessPost::dispatchSync($post->id);

    expect(entries('job'))->toBeEmpty();
});
