<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Integrity;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Rembon\LaravelAuditor\Models\Checkpoint;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Seals unsealed rows in id order, chaining each row's hash to the previous
 * one. It runs outside the request cycle (auditor:seal) so recording never
 * needs a global lock.
 */
final readonly class Sealer
{
    private const int CHUNK = 500; // @pest-mutate-ignore (declaration lines carry no coverage; chunking is asserted in IntegrityEdgeCasesTest)

    public function __construct(
        private RowHasher $hasher,
        private Cache $cache,
    ) {}

    /**
     * @return array<string, int> rows sealed per table
     */
    public function sealAll(?int $limit = null): array
    {
        $sealed = [];

        foreach (RowHasher::tables() as $table) {
            $sealed[$table] = $this->seal($table, $limit);
        }

        return $sealed;
    }

    public function seal(string $table, ?int $limit = null): int
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $this->sealUnlocked($table, $limit);
        }

        $lock = $store->lock('auditor:seal:'.$table, 600);

        if (! $lock->get()) {
            return 0; // another sealer is running for this table
        }

        try {
            return $this->sealUnlocked($table, $limit);
        } finally {
            $lock->release();
        }
    }

    private function sealUnlocked(string $table, ?int $limit): int
    {
        $model = self::model($table);
        $connection = $model->getConnection();
        $sealed = 0;
        $done = false;

        $delay = Date::now()->subSeconds(Settings::int('auditor.integrity.seal_delay', 10));
        $stale = Date::now()->subMinutes(Settings::int('auditor.integrity.stale_after', 60));

        while (! $done && ($limit === null || $sealed < $limit)) {
            $done = $connection->transaction(function () use ($model, $connection, $table, $limit, $delay, $stale, &$sealed): bool {
                $query = fn (): Builder => $connection->table($model->getTable());
                $previous = $this->previousHash($table, $query);

                $take = $limit === null ? self::CHUNK : min(self::CHUNK, $limit - $sealed);
                $rows = $query()->whereNull('hash')->orderBy('id')->limit($take)->lockForUpdate()->get();

                foreach ($rows as $row) {
                    $data = (array) $row;

                    if (Date::parse(Values::string($data, 'created_at')) > $delay) {
                        return true; // too recent: wait so late commits keep id order
                    }

                    if ($table === 'entries' && $row->completed_at === null && Date::parse(Values::string($data, 'started_at')) > $stale) {
                        return true; // still running: the chain waits for it
                    }

                    $hash = $this->hasher->hash($table, $row, $previous);

                    $query()->where('id', $row->id)->whereNull('hash')->update([
                        'previous_hash' => $previous,
                        'hash' => $hash,
                    ]);

                    $previous = $hash;
                    $sealed++;
                }

                return $rows->count() < $take;
            });
        }

        return $sealed;
    }

    /**
     * @param  \Closure(): Builder  $query
     */
    private function previousHash(string $table, \Closure $query): string
    {
        $last = Values::toString($query()->whereNotNull('hash')->orderByDesc('id')->value('hash'));

        return $last ?? Checkpoint::latestFor($table)->last_hash ?? RowHasher::GENESIS;
    }

    public static function model(string $table): Model
    {
        return match ($table) {
            'entries' => new Entry,
            'model_changes' => new ModelChange,
            default => throw new \InvalidArgumentException("Unknown auditor table [{$table}]."),
        };
    }
}
