<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Console\SealCommand;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Dashboard\TimeBuckets;
use Rembon\LaravelAuditor\Support\Dashboard\UserDirectory;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

final class OverviewController
{
    public function __invoke(Request $request, UserDirectory $users, Cache $cache): View
    {
        $range = (string) $request->query('range', '24h');
        $buckets = TimeBuckets::for($range);
        [$previousFrom, $previousTo] = $buckets->previous();

        $stats = [
            'entries' => $this->stat(Entry::query(), $buckets, $previousFrom, $previousTo, 'entries.index', []),
            'changes' => $this->stat(ModelChange::query(), $buckets, $previousFrom, $previousTo, 'changes.index', []),
            'denied' => $this->stat(Entry::query()->where('denied_abilities_count', '>', 0), $buckets, $previousFrom, $previousTo, 'entries.index', ['denied' => '1']),
            'failed' => $this->stat(Entry::query()->where('failed', true), $buckets, $previousFrom, $previousTo, 'entries.index', ['failed' => '1']),
        ];

        $recent = Entry::query()->latest('id')->limit(8)->get();
        $denied = Entry::query()->where('denied_abilities_count', '>', 0)->latest('id')->limit(8)->get();

        $activeUsers = Entry::query()
            ->selectRaw('user_type, user_id, count(*) as aggregate')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $buckets->from)
            ->groupBy('user_type', 'user_id')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get();

        $changedModels = ModelChange::query()
            ->selectRaw('auditable_type, count(*) as aggregate')
            ->where('created_at', '>=', $buckets->from)
            ->groupBy('auditable_type')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get();

        $users->load($recent->concat($denied)->concat($activeUsers));

        return view('auditor::overview', [
            'range' => $buckets->range,
            'buckets' => $buckets,
            'stats' => $stats,
            'activity' => $this->activity($buckets),
            'recent' => $recent,
            'denied' => $denied,
            'activeUsers' => $activeUsers,
            'changedModels' => $changedModels,
            'users' => $users,
            'checklist' => $this->checklist($cache),
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $filter
     * @return array{total: int, previous: int, delta: float|null, series: list<int>, url: string}
     */
    private function stat(Builder $query, TimeBuckets $buckets, mixed $previousFrom, mixed $previousTo, string $route, array $filter): array
    {
        $series = $this->series(clone $query, $buckets);
        $total = array_sum($series);
        $previous = (clone $query)->where('created_at', '>=', $previousFrom)->where('created_at', '<', $previousTo)->count();

        return [
            'total' => $total,
            'previous' => $previous,
            'delta' => $previous === 0 ? null : round(($total - $previous) / $previous * 100),
            'series' => $series,
            'url' => route('auditor.'.$route, $filter + ['range' => $buckets->range === '1h' ? '1h' : $buckets->range]),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return list<int>
     */
    private function series(Builder $query, TimeBuckets $buckets): array
    {
        $series = array_fill(0, $buckets->count, 0);
        $expression = $buckets->expression($query->getModel()->getConnection());

        $bucket = new Expression($expression);

        $rows = $query->toBase()
            ->select([new Expression($expression.' as bucket'), new Expression('count(*) as aggregate')])
            ->where('created_at', '>=', $buckets->from)
            ->where('created_at', '<', $buckets->to)
            ->groupBy($bucket)
            ->get();

        foreach ($rows as $row) {
            $data = (array) $row;
            $index = $buckets->index(Values::string($data, 'bucket'));

            if ($index !== null) {
                $series[$index] += Values::int($data, 'aggregate');
            }
        }

        return array_values($series);
    }

    /**
     * Stacked counts per entry type.
     *
     * @return array<string, list<int>>
     */
    private function activity(TimeBuckets $buckets): array
    {
        $activity = [];

        foreach ([EntryType::Http, EntryType::Job, EntryType::Command] as $type) {
            $activity[$type->value] = $this->series(Entry::query()->where('type', $type->value), $buckets);
        }

        return $activity;
    }

    /**
     * Things that still need attention; the card hides when all are done.
     *
     * @return list<array{key: string, done: bool}>
     */
    private function checklist(Cache $cache): array
    {
        $items = [
            ['key' => 'migrated', 'done' => true],
            ['key' => 'middleware', 'done' => Entry::query()->where('type', EntryType::Http->value)->where('created_at', '>=', now()->subDay())->exists()],
            ['key' => 'auditable', 'done' => ModelChange::query()->exists()],
            ['key' => 'gate', 'done' => Gate::has('viewAuditor')],
        ];

        if (config('auditor.integrity.enabled')) {
            $items[] = ['key' => 'sealed', 'done' => $cache->has(SealCommand::CACHE_KEY)];
        }

        if (config('auditor.queue.enabled')) {
            $items[] = ['key' => 'queue', 'done' => ! $this->hasFailedPersistJobs()];
        }

        return $items;
    }

    private function hasFailedPersistJobs(): bool
    {
        try {
            $table = Settings::string('queue.failed.table', 'failed_jobs');

            return Schema::hasTable($table)
                && DB::table($table)->where('payload', 'like', '%PersistEntry%')->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
