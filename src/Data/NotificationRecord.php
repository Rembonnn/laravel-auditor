<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Data;

use Rembon\LaravelAuditor\Support\Values;

final readonly class NotificationRecord
{
    public function __construct(
        public string $notification,
        public string $channel,
        public ?string $notifiableType = null,
        public ?string $notifiableId = null,
    ) {}

    /**
     * @return array{notification: string, channel: string, notifiable_type: string|null, notifiable_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'notification' => $this->notification,
            'channel' => $this->channel,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'notification'),
            Values::string($data, 'channel'),
            Values::nullableString($data, 'notifiable_type'),
            Values::nullableString($data, 'notifiable_id'),
        );
    }
}
