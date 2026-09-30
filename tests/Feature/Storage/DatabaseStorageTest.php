<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Storage\DatabaseStorage;

it('uses a custom connection and table names', function (): void {
    config([
        'database.connections.audit' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
        'auditor.storage.database.connection' => 'audit',
        'auditor.storage.database.tables' => [
            'entries' => 'audit_log',
            'model_changes' => 'audit_changes',
            'checkpoints' => 'audit_checkpoints',
        ],
    ]);

    foreach (glob(__DIR__.'/../../../database/migrations/*.php') as $migration) {
        (require $migration)->up();
    }

    $this->post('/posts', ['title' => 'Elsewhere'])->assertCreated();

    expect(DB::connection('audit')->table('audit_log')->count())->toBe(1)
        ->and(DB::connection('audit')->table('audit_changes')->count())->toBe(1)
        ->and(Schema::hasTable('auditor_entries'))->toBeTrue()
        ->and(DB::table('auditor_entries')->count())->toBe(0)
        ->and(Entry::query()->count())->toBe(1)
        ->and((new Entry)->getTable())->toBe('audit_log');
});

it('never replaces a complete entry with a partial one', function (): void {
    $storage = app(DatabaseStorage::class, ['db' => app('db'), 'config' => app('config')]);
    $complete = new EntryData('01J9Z00000000000000000000A', 'c', EntryType::Job, 'done', '2026-09-29 10:00:00.000000', '2026-09-29 10:00:01.000000', statusCode: 1);
    $partial = new EntryData('01J9Z00000000000000000000A', 'c', EntryType::Job, 'partial', '2026-09-29 10:00:00.000000');

    $storage->store($complete);
    $storage->store($partial);

    expect(Entry::query()->sole())->name->toBe('done')->completed_at->not->toBeNull();
});

it('stores empty collections as null and truncates long values', function (): void {
    $storage = new DatabaseStorage(app('db'), app('config'));
    $storage->store(new EntryData('01J9Z00000000000000000000B', 'c', EntryType::Http, str_repeat('n', 300), '2026-09-29 10:00:00.000000', '2026-09-29 10:00:00.000000', userAgent: str_repeat('u', 600)));

    $row = DB::table('auditor_entries')->first();

    expect($row->abilities)->toBeNull()->and($row->tags)->toBeNull()
        ->and(strlen($row->name))->toBe(255)->and(strlen($row->user_agent))->toBe(512);
});
