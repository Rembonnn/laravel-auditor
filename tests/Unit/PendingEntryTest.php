<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Data\AbilityCheck;
use Rembon\LaravelAuditor\Data\MailRecord;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Data\NotificationRecord;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\PendingEntry;

function pendingEntry(): PendingEntry
{
    return new PendingEntry('01J000000000000000000000AA', 'corr', EntryType::Http, 'GET /', '2026-01-01 00:00:00.000000', 0);
}

it('starts empty', function (): void {
    $entry = pendingEntry();

    expect($entry->deniedAbilitiesCount)->toBe(0)
        ->and($entry->changesCount)->toBe(0)
        ->and($entry->ignored)->toBeFalse()
        ->and($entry->trackRetrieved)->toBeTrue()
        ->and($entry->maxIds)->toBe(50)
        ->and($entry->persisted)->toBeFalse()
        ->and($entry->hasSomethingWorthKeeping())->toBeFalse()
        ->and(PendingEntry::MAX_ABILITIES)->toBe(100)
        ->and(PendingEntry::MAX_MESSAGES)->toBe(100);
});

it('counts repeated ability checks instead of storing them twice', function (): void {
    $entry = pendingEntry();

    $entry->addAbility(new AbilityCheck('update', false, [['type' => 'post', 'id' => '1']]));
    $entry->addAbility(new AbilityCheck('update', false, [['type' => 'post', 'id' => '1']]));

    expect(array_values($entry->abilities))->toBe([
        ['ability' => 'update', 'result' => false, 'arguments' => [['type' => 'post', 'id' => '1']], 'count' => 2],
    ])->and($entry->deniedAbilitiesCount)->toBe(1);
});

it('keeps ability checks apart when ability, result or arguments differ', function (): void {
    $entry = pendingEntry();

    $entry->addAbility(new AbilityCheck('update', true, ['1']));
    $entry->addAbility(new AbilityCheck('delete', true, ['1']));
    $entry->addAbility(new AbilityCheck('update', false, ['1']));
    $entry->addAbility(new AbilityCheck('update', true, ['2']));
    $entry->addAbility(new AbilityCheck('update', null, ['1']));

    expect($entry->abilities)->toHaveCount(5)
        ->and($entry->deniedAbilitiesCount)->toBe(2)
        ->and($entry->hasSomethingWorthKeeping())->toBeTrue();
});

it('stores at most MAX_ABILITIES distinct ability checks', function (): void {
    $entry = pendingEntry();

    foreach (range(1, PendingEntry::MAX_ABILITIES + 1) as $i) {
        $entry->addAbility(new AbilityCheck("ability-{$i}", false));
    }

    expect($entry->abilities)->toHaveCount(100)
        ->and($entry->deniedAbilitiesCount)->toBe(100);

    // Already known checks are still counted once the limit is reached.
    $entry->addAbility(new AbilityCheck('ability-1', false));

    expect(array_values($entry->abilities)[0]['count'])->toBe(2);
});

it('caps the ids per model but keeps counting', function (): void {
    $entry = pendingEntry();

    foreach (['1', '2', '3', '2'] as $id) {
        $entry->addModelAccess('post', $id, 2);
    }

    expect($entry->toData()->modelsAccessed)->toBe(['post' => ['ids' => ['1', '2'], 'count' => 4]]);
});

it('stores at most MAX_MESSAGES mails and notifications', function (): void {
    $entry = pendingEntry();

    foreach (range(1, PendingEntry::MAX_MESSAGES + 1) as $i) {
        $entry->addMail(new MailRecord(null, "Mail {$i}"));
        $entry->addNotification(new NotificationRecord('Welcome', 'mail'));
    }

    expect($entry->mails)->toHaveCount(100)
        ->and($entry->notifications)->toHaveCount(100)
        ->and($entry->mails[99]['subject'])->toBe('Mail 100');
});

it('pulls buffered changes but keeps counting them', function (): void {
    $entry = pendingEntry();
    $change = new ModelChangeData(
        ulid: '01J000000000000000000000BB',
        entryUlid: null,
        correlationId: 'corr',
        auditableType: 'post',
        auditableId: '1',
        event: ChangeEvent::Updated,
        oldValues: ['a' => 1],
        newValues: ['a' => 2],
        userType: null,
        userId: null,
        createdAt: '2026-01-01 00:00:00.000000',
    );

    $entry->addChange($change);

    expect($entry->pullChanges())->toBe([$change])
        ->and($entry->pullChanges())->toBe([])
        ->and($entry->changesCount)->toBe(1)
        ->and($entry->hasSomethingWorthKeeping())->toBeTrue();
});

it('converts to EntryData with string tags and typed attributes', function (): void {
    $entry = pendingEntry();
    $entry->tags = ['2024' => true, 'billing' => true];
    $entry->user = ['type' => 'user', 'id' => '5', 'guard' => 'web'];
    $entry->attributes = ['http_method' => 'POST', 'status_code' => '201', 'failed' => 1, 'input' => ['a' => 1]];

    $data = $entry->toData('2026-01-01 00:00:01.000000', 12);

    expect($data->tags)->toBe(['2024', 'billing'])
        ->and($data->userType)->toBe('user')
        ->and($data->userId)->toBe('5')
        ->and($data->guard)->toBe('web')
        ->and($data->httpMethod)->toBe('POST')
        ->and($data->statusCode)->toBe(201)
        ->and($data->failed)->toBeTrue()
        ->and($data->input)->toBe(['a' => 1])
        ->and($data->durationMs)->toBe(12)
        ->and($data->completedAt)->toBe('2026-01-01 00:00:01.000000');
});

it('is not marked as failed unless told so', function (): void {
    $data = pendingEntry()->toData();

    expect($data->failed)->toBeFalse()
        ->and($data->userId)->toBeNull()
        ->and($data->tags)->toBe([]);
});
