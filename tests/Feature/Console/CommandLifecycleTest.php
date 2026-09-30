<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

beforeEach(function (): void {
    Artisan::command('posts:rename {id} {title} {--api-token=}', function (int $id, string $title): void {
        Post::query()->findOrFail($id)->update(['title' => $title]);
    });

    Artisan::command('posts:fail', fn (): int => 3);
});

it('records a command with os user, host, input and its changes', function (): void {
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));

    $this->artisan('posts:rename', ['id' => $post->id, 'title' => 'renamed', '--api-token' => 'secret'])->assertSuccessful();

    $entry = entries('command')->sole();

    expect($entry->name)->toBe('posts:rename')
        ->and($entry->os_user)->not->toBeEmpty()
        ->and($entry->hostname)->toBe(gethostname())
        ->and($entry->status_code)->toBe(0)
        ->and($entry->failed)->toBeFalse()
        ->and($entry->input)->toMatchArray(['id' => (string) $post->id, 'title' => 'renamed', 'api-token' => '[REDACTED]'])
        ->and($entry->model_changes_count)->toBe(1)
        ->and(changes()->sole()->entry_id)->toBe($entry->id);
});

it('records the exit code of failing commands', function (): void {
    $this->artisan('posts:fail')->assertExitCode(3);

    expect(entries('command')->sole())->status_code->toBe(3)->failed->toBeTrue();
});

it('skips excluded commands (glob)', function (): void {
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    $this->artisan('auditor:prune');

    expect(entries('command'))->toBeEmpty();
});

it('can be disabled or skip the os user', function (): void {
    config(['auditor.console.record_os_user' => false]);
    $this->artisan('posts:fail');
    expect(entries('command')->sole()->os_user)->toBeNull();

    config(['auditor.console.enabled' => false]);
    $this->artisan('posts:fail');
    expect(entries('command'))->toHaveCount(1);
});
