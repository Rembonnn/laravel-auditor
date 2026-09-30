<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Data;

use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Support\Values;

final readonly class ModelChangeData
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public string $ulid,
        public ?string $entryUlid,
        public string $correlationId,
        public string $auditableType,
        public string $auditableId,
        public ChangeEvent $event,
        public ?array $oldValues,
        public ?array $newValues,
        public ?string $userType,
        public ?string $userId,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'entry_ulid' => $this->entryUlid,
            'correlation_id' => $this->correlationId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'event' => $this->event->value,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'user_type' => $this->userType,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ulid: Values::string($data, 'ulid'),
            entryUlid: Values::nullableString($data, 'entry_ulid'),
            correlationId: Values::string($data, 'correlation_id'),
            auditableType: Values::string($data, 'auditable_type'),
            auditableId: Values::string($data, 'auditable_id'),
            event: ChangeEvent::from(Values::string($data, 'event')),
            oldValues: Values::nullableMap($data, 'old_values'),
            newValues: Values::nullableMap($data, 'new_values'),
            userType: Values::nullableString($data, 'user_type'),
            userId: Values::nullableString($data, 'user_id'),
            createdAt: Values::string($data, 'created_at'),
        );
    }

    /**
     * Copy of this change detached from its entry (used when the entry
     * itself is ignored, filtered or sampled out).
     */
    public function withoutEntry(): self
    {
        return new self(
            $this->ulid, null, $this->correlationId, $this->auditableType, $this->auditableId,
            $this->event, $this->oldValues, $this->newValues, $this->userType, $this->userId, $this->createdAt,
        );
    }
}
