<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Integrity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Rembon\LaravelAuditor\Support\CanonicalJson;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * hash = HMAC-SHA256(key, previous_hash . canonical_json(row))
 *
 * Rows are normalised per column type first, so the hash does not depend on
 * how a database driver returns values (1 vs "1", t vs true, JSON key order).
 *
 * Not covered by the hash (they legitimately change after sealing):
 *  - entries.model_changes_count (denormalised counter)
 *  - model_changes.entry_id (set to NULL when an entry is pruned)
 */
final readonly class RowHasher
{
    public const string GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Hashed columns and how their values are normalised.
     *
     * @return array<string, array<string, 'int'|'string'|'bool'|'json'|'timestamp'>>
     */
    public static function columns(): array
    {
        return [
            'entries' => [
                'id' => 'int',
                'ulid' => 'string',
                'correlation_id' => 'string',
                'type' => 'string',
                'name' => 'string',
                'user_type' => 'string',
                'user_id' => 'string',
                'guard' => 'string',
                'http_method' => 'string',
                'url' => 'string',
                'route_action' => 'string',
                'status_code' => 'int',
                'failed' => 'bool',
                'ip' => 'string',
                'user_agent' => 'string',
                'os_user' => 'string',
                'hostname' => 'string',
                'duration_ms' => 'int',
                'abilities' => 'json',
                'models_accessed' => 'json',
                'mails' => 'json',
                'notifications' => 'json',
                'input' => 'json',
                'properties' => 'json',
                'tags' => 'json',
                'denied_abilities_count' => 'int',
                'started_at' => 'timestamp',
                'completed_at' => 'timestamp',
                'created_at' => 'timestamp',
            ],
            'model_changes' => [
                'id' => 'int',
                'ulid' => 'string',
                'correlation_id' => 'string',
                'auditable_type' => 'string',
                'auditable_id' => 'string',
                'event' => 'string',
                'old_values' => 'json',
                'new_values' => 'json',
                'user_type' => 'string',
                'user_id' => 'string',
                'created_at' => 'timestamp',
            ],
        ];
    }

    public function __construct(#[\SensitiveParameter] private string $key) {}

    /**
     * @throws InvalidArgumentException when no key is configured
     */
    public static function fromConfig(): self
    {
        $key = Settings::string('auditor.integrity.key');

        if ($key === '') {
            throw new InvalidArgumentException(
                'AUDITOR_INTEGRITY_KEY is not set. Run `php artisan auditor:install --integrity` to generate one.'
            );
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'), true);

            if ($key === false || $key === '') {
                throw new InvalidArgumentException('AUDITOR_INTEGRITY_KEY starts with "base64:" but is not valid base64.');
            }
        }

        return new self($key);
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return array_keys(self::columns());
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    public function hash(string $table, array|object $row, string $previousHash): string
    {
        return hash_hmac('sha256', $previousHash.CanonicalJson::encode(self::canonical($table, Values::stringKeys((array) $row))), $this->key);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function canonical(string $table, array $row): array
    {
        $columns = self::columns()[$table] ?? throw new InvalidArgumentException("Unknown auditor table [{$table}].");
        $canonical = [];

        foreach ($columns as $column => $type) {
            $canonical[$column] = self::normalize($row[$column] ?? null, $type);
        }

        return $canonical;
    }

    private static function normalize(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => Values::toInt($value),
            'bool' => in_array($value, [true, 1, '1', 't', 'true'], true),
            'json' => is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value,
            'timestamp' => Carbon::parse($value instanceof \DateTimeInterface ? $value : Values::toString($value), Settings::string('app.timezone', 'UTC')) // @pest-mutate-ignore InstanceOfToTrue (Carbon parses driver strings too)
                ->utc()
                ->format('Y-m-d\TH:i:s.u\Z'),
            default => Values::toString($value),
        };
    }
}
