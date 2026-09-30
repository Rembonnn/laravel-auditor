<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Data;

use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Immutable snapshot of one request, job or command. It only holds scalars
 * and arrays so it can be serialized onto a queue safely.
 */
final readonly class EntryData
{
    /**
     * Nested records keep the shapes of AbilityCheck / MailRecord /
     * NotificationRecord ::toArray(); they are typed loosely because they
     * round-trip through queues and JSON.
     *
     * @param  list<array<string, mixed>>  $abilities
     * @param  array<string, array<string, mixed>>  $modelsAccessed
     * @param  list<array<string, mixed>>  $mails
     * @param  list<array<string, mixed>>  $notifications
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $ulid,
        public string $correlationId,
        public EntryType $type,
        public ?string $name,
        public string $startedAt,
        public ?string $completedAt = null,
        public ?string $userType = null,
        public ?string $userId = null,
        public ?string $guard = null,
        public ?string $httpMethod = null,
        public ?string $url = null,
        public ?string $routeAction = null,
        public ?int $statusCode = null,
        public bool $failed = false,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $osUser = null,
        public ?string $hostname = null,
        public ?int $durationMs = null,
        public array $abilities = [],
        public array $modelsAccessed = [],
        public array $mails = [],
        public array $notifications = [],
        public ?array $input = null,
        public array $properties = [],
        public array $tags = [],
        public int $deniedAbilitiesCount = 0,
    ) {}

    public function isComplete(): bool
    {
        return $this->completedAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'correlation_id' => $this->correlationId,
            'type' => $this->type->value,
            'name' => $this->name,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'user_type' => $this->userType,
            'user_id' => $this->userId,
            'guard' => $this->guard,
            'http_method' => $this->httpMethod,
            'url' => $this->url,
            'route_action' => $this->routeAction,
            'status_code' => $this->statusCode,
            'failed' => $this->failed,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'os_user' => $this->osUser,
            'hostname' => $this->hostname,
            'duration_ms' => $this->durationMs,
            'abilities' => $this->abilities,
            'models_accessed' => $this->modelsAccessed,
            'mails' => $this->mails,
            'notifications' => $this->notifications,
            'input' => $this->input,
            'properties' => $this->properties,
            'tags' => $this->tags,
            'denied_abilities_count' => $this->deniedAbilitiesCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ulid: Values::string($data, 'ulid'),
            correlationId: Values::string($data, 'correlation_id'),
            type: EntryType::tryFrom(Values::string($data, 'type')) ?? EntryType::Other,
            name: Values::nullableString($data, 'name'),
            startedAt: Values::string($data, 'started_at'),
            completedAt: Values::nullableString($data, 'completed_at'),
            userType: Values::nullableString($data, 'user_type'),
            userId: Values::nullableString($data, 'user_id'),
            guard: Values::nullableString($data, 'guard'),
            httpMethod: Values::nullableString($data, 'http_method'),
            url: Values::nullableString($data, 'url'),
            routeAction: Values::nullableString($data, 'route_action'),
            statusCode: Values::nullableInt($data, 'status_code'),
            failed: Values::bool($data, 'failed'),
            ip: Values::nullableString($data, 'ip'),
            userAgent: Values::nullableString($data, 'user_agent'),
            osUser: Values::nullableString($data, 'os_user'),
            hostname: Values::nullableString($data, 'hostname'),
            durationMs: Values::nullableInt($data, 'duration_ms'),
            abilities: self::records($data, 'abilities'),
            modelsAccessed: array_map(
                fn (mixed $model): array => is_array($model) ? Values::stringKeys($model) : [],
                Values::map($data, 'models_accessed'),
            ),
            mails: self::records($data, 'mails'),
            notifications: self::records($data, 'notifications'),
            input: Values::nullableMap($data, 'input'),
            properties: Values::map($data, 'properties'),
            tags: Values::strings($data, 'tags'),
            deniedAbilitiesCount: Values::int($data, 'denied_abilities_count'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function records(array $data, string $key): array
    {
        $records = [];

        foreach (Values::list($data, $key) as $record) {
            if (is_array($record)) {
                $records[] = Values::stringKeys($record);
            }
        }

        return $records;
    }
}
