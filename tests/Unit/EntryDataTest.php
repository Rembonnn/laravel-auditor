<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Data\AbilityCheck;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\MailRecord;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Data\NotificationRecord;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;

it('round-trips EntryData through arrays', function (): void {
    $entry = new EntryData(
        ulid: '01J9Z0000000000000000000AA',
        correlationId: 'corr',
        type: EntryType::Http,
        name: 'posts.store',
        startedAt: '2026-09-29 10:00:00.000000',
        completedAt: '2026-09-29 10:00:00.150000',
        userType: 'user',
        userId: '5',
        guard: 'web',
        httpMethod: 'POST',
        url: 'https://x.test/posts',
        statusCode: 201,
        durationMs: 150,
        abilities: [['ability' => 'create', 'result' => true, 'arguments' => []]],
        modelsAccessed: ['App\\Post' => ['ids' => ['1'], 'count' => 1]],
        properties: ['a' => 1],
        tags: ['x'],
        deniedAbilitiesCount: 0,
    );

    $copy = EntryData::fromArray(json_decode(json_encode($entry->toArray()), true));

    expect($copy)->toEqual($entry)->and($copy->isComplete())->toBeTrue();
});

it('round-trips ModelChangeData and detaches it from its entry', function (): void {
    $change = new ModelChangeData('u', 'e', 'c', 'post', '1', ChangeEvent::Updated, ['a' => 1], ['a' => 2], 'user', '5', '2026-09-29 10:00:00.000000');

    expect(ModelChangeData::fromArray($change->toArray()))->toEqual($change)
        ->and($change->withoutEntry()->entryUlid)->toBeNull()
        ->and($change->withoutEntry()->ulid)->toBe('u');
});

it('round-trips the small records', function (): void {
    $ability = new AbilityCheck('delete', false, [['type' => 'post', 'id' => '1']]);
    $mail = new MailRecord('WelcomeMail', 'Hi', ['a@b.c']);
    $notification = new NotificationRecord('PostPublished', 'mail', 'user', '5');

    expect(AbilityCheck::fromArray($ability->toArray()))->toEqual($ability)
        ->and($ability->denied())->toBeTrue()
        ->and((new AbilityCheck('x', null))->denied())->toBeTrue()
        ->and(MailRecord::fromArray($mail->toArray()))->toEqual($mail)
        ->and(NotificationRecord::fromArray($notification->toArray()))->toEqual($notification);
});

it('maps observer method names to change events', function (): void {
    expect(ChangeEvent::fromObserverMethod('forceDeleted'))->toBe(ChangeEvent::ForceDeleted)
        ->and(ChangeEvent::fromObserverMethod('created'))->toBe(ChangeEvent::Created);
});
