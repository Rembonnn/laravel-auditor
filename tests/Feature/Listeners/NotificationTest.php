<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\PostPublished;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('records sent notifications', function (): void {
    $user = Auditor::withoutAuditing(fn (): User => User::make());

    $this->actingAs($user)->post('/notify')->assertOk();

    $entry = entries('http')->sole();

    expect(sortedKeys($entry->notifications))->toBe(sortedKeys([[
        'notification' => PostPublished::class,
        'channel' => 'mail',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => (string) $user->id,
    ]]))
        ->and($entry->mails[0]['mailable'])->toBe(PostPublished::class);
});

it('can be switched off', function (): void {
    config(['auditor.listeners.notifications' => false]);
    $user = Auditor::withoutAuditing(fn (): User => User::make());

    $this->actingAs($user)->post('/notify')->assertOk();

    expect(entries('http')->sole()->notifications)->toBeNull();
});
