<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Storage\StorageManager;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

/**
 * Migrates rows from the v2 `audits` table. Raw emails are NOT imported.
 * Safe to run more than once: rows already imported are skipped.
 */
#[AsCommand(name: 'auditor:import-v2')]
final class ImportV2Command extends Command
{
    protected $signature = 'auditor:import-v2
        {--chunk=500 : Rows per chunk}
        {--user-model= : User model class of the v2 user_id column (default: App\Models\User)}
        {--connection= : Connection holding the v2 tables (default: the default connection)}
        {--drop-old : Drop the v2 audits and performances tables afterwards}';

    protected $description = 'Import Laravel Auditor v2 data (audits table) into the v3 tables';

    public function handle(StorageManager $storage): int
    {
        /** @var Connection $db */
        $db = $this->laravel->make('db')->connection($this->option('connection'));

        if (! $db->getSchemaBuilder()->hasTable('audits')) {
            $this->components->warn('No v2 "audits" table found. Nothing to import.');

            return self::SUCCESS;
        }

        $userType = $this->userMorphClass();
        $database = $storage->driver('database');
        $chunk = max(1, (int) $this->option('chunk'));
        $imported = 0;
        $skipped = 0;

        $db->table('audits')->orderBy('id')->chunkById($chunk, function (Collection $rows) use ($database, $userType, &$imported, &$skipped): void {
            /** @var Collection<int, stdClass> $rows */
            $correlationIds = $rows->map(fn (stdClass $row): string => self::correlationId(Values::int((array) $row, 'id')))->all();
            $existing = array_flip(array_filter(array_map(Values::toString(...), Entry::query()->whereIn('correlation_id', $correlationIds)->pluck('correlation_id')->all()), is_string(...)));

            foreach ($rows as $row) {
                if (isset($existing[self::correlationId(Values::int((array) $row, 'id'))])) {
                    $skipped++;

                    continue;
                }

                $database->store($this->toEntry($row, $userType));
                $imported++;
            }
        });

        $this->components->info("Imported {$imported} entries ({$skipped} already imported). v2 emails were not imported.");

        if ($this->option('drop-old')) {
            $this->dropOldTables($db);
        }

        return self::SUCCESS;
    }

    private function toEntry(stdClass $object, string $userType): EntryData
    {
        $row = (array) $object;
        $startedAt = Date::parse(Values::nullableString($row, 'datetime') ?? Values::nullableString($row, 'created_at') ?? 'now')->format('Y-m-d H:i:s.u');
        $abilities = self::decode($row['abilities'] ?? null);
        $abilityList = [];
        $denied = 0;

        foreach ($abilities as $ability => $result) {
            if (! is_string($ability)) {
                continue; // v2 bug B1 pushed e-mails in here with numeric keys
            }

            $abilityList[] = ['ability' => $ability, 'result' => $result === null ? null : (bool) $result, 'arguments' => []];
            $denied += $result === true ? 0 : 1;
        }

        $notifications = array_values(array_map(fn (array $n): array => [
            'notification' => Values::string($n, 'notification', 'unknown'),
            'channel' => Values::string($n, 'channel', 'unknown'),
            'notifiable_type' => null,
            'notifiable_id' => null,
        ], array_filter(self::decode($row['notifications'] ?? null), is_array(...))));

        $route = Values::nullableString($row, 'route');
        $userId = Values::nullableString($row, 'user_id');
        $requestTime = $row['request_time'] ?? null;

        return new EntryData(
            ulid: (string) Str::ulid(Date::parse($startedAt)),
            correlationId: self::correlationId(Values::int($row, 'id')),
            type: EntryType::Http,
            name: $route === 'unknown' ? null : $route,
            startedAt: $startedAt,
            completedAt: $startedAt,
            userType: $userId === null ? null : $userType,
            userId: $userId,
            url: Values::nullableString($row, 'url'),
            durationMs: is_numeric($requestTime) ? (int) round(((float) $requestTime) * 1000) : null,
            abilities: $abilityList,
            modelsAccessed: self::models(self::decode($row['models'] ?? null)),
            notifications: $notifications,
            properties: Values::stringKeys(self::decode($row['properties'] ?? null)),
            tags: ['imported-v2'],
            deniedAbilitiesCount: $denied,
        );
    }

    /**
     * v2 stored nested collections of full models; keep class => ids only.
     *
     * @param  array<array-key, mixed>  $models
     * @return array<string, array{ids: list<string>, count: int}>
     */
    private static function models(array $models): array
    {
        $result = [];
        $max = Settings::int('auditor.models.max_ids_per_model', 50);

        foreach ($models as $class => $items) {
            if (! is_string($class) || ! is_array($items)) {
                continue;
            }

            $ids = [];

            array_walk_recursive($items, function (mixed $value, string|int $key) use (&$ids): void {
                if ($key === 'id' && is_scalar($value)) {
                    $ids[(string) $value] = true;
                }
            });

            $result[$class] = ['ids' => array_slice(array_map(strval(...), array_keys($ids)), 0, $max), 'count' => count($ids)];
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function correlationId(int $id): string
    {
        return 'v2-'.$id;
    }

    private function userMorphClass(): string
    {
        $class = (string) ($this->option('user-model') ?: 'App\\Models\\User');

        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            return (new $class)->getMorphClass();
        }

        return $class;
    }

    private function dropOldTables(Connection $db): void
    {
        if ($this->input->isInteractive() && ! confirm('Drop the v2 "audits" and "performances" tables? This cannot be undone.', default: false)) {
            return;
        }

        foreach (['audits', 'performances'] as $table) {
            $db->getSchemaBuilder()->dropIfExists($table);
        }

        $this->components->info('Dropped the v2 tables.');
    }
}
