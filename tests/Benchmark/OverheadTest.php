<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

/*
 * Overhead of the middleware + observers for a request that loads 100
 * models, compared with the same request with the auditor disabled.
 * Persisting is excluded (discarding storage), as in the plan's G3 target.
 * Not part of the default run: `pest --group=benchmark`.
 */
beforeEach(function (): void {
    Auditor::extend('discard', fn (): Storage => new class implements Storage
    {
        public function store(?EntryData $entry, array $changes = []): void {}
    });
    config(['auditor.storage.driver' => 'discard']);
});

it('adds less than 1 ms (median) to a request loading 100 models', function (): void {
    Auditor::withoutAuditing(function (): void {
        foreach (range(1, 100) as $i) {
            Post::query()->create(['title' => "p{$i}"]);
        }
    });

    $kernel = app(Kernel::class);

    $measure = function (bool $enabled) use ($kernel): float {
        config(['auditor.enabled' => $enabled]);
        $samples = [];

        foreach (range(1, 60) as $i) {
            $request = Request::create('/posts');
            $start = hrtime(true);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        sort($samples);

        return $samples[intdiv(count($samples), 2)];
    };

    $measure(true); // warm up
    $without = $measure(false);
    $with = $measure(true);
    $overhead = $with - $without;

    fwrite(STDERR, sprintf("\nmedian without: %.3f ms, with: %.3f ms, overhead: %.3f ms\n", $without, $with, $overhead));

    expect($overhead)->toBeLessThan(1.0);
})->group('benchmark');

it('keeps memory flat for a command with 10,000 updates (buffer_size)', function (): void {
    config(['auditor.buffer_size' => 100]);

    $post = Auditor::withoutAuditing(fn () => Post::query()->create(['title' => 'x']));
    $recorder = app(Recorder::class);
    $entry = $recorder->start(EntryType::Command, 'import');

    foreach (range(1, 1_000) as $i) {
        $post->update(['title' => "t{$i}"]);
    }
    gc_collect_cycles();
    $after1k = memory_get_usage();

    foreach (range(1, 9_000) as $i) {
        $post->update(['title' => "u{$i}"]);
    }
    gc_collect_cycles();
    $after10k = memory_get_usage();

    fwrite(STDERR, sprintf("\nmemory growth 1k->10k updates: %.2f MB\n", ($after10k - $after1k) / 1048576));

    expect($after10k - $after1k)->toBeLessThan(2 * 1024 * 1024)
        ->and(count($entry->changes))->toBeLessThanOrEqual(100);

    $recorder->finish($entry);
})->group('benchmark');
