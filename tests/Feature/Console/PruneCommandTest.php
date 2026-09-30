<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    foreach (range(1, 4) as $i) {
        $this->post('/posts', ['title' => "Post {$i}"]);
    }

    $this->entryIds = DB::table('auditor_entries')->orderBy('id')->pluck('id')->all();
    $this->changeIds = DB::table('auditor_model_changes')->orderBy('id')->pluck('id')->all();

    DB::table('auditor_entries')->whereIn('id', array_slice($this->entryIds, 0, 2))->update(['created_at' => now()->subDays(91)]);
    DB::table('auditor_model_changes')->whereIn('id', array_slice($this->changeIds, 0, 2))->update(['created_at' => now()->subDays(91)]);
});

it('B10: deletes data older than keep_days', function (): void {
    $this->artisan('auditor:prune')->assertSuccessful();

    expect(DB::table('auditor_entries')->orderBy('id')->pluck('id')->all())->toBe(array_slice($this->entryIds, 2))
        ->and(DB::table('auditor_model_changes')->orderBy('id')->pluck('id')->all())->toBe(array_slice($this->changeIds, 2))
        ->and(DB::table('auditor_checkpoints')->count())->toBe(0); // integrity disabled
});

it('accepts --days', function (): void {
    $this->artisan('auditor:prune', ['--days' => 365])->assertSuccessful();

    expect(DB::table('auditor_entries')->count())->toBe(4);

    $this->artisan('auditor:prune', ['--days' => 0])->assertFailed();
});

it('only prunes sealed rows when integrity is enabled', function (): void {
    config(['auditor.integrity.enabled' => true]);

    $this->artisan('auditor:prune')->assertSuccessful();

    expect(DB::table('auditor_entries')->count())->toBe(4);
});

it('only removes a contiguous prefix', function (): void {
    DB::table('auditor_entries')->where('id', $this->entryIds[3])->update(['created_at' => now()->subDays(200)]);

    $this->artisan('auditor:prune');

    expect(DB::table('auditor_entries')->orderBy('id')->pluck('id')->all())->toBe(array_slice($this->entryIds, 2));
});
