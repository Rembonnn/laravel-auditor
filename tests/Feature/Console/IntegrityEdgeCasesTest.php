<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Integrity\RowHasher;
use Rembon\LaravelAuditor\Integrity\Sealer;
use Rembon\LaravelAuditor\Integrity\VerificationResult;
use Rembon\LaravelAuditor\Integrity\Verifier;
use Rembon\LaravelAuditor\Models\Checkpoint;

beforeEach(function (): void {
    config([
        'auditor.integrity.enabled' => true,
        'auditor.integrity.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'auditor.integrity.seal_delay' => 0,
    ]);

    foreach (range(1, 5) as $i) {
        $this->post('/posts', ['title' => "Post {$i}"])->assertCreated();
    }

    $this->travel(1)->seconds();

    $this->ids = DB::table('auditor_entries')->orderBy('id')->pluck('id')->all();
});

function sealer(?Repository $cache = null): Sealer
{
    return new Sealer(RowHasher::fromConfig(), $cache ?? app('cache.store'));
}

function verifier(): Verifier
{
    return new Verifier(RowHasher::fromConfig());
}

/**
 * Inserts model change rows directly (fast) that are old enough to be sealed.
 */
function insertChanges(int $count, string $id = 'bulk'): void
{
    $rows = [];

    foreach (range(1, $count) as $i) {
        $rows[] = [
            'ulid' => (string) Str::ulid(), 'correlation_id' => $id, 'auditable_type' => 'post',
            'auditable_id' => (string) $i, 'event' => 'created', 'created_at' => now()->subMinute()->format('Y-m-d H:i:s.u'),
        ];
    }

    foreach (array_chunk($rows, 200) as $chunk) {
        DB::table('auditor_model_changes')->insert($chunk);
    }
}

it('starts a verification result empty', function (): void {
    $result = new VerificationResult('entries');

    expect($result->checked)->toBe(0)
        ->and($result->unsealed)->toBe(0)
        ->and($result->valid())->toBeTrue()
        ->and($result->firstViolation())->toBeNull()
        ->and($result->startedAfterId)->toBeNull();
});

it('treats trailing unsealed rows as pending, not as a violation', function (): void {
    $result = verifier()->verify('entries');

    expect($result->valid())->toBeTrue()
        ->and($result->unsealed)->toBe(5)
        ->and($result->checked)->toBe(0);
});

it('flags sealed rows that follow an unsealed one', function (): void {
    sealer()->seal('entries');
    DB::table('auditor_entries')->where('id', $this->ids[1])->update(['hash' => null]);

    $result = verifier()->verify('entries');
    $reasons = collect($result->violations)->groupBy('reason')->map->pluck('id')->all();

    expect($result->unsealed)->toBe(1)
        ->and($result->checked)->toBe(4)
        ->and($result->firstViolation())->toMatchArray(['id' => $this->ids[2], 'reason' => 'sealed_after_unsealed'])
        ->and($reasons['sealed_after_unsealed']->all())->toBe(array_slice($this->ids, 2))
        ->and($reasons['missing_previous_rows']->all())->toBe([$this->ids[2]]);
});

it('reports a row whose previous hash was removed as tampered', function (): void {
    sealer()->seal('entries');
    DB::table('auditor_entries')->where('id', $this->ids[2])->update(['previous_hash' => null]);

    expect(verifier()->verify('entries')->violations)->toBe([[
        'id' => $this->ids[2],
        'reason' => 'tampered',
        'expected' => DB::table('auditor_entries')->where('id', $this->ids[1])->value('hash'),
        'actual' => null,
    ]]);
});

it('stops after 100 violations', function (): void {
    insertChanges(1_005); // more than one verification chunk
    sealer()->seal('model_changes');
    DB::table('auditor_model_changes')->update(['event' => 'forged']);

    expect(verifier()->verify('model_changes')->violations)->toHaveCount(100);
});

it('anchors --from on the closest sealed row before it', function (): void {
    sealer()->seal('entries');
    DB::table('auditor_entries')->where('id', $this->ids[1])->update(['hash' => null]);

    expect(verifier()->verify('entries', $this->ids[2])->startedAfterId)->toBe($this->ids[0]);
});

it('does not re-anchor --from that equals the checkpoint', function (): void {
    sealer()->seal('entries');
    Checkpoint::query()->create([
        'table' => 'entries',
        'last_id' => $this->ids[1],
        'last_hash' => DB::table('auditor_entries')->where('id', $this->ids[1])->value('hash'),
    ]);
    DB::table('auditor_entries')->whereIn('id', array_slice($this->ids, 0, 2))->delete();

    $result = verifier()->verify('entries', $this->ids[1]);

    expect($result->startedAfterId)->toBe($this->ids[1])
        ->and($result->valid())->toBeTrue();
});

it('seals every table and reports the counts', function (): void {
    expect(sealer()->sealAll())->toBe(['entries' => 5, 'model_changes' => 5]);
});

it('chains new rows onto the checkpoint when every sealed row was pruned', function (): void {
    sealer()->seal('entries');
    $last = DB::table('auditor_entries')->orderByDesc('id')->first();
    Checkpoint::query()->create(['table' => 'entries', 'last_id' => $last->id, 'last_hash' => $last->hash]);
    DB::table('auditor_entries')->delete();

    $this->post('/posts', ['title' => 'After prune'])->assertCreated();
    $this->travel(1)->seconds();
    sealer()->seal('entries');

    expect(DB::table('auditor_entries')->value('previous_hash'))->toBe($last->hash)
        ->and(verifier()->verify('entries')->valid())->toBeTrue();
});

it('continues with the next chunk until the limit is reached', function (): void {
    insertChanges(600);

    expect(sealer()->seal('model_changes', 502))->toBe(502)
        ->and(sealer()->seal('model_changes'))->toBe(103);
});

it('seals without a lock when the cache store cannot lock', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('getStore')->andReturn(Mockery::mock(Store::class));

    expect(sealer($cache)->seal('entries'))->toBe(5);
});

it('holds a ten minute lock per table while sealing', function (): void {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('get')->once()->andReturnTrue();
    $lock->shouldReceive('release')->once();

    $store = Mockery::mock(Store::class, LockProvider::class);
    $store->shouldReceive('lock')->once()->with('auditor:seal:entries', 600)->andReturn($lock);

    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('getStore')->andReturn($store);

    expect(sealer($cache)->seal('entries'))->toBe(5);
});

it('waits exactly the default seal delay', function (): void {
    config(['auditor.integrity' => ['enabled' => true, 'key' => config('auditor.integrity.key')]]);
    $now = Date::parse('2026-09-29 12:00:00');
    $created = $now->copy()->subSeconds(10)->format('Y-m-d H:i:s.u');
    DB::table('auditor_entries')->update(['created_at' => $created, 'started_at' => $created]);

    $this->travelTo($now->copy()->subSecond());
    expect(sealer()->seal('entries'))->toBe(0);

    $this->travelTo($now);
    expect(sealer()->seal('entries'))->toBe(5);
});

it('waits exactly the default stale period for entries that never completed', function (): void {
    config(['auditor.integrity' => ['enabled' => true, 'key' => config('auditor.integrity.key')]]);
    $now = Date::parse('2026-09-29 12:00:00');
    $started = $now->copy()->subMinutes(60)->format('Y-m-d H:i:s.u');
    DB::table('auditor_entries')->update(['created_at' => $started, 'started_at' => $started, 'completed_at' => null]);

    $this->travelTo($now->copy()->subSecond());
    expect(sealer()->seal('entries'))->toBe(0);

    $this->travelTo($now);
    expect(sealer()->seal('entries'))->toBe(5);
});
