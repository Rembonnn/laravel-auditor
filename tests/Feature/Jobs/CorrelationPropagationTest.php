<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('carries the correlation id and the causer from a request into a queued job', function (): void {
    config(['queue.default' => 'database']);
    $user = Auditor::withoutAuditing(fn (): User => User::make());
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    $this->actingAs($user)->post("/posts/{$post->id}/process")->assertOk();

    // The worker is another process: no authenticated user, fresh state.
    app('auth')->forgetGuards();
    app()->forgetScopedInstances();
    Context::flush();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    $http = entries('http')->sole();
    $job = entries('job')->sole();

    expect($job->correlation_id)->toBe($http->correlation_id)
        ->and($job->user_id)->toBe((string) $user->id)
        ->and($job->user_type)->toBe($user->getMorphClass())
        ->and(changes()->sole())->user_id->toBe((string) $user->id)->correlation_id->toBe($http->correlation_id)
        ->and(Entry::query()->forCorrelation($http->correlation_id)->count())->toBe(2);
});

it('exposes the current correlation id', function (): void {
    $id = Auditor::correlationId();

    expect($id)->toHaveLength(26)->and(Auditor::correlationId())->toBe($id);
});

it('restores the outer correlation id after an entry finishes', function (): void {
    $recorder = app(Recorder::class);

    $entry = $recorder->start(EntryType::Command, 'x');
    $inside = Auditor::correlationId();
    $recorder->finish($entry);

    expect(Auditor::correlationId())->not->toBe($inside);
});
