<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Models\Concerns;

use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Connection and table names come from the auditor config.
 *
 * @internal
 */
trait UsesAuditorConnection
{
    public function getConnectionName(): ?string
    {
        return Settings::nullableString('auditor.storage.database.connection') ?? (is_string($this->connection) ? $this->connection : null);
    }

    public function getTable(): string
    {
        return Settings::string('auditor.storage.database.tables.'.static::TABLE_KEY, 'auditor_'.static::TABLE_KEY);
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * First id that must be kept when pruning rows older than the retention
     * window. Everything below it forms a contiguous, prunable prefix, so a
     * hash chain stays verifiable from the checkpoint written before.
     */
    public static function pruneBoundary(?int $days = null): int
    {
        $days ??= Settings::int('auditor.prune.keep_days', 90);
        $query = static::query();

        $boundary = (clone $query)
            ->where(function ($query) use ($days): void {
                $query->where('created_at', '>=', now()->subDays($days));

                if (config('auditor.integrity.enabled')) {
                    $query->orWhereNull('hash');
                }
            })
            ->min('id');

        $boundary = Values::toInt($boundary);

        return $boundary ?? (Values::toInt($query->max('id')) ?? 0) + 1;
    }
}
