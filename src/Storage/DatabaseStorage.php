<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Stores entries and their model changes in one transaction, using the
 * query builder (no Eloquent events, no model hydration).
 */
final readonly class DatabaseStorage implements Storage
{
    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public function __construct(
        private ConnectionResolverInterface $db,
        private Config $config,
    ) {}

    public function connection(): ConnectionInterface
    {
        return $this->db->connection(Values::toString($this->config->get('auditor.storage.database.connection')));
    }

    public function table(string $name): string
    {
        return Values::toString($this->config->get("auditor.storage.database.tables.{$name}")) ?? "auditor_{$name}";
    }

    public function store(?EntryData $entry, array $changes = []): void
    {
        $connection = $this->connection();

        $connection->transaction(function () use ($connection, $entry, $changes): void {
            $entryId = $entry === null ? null : $this->upsertEntry($connection, $entry);

            if ($changes === []) {
                return;
            }

            $rows = array_map(fn (ModelChangeData $change): array => $this->changeRow(
                $change,
                $entry !== null && $change->entryUlid === $entry->ulid ? $entryId : null,
            ), $changes);

            foreach (array_chunk($rows, 100) as $chunk) {
                $connection->table($this->table('model_changes'))->insert($chunk);
            }

            if ($entryId !== null) {
                $connection->table($this->table('entries'))
                    ->where('id', $entryId)
                    ->increment('model_changes_count', count($changes));
            }
        });
    }

    private function upsertEntry(ConnectionInterface $connection, EntryData $entry): int
    {
        $table = $connection->table($this->table('entries'));
        $existing = (clone $table)->where('ulid', $entry->ulid)->first(['id', 'completed_at']);

        if ($existing === null) {
            return (int) $table->insertGetId($this->entryRow($entry) + [
                'ulid' => $entry->ulid,
                'model_changes_count' => 0,
                'created_at' => $entry->startedAt,
            ]);
        }

        // Never overwrite a complete entry with a partial one (queued jobs
        // may run out of order), and never touch a sealed row.
        if ($entry->isComplete() || $existing->completed_at === null) {
            (clone $table)->where('id', $existing->id)->whereNull('hash')->update($this->entryRow($entry));
        }

        return Values::int((array) $existing, 'id');
    }

    /**
     * @return array<string, mixed>
     */
    private function entryRow(EntryData $entry): array
    {
        return [
            'correlation_id' => $entry->correlationId,
            'type' => $entry->type->value,
            'name' => self::limit($entry->name, 255),
            'user_type' => $entry->userType,
            'user_id' => $entry->userId,
            'guard' => self::limit($entry->guard, 64),
            'http_method' => $entry->httpMethod,
            'url' => $entry->url,
            'route_action' => self::limit($entry->routeAction, 255),
            'status_code' => $entry->statusCode,
            'failed' => $entry->failed,
            'ip' => self::limit($entry->ip, 45),
            'user_agent' => self::limit($entry->userAgent, 512),
            'os_user' => self::limit($entry->osUser, 64),
            'hostname' => self::limit($entry->hostname, 255),
            'duration_ms' => $entry->durationMs,
            'abilities' => self::json($entry->abilities),
            'models_accessed' => self::json($entry->modelsAccessed),
            'mails' => self::json($entry->mails),
            'notifications' => self::json($entry->notifications),
            'input' => $entry->input === null ? null : json_encode($entry->input, self::JSON_FLAGS),
            'properties' => self::json($entry->properties),
            'tags' => self::json($entry->tags),
            'denied_abilities_count' => min($entry->deniedAbilitiesCount, 65535),
            'started_at' => $entry->startedAt,
            'completed_at' => $entry->completedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changeRow(ModelChangeData $change, ?int $entryId): array
    {
        return [
            'ulid' => $change->ulid,
            'entry_id' => $entryId,
            'correlation_id' => $change->correlationId,
            'auditable_type' => $change->auditableType,
            'auditable_id' => $change->auditableId,
            'event' => $change->event->value,
            'old_values' => $change->oldValues === null ? null : json_encode($change->oldValues, self::JSON_FLAGS),
            'new_values' => $change->newValues === null ? null : json_encode($change->newValues, self::JSON_FLAGS),
            'user_type' => $change->userType,
            'user_id' => $change->userId,
            'created_at' => $change->createdAt,
        ];
    }

    /**
     * Empty collections are stored as NULL to keep rows small.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function json(array $value): ?string
    {
        return $value === [] ? null : json_encode($value, self::JSON_FLAGS);
    }

    private static function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
