<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Rembon\LaravelAuditor\Integrity\RowHasher;

function sampleRow(string $table): array
{
    $values = ['int' => 7, 'string' => 'value', 'bool' => false, 'json' => '{"a":1}', 'timestamp' => '2026-09-29 10:00:00.123456'];

    return array_map(fn (string $type): int|string|false => $values[$type], RowHasher::columns()[$table]);
}

function changed(string $type): mixed
{
    return match ($type) {
        'int' => 8,
        'string' => 'other',
        'bool' => true,
        'json' => '{"a":2}',
        'timestamp' => '2026-09-29 10:00:01.123456',
    };
}

it('covers every column of every table with the hash', function (string $table): void {
    $hasher = new RowHasher('secret');
    $row = sampleRow($table);
    $original = $hasher->hash($table, $row, RowHasher::GENESIS);

    foreach (RowHasher::columns()[$table] as $column => $type) {
        $tampered = [...$row, $column => changed($type)];

        expect($hasher->hash($table, $tampered, RowHasher::GENESIS))->not->toBe($original, "Column [{$column}] is not protected by the hash.");
    }
})->with(['entries', 'model_changes']);

it('ignores columns that legitimately change after sealing', function (): void {
    $hasher = new RowHasher('secret');
    $entry = sampleRow('entries');
    $change = sampleRow('model_changes');

    expect($hasher->hash('entries', [...$entry, 'model_changes_count' => 5, 'hash' => 'x', 'previous_hash' => 'y'], RowHasher::GENESIS))
        ->toBe($hasher->hash('entries', $entry, RowHasher::GENESIS))
        ->and($hasher->hash('model_changes', [...$change, 'entry_id' => null], RowHasher::GENESIS))
        ->toBe($hasher->hash('model_changes', [...$change, 'entry_id' => 99], RowHasher::GENESIS));
});

it('normalises values the way different database drivers return them', function (): void {
    $canonical = fn (array $overrides): array => RowHasher::canonical('entries', [...sampleRow('entries'), ...$overrides]);

    expect($canonical(['id' => '7']))->toBe($canonical(['id' => 7]))
        ->and($canonical(['failed' => 't']))->toBe($canonical(['failed' => true]))
        ->and($canonical(['failed' => '1']))->toBe($canonical(['failed' => 1]))
        ->and($canonical(['failed' => 'true']))->toBe($canonical(['failed' => true]))
        ->and($canonical(['failed' => 0]))->toBe($canonical(['failed' => false]))
        ->and($canonical(['failed' => 'f']))->toBe($canonical(['failed' => false]))
        ->and($canonical(['abilities' => '{"b":1,"a":2}'])['abilities'])->toBe(['b' => 1, 'a' => 2])
        ->and($canonical(['abilities' => ['a' => 2]])['abilities'])->toBe(['a' => 2])
        ->and($canonical(['started_at' => '2026-09-29 10:00:00'])['started_at'])->toBe('2026-09-29T10:00:00.000000Z')
        ->and($canonical(['started_at' => Carbon::parse('2026-09-29 10:00:00.5')])['started_at'])->toBe('2026-09-29T10:00:00.500000Z')
        ->and($canonical(['name' => 123])['name'])->toBe('123')
        ->and($canonical(['name' => null])['name'])->toBeNull()
        ->and($canonical(['name' => ''])['name'])->toBe('')
        ->and(array_keys($canonical([])))->toBe(array_keys(RowHasher::columns()['entries']));
});

it('reads stored timestamps in the application time zone', function (): void {
    config(['app.timezone' => 'Asia/Jakarta']);

    expect(RowHasher::canonical('entries', ['started_at' => '2026-09-29 17:00:00'])['started_at'])->toBe('2026-09-29T10:00:00.000000Z');
});

it('chains on the previous hash and on the key', function (): void {
    $row = sampleRow('entries');

    expect((new RowHasher('a'))->hash('entries', $row, RowHasher::GENESIS))
        ->not->toBe((new RowHasher('b'))->hash('entries', $row, RowHasher::GENESIS))
        ->not->toBe((new RowHasher('a'))->hash('entries', $row, str_repeat('1', 64)))
        ->toMatch('/^[0-9a-f]{64}$/')
        ->and((new RowHasher('a'))->hash('entries', (object) $row, RowHasher::GENESIS))
        ->toBe((new RowHasher('a'))->hash('entries', $row, RowHasher::GENESIS));
});

it('reads base64 keys from the config and refuses an empty key', function (): void {
    config(['auditor.integrity.key' => 'base64:'.base64_encode('raw-key-bytes')]);
    $row = sampleRow('entries');

    expect(RowHasher::fromConfig()->hash('entries', $row, RowHasher::GENESIS))
        ->toBe((new RowHasher('raw-key-bytes'))->hash('entries', $row, RowHasher::GENESIS));

    config(['auditor.integrity.key' => 'plain-key']);
    expect(RowHasher::fromConfig()->hash('entries', $row, RowHasher::GENESIS))
        ->toBe((new RowHasher('plain-key'))->hash('entries', $row, RowHasher::GENESIS));

    config(['auditor.integrity.key' => '']);
    expect(fn (): RowHasher => RowHasher::fromConfig())->toThrow(InvalidArgumentException::class, 'AUDITOR_INTEGRITY_KEY');
});

it('rejects unknown tables', function (): void {
    expect(fn (): array => RowHasher::canonical('users', []))->toThrow(InvalidArgumentException::class)
        ->and(RowHasher::tables())->toBe(['entries', 'model_changes']);
});

it('hashes exactly these columns, so existing chains stay verifiable', function (): void {
    // Changing this list (or the normalisation) invalidates every sealed row.
    expect(RowHasher::columns())->toBe([
        'entries' => [
            'id' => 'int', 'ulid' => 'string', 'correlation_id' => 'string', 'type' => 'string', 'name' => 'string',
            'user_type' => 'string', 'user_id' => 'string', 'guard' => 'string', 'http_method' => 'string', 'url' => 'string',
            'route_action' => 'string', 'status_code' => 'int', 'failed' => 'bool', 'ip' => 'string', 'user_agent' => 'string',
            'os_user' => 'string', 'hostname' => 'string', 'duration_ms' => 'int', 'abilities' => 'json', 'models_accessed' => 'json',
            'mails' => 'json', 'notifications' => 'json', 'input' => 'json', 'properties' => 'json', 'tags' => 'json',
            'denied_abilities_count' => 'int', 'started_at' => 'timestamp', 'completed_at' => 'timestamp', 'created_at' => 'timestamp',
        ],
        'model_changes' => [
            'id' => 'int', 'ulid' => 'string', 'correlation_id' => 'string', 'auditable_type' => 'string', 'auditable_id' => 'string',
            'event' => 'string', 'old_values' => 'json', 'new_values' => 'json', 'user_type' => 'string', 'user_id' => 'string',
            'created_at' => 'timestamp',
        ],
    ]);
});

it('produces stable hashes (golden values)', function (): void {
    $hasher = new RowHasher('golden-key');

    $entry = [
        'id' => 1, 'ulid' => '01K6A0000000000000000000AA', 'correlation_id' => 'c-1', 'type' => 'http', 'name' => 'GET /posts',
        'user_type' => 'user', 'user_id' => '5', 'guard' => 'web', 'http_method' => 'GET', 'url' => '/posts',
        'route_action' => 'PostController@index', 'status_code' => 200, 'failed' => null, 'ip' => '127.0.0.0', 'user_agent' => 'curl',
        'os_user' => null, 'hostname' => null, 'duration_ms' => 12, 'abilities' => '[]', 'models_accessed' => '{"post":{"ids":["1"],"count":1}}',
        'mails' => null, 'notifications' => null, 'input' => null, 'properties' => '{"b":1,"a":2}', 'tags' => '["x"]',
        'denied_abilities_count' => 0, 'started_at' => '2026-09-29 10:00:00.123456', 'completed_at' => null, 'created_at' => '2026-09-29 10:00:00',
    ];

    $change = [
        'id' => 1, 'ulid' => '01K6A0000000000000000000BB', 'correlation_id' => 'c-1', 'auditable_type' => 'post', 'auditable_id' => '1',
        'event' => 'updated', 'old_values' => '{"title":"a"}', 'new_values' => '{"title":"b"}', 'user_type' => null, 'user_id' => null,
        'created_at' => '2026-09-29 10:00:00',
    ];

    expect($hasher->hash('entries', $entry, RowHasher::GENESIS))->toBe('9b174620b6259b7380825186083ea421bf9b1d27acdaab525c643d1e8ad93e0f')
        ->and($hasher->hash('model_changes', $change, str_repeat('a', 64)))->toBe('3774ff65ada9bc9b11d1916439e761a46367ce2efe9325793047d0e68500c728');
});

it('keeps null values null instead of casting them', function (): void {
    $canonical = RowHasher::canonical('entries', []);

    expect(array_filter($canonical, fn (mixed $value): bool => $value !== null))->toBe([]);
});

it('refuses a base64 key that does not decode', function (string $key): void {
    config(['auditor.integrity.key' => $key]);

    expect(fn (): RowHasher => RowHasher::fromConfig())->toThrow(InvalidArgumentException::class, 'not valid base64');
})->with(['base64:***', 'base64:', 'base64:cmF3*LWtleQ==']);
