<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('gives each job in a worker its own entry, correlation and causer', function (): void {
    config(['queue.default' => 'database']);
    [$alice, $bob] = Auditor::withoutAuditing(fn (): array => [User::make(), User::make()]);
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    $this->actingAs($alice)->post("/posts/{$post->id}/process");
    $this->actingAs($bob)->post("/posts/{$post->id}/process");

    app('auth')->forgetGuards();
    Context::flush();

    // Two jobs in one process, with the reset the worker performs in between.
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);
    app()->forgetScopedInstances();
    Context::flush();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    $jobs = entries('job');
    $requests = entries('http');

    expect($jobs)->toHaveCount(2)
        ->and($jobs[0]->user_id)->toBe((string) $alice->id)
        ->and($jobs[1]->user_id)->toBe((string) $bob->id)
        ->and($jobs[0]->correlation_id)->toBe($requests[0]->correlation_id)
        ->and($jobs[1]->correlation_id)->toBe($requests[1]->correlation_id);
});
