<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Events\IntegrityViolationDetected;
use Rembon\LaravelAuditor\Integrity\RowHasher;
use Rembon\LaravelAuditor\Integrity\Sealer;
use Rembon\LaravelAuditor\Integrity\VerificationResult;
use Rembon\LaravelAuditor\Integrity\Verifier;
use Rembon\LaravelAuditor\Models\Checkpoint;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

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
    $this->changeIds = DB::table('auditor_model_changes')->orderBy('id')->pluck('id')->all();
});

function verify(string $table = 'entries'): VerificationResult
{
    return (new Verifier(RowHasher::fromConfig()))->verify($table);
}

it('seals every completed row and verifies the chain', function (): void {
    $this->artisan('auditor:seal')->assertSuccessful();

    expect(DB::table('auditor_entries')->whereNull('hash')->count())->toBe(0)
        ->and(DB::table('auditor_model_changes')->whereNull('hash')->count())->toBe(0)
        ->and(DB::table('auditor_entries')->orderBy('id')->first()->previous_hash)->toBe(RowHasher::GENESIS);

    $this->artisan('auditor:verify')->assertSuccessful();

    expect(verify())->valid()->toBeTrue()->checked->toBe(5)
        ->and(verify('model_changes')->valid())->toBeTrue();
});

it('detects a tampered column and points at the right row', function (): void {
    $this->artisan('auditor:seal');
    Event::fake([IntegrityViolationDetected::class]);

    DB::table('auditor_entries')->where('id', $this->ids[2])->update(['status_code' => 200]);

    $this->artisan('auditor:verify')->assertFailed();

    $result = verify();

    expect($result->valid())->toBeFalse()
        ->and($result->firstViolation())->toMatchArray(['id' => $this->ids[2], 'reason' => 'tampered'])
        ->and($result->violations)->toHaveCount(1);

    Event::assertDispatched(IntegrityViolationDetected::class, fn ($e): bool => $e->table === 'entries' && $e->id === $this->ids[2]);
});

it('detects tampered JSON values', function (): void {
    $this->artisan('auditor:seal');

    DB::table('auditor_model_changes')->where('id', $this->changeIds[1])->update(['new_values' => json_encode(['title' => 'Forged'])]);

    expect(verify('model_changes')->firstViolation())->toMatchArray(['id' => $this->changeIds[1], 'reason' => 'tampered']);
});

it('detects a deleted row in the middle', function (): void {
    $this->artisan('auditor:seal');

    DB::table('auditor_entries')->where('id', $this->ids[2])->delete();

    expect(verify()->firstViolation())->toMatchArray(['id' => $this->ids[3], 'reason' => 'missing_previous_rows']);
});

it('still verifies after pruning, thanks to the checkpoint', function (): void {
    $this->artisan('auditor:seal');
    DB::table('auditor_entries')->whereIn('id', array_slice($this->ids, 0, 2))->update(['created_at' => now()->subDays(100)]);
    DB::table('auditor_model_changes')->whereIn('id', array_slice($this->changeIds, 0, 2))->update(['created_at' => now()->subDays(100)]);

    // created_at is part of the hash: re-seal a fresh chain for this scenario.
    DB::table('auditor_entries')->update(['hash' => null, 'previous_hash' => null]);
    DB::table('auditor_model_changes')->update(['hash' => null, 'previous_hash' => null]);
    $this->artisan('auditor:seal');

    $this->artisan('auditor:prune')->assertSuccessful();

    expect(DB::table('auditor_entries')->count())->toBe(3)
        ->and(DB::table('auditor_checkpoints')->where('table', 'entries')->value('last_id'))->toBe($this->ids[1])
        ->and(verify()->valid())->toBeTrue()
        ->and(verify('model_changes')->valid())->toBeTrue();

    $this->artisan('auditor:verify')->assertSuccessful();
});

it('only seals entries that completed, and waits for them in order', function (): void {
    $recorder = app(Recorder::class);
    config(['auditor.buffer_size' => 1]);
    $running = $recorder->start(EntryType::Command, 'long:task');
    Post::query()->create(['title' => 'during']); // flushes a partial entry row

    $this->post('/posts', ['title' => 'after'])->assertCreated();
    $this->travel(1)->seconds();

    $this->artisan('auditor:seal');

    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(5)
        ->and(DB::table('auditor_entries')->whereNull('hash')->count())->toBe(2);

    $recorder->finish($running);
    $this->artisan('auditor:seal');

    expect(DB::table('auditor_entries')->whereNull('hash')->count())->toBe(0)
        ->and(verify()->valid())->toBeTrue();
});

it('never updates a sealed entry', function (): void {
    $this->artisan('auditor:seal');
    $row = DB::table('auditor_entries')->first();

    app(Auditor::class)->storeNow(new EntryData(
        ulid: $row->ulid, correlationId: 'forged', type: EntryType::Http,
        name: 'forged', startedAt: $row->started_at, completedAt: $row->completed_at,
    ));

    expect(DB::table('auditor_entries')->where('id', $row->id)->value('name'))->toBe('posts.store')
        ->and(verify()->valid())->toBeTrue();
});

it('does not seal rows younger than the seal delay', function (): void {
    config(['auditor.integrity.seal_delay' => 3600]);

    $this->artisan('auditor:seal');

    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(0);
});

it('does not fork the chain when two sealers run', function (): void {
    $sealer = new Sealer(RowHasher::fromConfig(), app('cache.store'));
    $lock = app('cache.store')->getStore()->lock('auditor:seal:entries', 60);
    $lock->get();

    expect($sealer->seal('entries'))->toBe(0); // the other sealer holds the lock

    $lock->release();

    expect($sealer->seal('entries'))->toBe(5)->and($sealer->seal('entries'))->toBe(0)
        ->and(verify()->valid())->toBeTrue();
});

it('respects --limit', function (): void {
    $this->artisan('auditor:seal', ['--limit' => 2]);

    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(2);

    $this->artisan('auditor:seal');

    expect(verify()->valid())->toBeTrue();
});

it('explains a missing key instead of crashing', function (): void {
    config(['auditor.integrity.key' => null]);

    $this->artisan('auditor:seal')->expectsOutputToContain('AUDITOR_INTEGRITY_KEY')->assertFailed();
    $this->artisan('auditor:verify')->expectsOutputToContain('AUDITOR_INTEGRITY_KEY')->assertFailed();
});

it('does nothing when integrity is disabled', function (): void {
    config(['auditor.integrity.enabled' => false]);

    $this->artisan('auditor:seal')->assertSuccessful();
    $this->artisan('auditor:verify')->assertSuccessful();

    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(0);
});

it('rejects an unknown table', function (): void {
    $this->artisan('auditor:verify', ['--table' => 'users'])->assertFailed();
});

it('verifies from a given id', function (): void {
    $this->artisan('auditor:seal');
    DB::table('auditor_entries')->where('id', $this->ids[0])->update(['status_code' => 418]);

    expect(verify()->valid())->toBeFalse()
        ->and((new Verifier(RowHasher::fromConfig()))->verify('entries', $this->ids[2])->valid())->toBeTrue();
});

it('seals an entry that never completed once it is stale', function (): void {
    config(['auditor.buffer_size' => 1, 'auditor.integrity.stale_after' => 30]);
    $recorder = app(Recorder::class);
    $recorder->start(EntryType::Command, 'crashed');
    Post::query()->create(['title' => 'during']); // flushes a partial entry

    $this->travel(5)->minutes();
    $this->artisan('auditor:seal');
    expect(DB::table('auditor_entries')->whereNull('hash')->count())->toBe(1);

    $this->travel(31)->minutes();
    $this->artisan('auditor:seal');
    expect(DB::table('auditor_entries')->whereNull('hash')->count())->toBe(0)
        ->and(verify()->valid())->toBeTrue();
});

it('uses safe defaults when the integrity timing keys are missing', function (): void {
    config(['auditor.integrity' => ['enabled' => true, 'key' => config('auditor.integrity.key')]]);

    $this->artisan('auditor:seal');
    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(0); // 10 s default delay

    $this->travel(11)->seconds();
    $this->artisan('auditor:seal');
    expect(DB::table('auditor_entries')->whereNotNull('hash')->count())->toBe(5);
});

it('reports verification results as arrays', function (): void {
    $this->artisan('auditor:seal');
    DB::table('auditor_entries')->where('id', $this->ids[1])->update(['name' => 'forged']);

    $result = verify();

    expect($result->toArray())->toMatchArray([
        'table' => 'entries',
        'valid' => false,
        'checked' => 5,
        'unsealed' => 0,
        'started_after_id' => null,
    ])->and($result->toArray()['violations'][0])->toMatchArray(['id' => $this->ids[1], 'reason' => 'tampered'])
        ->and($result->firstViolation()['actual'])->toBe(DB::table('auditor_entries')->where('id', $this->ids[1])->value('hash'));
});

it('starts --from at the genesis when nothing before it is sealed', function (): void {
    $this->artisan('auditor:seal');

    $result = (new Verifier(RowHasher::fromConfig()))->verify('entries', $this->ids[0]);

    expect($result->valid())->toBeTrue()
        ->and($result->checked)->toBe(5)
        ->and($result->startedAfterId)->toBe($this->ids[0] - 1);
});

it('ignores --from at or before the last checkpoint', function (): void {
    $this->artisan('auditor:seal');
    Checkpoint::query()->create([
        'table' => 'entries',
        'last_id' => $this->ids[1],
        'last_hash' => DB::table('auditor_entries')->where('id', $this->ids[1])->value('hash'),
    ]);
    DB::table('auditor_entries')->whereIn('id', array_slice($this->ids, 0, 2))->delete();

    $result = (new Verifier(RowHasher::fromConfig()))->verify('entries', $this->ids[0]);

    expect($result->valid())->toBeTrue()
        ->and($result->checked)->toBe(3)
        ->and($result->startedAfterId)->toBe($this->ids[1]);
});

it('verifies across chunks', function (): void {
    foreach (range(1, 1_005) as $i) {
        DB::table('auditor_model_changes')->insert([
            'ulid' => (string) Str::ulid(), 'correlation_id' => 'bulk', 'auditable_type' => 'post',
            'auditable_id' => (string) $i, 'event' => 'created', 'created_at' => now()->subMinute()->format('Y-m-d H:i:s.u'),
        ]);
    }

    $this->artisan('auditor:seal');
    $last = DB::table('auditor_model_changes')->max('id');
    DB::table('auditor_model_changes')->where('id', $last)->update(['auditable_id' => 'forged']);

    $result = verify('model_changes');

    expect($result->checked)->toBe(1_010)
        ->and($result->firstViolation()['id'])->toBe($last);
});
