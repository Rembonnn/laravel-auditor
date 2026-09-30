<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('records in memory and offers assertions', function (): void {
    $fake = Auditor::fake();
    $user = User::make();
    $post = Post::query()->create(['title' => 'Old', 'user_id' => $user->id]);

    $this->actingAs($user)->put("/posts/{$post->id}", ['title' => 'Baru'])->assertOk();

    Auditor::assertChangeRecorded(Post::class, $post->id, ChangeEvent::Updated,
        fn (ModelChangeData $change): bool => $change->newValues['title'] === 'Baru');
    Auditor::assertEntryRecorded(fn (EntryData $e): bool => $e->userId === (string) $user->id && $e->name === 'posts.update');
    Auditor::assertAbilityGranted('update');
    Auditor::assertChangeNotRecorded(Post::class, $post->id, ChangeEvent::Deleted);

    expect(entries())->toBeEmpty()->and(changes())->toBeEmpty()
        ->and($fake->changes())->not->toBeEmpty();
});

it('asserts denied abilities', function (): void {
    Auditor::fake();
    $post = Post::query()->create(['title' => 'x']);

    $this->actingAs(User::make())->delete("/posts/{$post->id}")->assertForbidden();

    Auditor::assertAbilityDenied('delete');
});

it('asserts that nothing was recorded', function (): void {
    Auditor::fake();
    config(['auditor.enabled' => false]);

    $this->get('/ping');

    Auditor::assertNothingRecorded();
});

it('fails assertions that do not hold', function (): void {
    Auditor::fake();

    expect(fn () => Auditor::assertEntryRecorded())->toThrow(ExpectationFailedException::class)
        ->and(fn () => Auditor::assertAbilityDenied('x'))->toThrow(ExpectationFailedException::class);
});

it('is not affected by queue mode', function (): void {
    config(['auditor.queue.enabled' => true]);
    Auditor::fake();

    $this->get('/ping');

    Auditor::assertEntryRecorded(fn (EntryData $e): bool => $e->name === 'ping');
    Auditor::assertEntryNotRecorded(fn (EntryData $e): bool => $e->name === 'other');
});
