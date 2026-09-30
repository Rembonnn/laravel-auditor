<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('B2: records created with the new values', function (): void {
    $post = Post::query()->create(['title' => 'Hello', 'meta' => ['lang' => 'id']]);

    $change = changes()->sole();

    expect($change->event)->toBe(ChangeEvent::Created)
        ->and($change->auditable_type)->toBe($post->getMorphClass())
        ->and($change->auditable_id)->toBe((string) $post->id)
        ->and($change->old_values)->toBeNull()
        ->and($change->new_values)->toMatchArray(['title' => 'Hello', 'meta' => ['lang' => 'id']])
        ->and($change->new_values)->not->toHaveKey('updated_at');
});

it('B2: records updated with old and new values of changed attributes only', function (): void {
    $post = Post::query()->create(['title' => 'Old', 'body' => 'Same']);

    $post->update(['title' => 'New', 'body' => 'Same']);

    $change = changes()->last();

    expect($change->event)->toBe(ChangeEvent::Updated)
        ->and($change->old_values)->toBe(['title' => 'Old'])
        ->and($change->new_values)->toBe(['title' => 'New']);
});

it('stores JSON columns decoded and dates as stored', function (): void {
    $post = Post::query()->create(['title' => 'x', 'meta' => ['a' => 1]]);

    $post->update(['meta' => ['a' => 2, 'b' => [1, 2]], 'published_at' => '2026-09-29 10:00:00']);

    expect(changes()->last())
        ->old_values->toBe(['meta' => ['a' => 1], 'published_at' => null])
        ->new_values->toBe(['meta' => ['a' => 2, 'b' => [1, 2]], 'published_at' => '2026-09-29 10:00:00']);
});

it('records a soft delete as deleted with deleted_at in the new values', function (): void {
    $post = Post::query()->create(['title' => 'x']);

    $post->delete();

    $change = changes()->last();

    expect($change->event)->toBe(ChangeEvent::Deleted)
        ->and($change->old_values)->toMatchArray(['title' => 'x', 'deleted_at' => null])
        ->and($change->new_values)->toHaveKey('deleted_at')
        ->and($change->new_values['deleted_at'])->not->toBeNull();
});

it('records restored once', function (): void {
    $post = Post::query()->create(['title' => 'x']);
    $post->delete();

    $post->restore();

    $change = changes()->last();

    expect(changes())->toHaveCount(3)
        ->and($change->event)->toBe(ChangeEvent::Restored)
        ->and($change->old_values['deleted_at'])->not->toBeNull()
        ->and($change->new_values)->toBe(['deleted_at' => null]);
});

it('records a force delete only as force_deleted', function (): void {
    $post = Post::query()->create(['title' => 'x']);

    $post->forceDelete();

    expect(changes()->pluck('event')->all())->toBe([ChangeEvent::Created, ChangeEvent::ForceDeleted])
        ->and(changes()->last()->old_values)->toMatchArray(['title' => 'x'])
        ->and(changes()->last()->new_values)->toBeNull();
});

it('records a hard delete of a model without soft deletes', function (): void {
    $user = User::make(['name' => 'Budi']);

    $user->delete();

    expect(changes()->last())
        ->event->toBe(ChangeEvent::Deleted)
        ->old_values->toMatchArray(['name' => 'Budi']);
});

it('respects $auditEvents on the model', function (): void {
    $post = new class extends Post
    {
        protected array $auditEvents = ['deleted'];
    };

    $model = $post->newQuery()->create(['title' => 'x']);
    $model->update(['title' => 'y']);
    $model->delete();

    expect(changes()->pluck('event')->all())->toBe([ChangeEvent::Deleted]);
});

it('creates an "other" entry for changes outside any request, job or command', function (): void {
    Post::query()->create(['title' => 'x']);

    $entry = entries()->sole();

    expect($entry->type)->toBe(EntryType::Other)
        ->and($entry->model_changes_count)->toBe(1)
        ->and(changes()->first()->entry_id)->toBe($entry->id)
        ->and(changes()->first()->correlation_id)->toBe($entry->correlation_id);
});

it('attributes changes to the acting user', function (): void {
    $user = User::make();
    $this->actingAs($user);

    Post::query()->create(['title' => 'x']);

    expect(changes()->last())->user_type->toBe($user->getMorphClass())->user_id->toBe((string) $user->id);
});

it('links changes made during a request to the request entry', function (): void {
    $this->post('/posts', ['title' => 'Via HTTP'])->assertCreated();

    $entry = entries('http')->sole();

    expect(changes()->sole()->entry_id)->toBe($entry->id)
        ->and($entry->modelChanges)->toHaveCount(1);
});

it('lists changes newest first through the audits relation', function (): void {
    $post = Post::query()->create(['title' => 'a']);
    $post->update(['title' => 'b']);

    expect($post->audits()->pluck('event')->all())->toBe([ChangeEvent::Updated, ChangeEvent::Created])
        ->and($post->audits()->where('event', ChangeEvent::Updated)->count())->toBe(1);
});

it('supports eager loading the audits relation', function (): void {
    $a = Post::query()->create(['title' => 'a']);
    $b = Post::query()->create(['title' => 'b']);
    $b->update(['title' => 'bb']);

    $posts = Post::query()->with('audits')->orderBy('id')->get();

    expect($posts[0]->audits)->toHaveCount(1)->and($posts[1]->audits)->toHaveCount(2);
});

it('queries changes by causer and date', function (): void {
    $user = User::make();
    $this->actingAs($user);
    Post::query()->create(['title' => 'x']);

    expect(ModelChange::query()->causedBy($user)->since(now()->subWeek())->count())->toBe(1);
});

it('can pause auditing with withoutAuditing()', function (): void {
    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'quiet']));

    expect($post)->toBeInstanceOf(Post::class)->and(changes())->toBeEmpty();
});
