<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\Dashboard\Diff;
use Rembon\LaravelAuditor\Support\Dashboard\Icons;
use Rembon\LaravelAuditor\Support\Dashboard\ModelRef;
use Rembon\LaravelAuditor\Support\Dashboard\Present;
use Rembon\LaravelAuditor\Support\Dashboard\TimeBuckets;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

it('diffs attributes, nested JSON and redacted values', function (): void {
    $rows = Diff::rows(
        ['title' => 'a', 'meta' => ['lang' => 'en', 'x' => 1, 'y' => 2], 'gone' => 1, 'password' => '[REDACTED]', 'same' => 1],
        ['title' => 'b', 'meta' => ['lang' => 'id', 'x' => 1, 'y' => 2], 'new' => 2, 'password' => '[REDACTED]', 'same' => 1],
    );
    $byKey = collect($rows)->keyBy('key');

    expect($byKey['title']['status'])->toBe('changed')
        ->and($byKey['new']['status'])->toBe('added')
        ->and($byKey['gone']['status'])->toBe('removed')
        ->and($byKey['password']['status'])->toBe('redacted')
        ->and($byKey['same']['status'])->toBe('unchanged')
        ->and($byKey['meta']['children'])->toHaveCount(1)
        ->and($byKey['meta']['children'][0]['key'])->toBe('lang')
        ->and($byKey['meta']['unchanged'])->toBe(2)
        ->and(Diff::rows(null, ['a' => 1])[0]['status'])->toBe('added')
        ->and(Diff::keys(['a' => 1, 'b' => 2]))->toBe(['a', 'b']);
});

it('presents tones, icons, durations and values', function (): void {
    expect(Present::methodTone('post'))->toBe('success')
        ->and(Present::methodTone('PATCH'))->toBe('warning')
        ->and(Present::methodTone('DELETE'))->toBe('danger')
        ->and(Present::methodTone(null))->toBe('neutral')
        ->and(Present::statusTone(503))->toBe('danger')
        ->and(Present::statusTone(200, failed: true))->toBe('danger')
        ->and(Present::statusTone(404))->toBe('warning')
        ->and(Present::statusTone(302))->toBe('neutral')
        ->and(Present::statusTone(201))->toBe('success')
        ->and(Present::duration(null))->toBe('—')
        ->and(Present::duration(12))->toBe('12 ms')
        ->and(Present::duration(1500))->toBe('1.5 s')
        ->and(Present::duration(252_000))->toBe('4m 12s')
        ->and(Present::shortId('01J9Z0000000000000000000AA'))->toBe('01J9Z0…0000AA')
        ->and(Present::shortId('short'))->toBe('short')
        ->and(Present::initials('Budi Santoso'))->toBe('BS')
        ->and(Present::initials('sari@example.com'))->toBe('SE')
        ->and(Present::initials(null))->toBe('#')
        ->and(Present::number(1204332))->toBe('1.204.332')
        ->and(Present::absolute(null))->toBe('—')
        ->and(Present::value(null)['kind'])->toBe('null')
        ->and(Present::value(false)['text'])->toBe('false')
        ->and(Present::value('')['kind'])->toBe('empty')
        ->and(Present::value('[REDACTED]')['kind'])->toBe('redacted')
        ->and(Present::value(1.5)['kind'])->toBe('number')
        ->and(Present::value(['a' => 1])['kind'])->toBe('json')
        ->and(Present::value('x')['kind'])->toBe('string');

    foreach (ChangeEvent::cases() as $event) {
        expect(Icons::has(Present::eventIcon($event)))->toBeTrue()->and(Present::eventTone($event))->toBeString();
    }

    foreach (EntryType::cases() as $type) {
        expect(Icons::has(Present::typeIcon($type)))->toBeTrue()->and(Present::typeTone($type))->toBeString();
    }
});

it('renders icons as decorative or labelled SVG', function (): void {
    expect((string) Icons::svg('check'))->toContain('aria-hidden="true"')
        ->and((string) Icons::svg('check', 'size-3', 'Done <b>'))->toContain('aria-label="Done &lt;b&gt;"')
        ->and((string) Icons::svg('does-not-exist'))->toContain('<circle')
        ->and(Icons::names())->toContain('shield-check');
});

it('encodes model types for URLs and only resolves real models', function (): void {
    expect(ModelRef::encode('post'))->toBe('post')
        ->and(ModelRef::decode(ModelRef::encode(Post::class)))->toBe(Post::class)
        ->and(ModelRef::decode('b64.!!!'))->toBe('b64.!!!')
        ->and(ModelRef::modelClass(Post::class))->toBe(Post::class)
        ->and(ModelRef::modelClass(stdClass::class))->toBeNull()
        ->and(ModelRef::modelClass('Nope\\Missing'))->toBeNull()
        ->and(ModelRef::find('Nope\\Missing', '1'))->toBeNull()
        ->and(ModelRef::title(null))->toBeNull();

    $post = Post::query()->create(['title' => 'Hello']);
    $post->delete();

    expect(ModelRef::find(Post::class, (string) $post->id)?->id)->toBe($post->id)
        ->and(ModelRef::title($post))->toBe('Hello');
});

it('buckets time ranges', function (string $range, int $count, string $unit): void {
    Date::setTestNow('2026-09-29 10:17:30');
    $buckets = TimeBuckets::for($range);

    expect($buckets->count)->toBe($count)
        ->and($buckets->unit)->toBe($unit)
        ->and($buckets->starts())->toHaveCount($count)
        ->and($buckets->to > Date::now())->toBeTrue()
        ->and($buckets->previous()[1]->eq($buckets->from))->toBeTrue();

    Date::setTestNow();
})->with([['1h', 12, 'minute'], ['24h', 24, 'hour'], ['7d', 7, 'day'], ['30d', 30, 'day'], ['nope', 24, 'hour']]);

it('maps SQL keys to bucket indexes', function (): void {
    Date::setTestNow('2026-09-29 10:17:30');

    $hours = TimeBuckets::for('24h');
    $minutes = TimeBuckets::for('1h');

    expect($hours->index('2026-09-29 10'))->toBe(23)
        ->and($hours->index('2026-09-28 11'))->toBe(0)
        ->and($hours->index('2026-09-27 10'))->toBeNull()
        ->and($minutes->index('2026-09-29 10:17'))->toBe(11)
        ->and($minutes->index('2026-09-29 10:10'))->toBe(10)
        ->and(TimeBuckets::for('7d')->labelFormat())->toBe('d M')
        ->and($hours->labelFormat())->toBe('H:i');

    foreach (['sqlite', 'mysql', 'pgsql', 'sqlsrv'] as $driver) {
        $connection = Mockery::mock(Connection::class, ['getDriverName' => $driver]);

        foreach (['1h', '24h', '7d'] as $range) {
            expect(TimeBuckets::for($range)->expression($connection))->toContain('created_at');
        }
    }

    Date::setTestNow();
});
