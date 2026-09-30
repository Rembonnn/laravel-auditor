<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

use Rembon\LaravelAuditor\Data\AbilityCheck;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\MailRecord;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Data\NotificationRecord;
use Rembon\LaravelAuditor\Enums\EntryType;

/**
 * Mutable state of one lifecycle (request, job or command) while it runs.
 *
 * @internal
 */
final class PendingEntry
{
    /** Maximum number of distinct ability checks kept per entry. */
    public const int MAX_ABILITIES = 100; // @pest-mutate-ignore (declaration lines carry no coverage; the limit is asserted in PendingEntryTest)

    /** Maximum number of mails / notifications kept per entry. */
    public const int MAX_MESSAGES = 100; // @pest-mutate-ignore (see MAX_ABILITIES)

    /** @var array<string, array{ability: string, result: bool|null, arguments: list<mixed>, count: int}> */
    public array $abilities = [];

    public int $deniedAbilitiesCount = 0; // @pest-mutate-ignore (defaults are asserted in PendingEntryTest)

    /** @var array<string, array{ids: array<string, true>, count: int}> */
    public array $modelsAccessed = [];

    /** @var list<array{mailable: string|null, subject: string|null, to: list<string>, cc: list<string>, bcc: list<string>}> */
    public array $mails = [];

    /** @var list<array{notification: string, channel: string, notifiable_type: string|null, notifiable_id: string|null}> */
    public array $notifications = [];

    /** @var array<string, mixed> */
    public array $properties = [];

    /** @var array<string, true> */
    public array $tags = [];

    /** @var list<ModelChangeData> */
    public array $changes = [];

    public int $changesCount = 0; // @pest-mutate-ignore

    public bool $ignored = false; // @pest-mutate-ignore

    /** Snapshot of models.track_retrieved, read once per entry (hot path). */
    public bool $trackRetrieved = true; // @pest-mutate-ignore

    /** Snapshot of models.max_ids_per_model. */
    public int $maxIds = 50; // @pest-mutate-ignore

    /** Whether a partial version of this entry was already persisted. */
    public bool $persisted = false; // @pest-mutate-ignore

    /** @var array{type: string, id: string, guard: string|null}|null */
    public ?array $user = null;

    /**
     * Free-form attributes that end up as EntryData fields (url, ip, ...).
     *
     * @var array<string, mixed>
     */
    public array $attributes = [];

    public function __construct(
        public readonly string $ulid,
        public readonly string $correlationId,
        public readonly EntryType $type,
        public ?string $name,
        public readonly string $startedAt,
        public readonly int|float $startedHrtime,
        public readonly ?string $key = null,
    ) {}

    public function addAbility(AbilityCheck $check): void
    {
        $key = json_encode([$check->ability, $check->result, $check->arguments], JSON_THROW_ON_ERROR);

        if (isset($this->abilities[$key])) {
            $this->abilities[$key]['count']++;

            return;
        }

        if (count($this->abilities) >= self::MAX_ABILITIES) {
            return;
        }

        $this->abilities[$key] = $check->toArray() + ['count' => 1];

        if ($check->denied()) {
            $this->deniedAbilitiesCount++;
        }
    }

    public function addModelAccess(string $class, string $id, int $maxIds): void
    {
        $this->modelsAccessed[$class] ??= ['ids' => [], 'count' => 0];
        $this->modelsAccessed[$class]['count']++;

        if (count($this->modelsAccessed[$class]['ids']) < $maxIds) {
            $this->modelsAccessed[$class]['ids'][$id] = true; // @pest-mutate-ignore TrueToFalse (only the keys are used)
        }
    }

    public function addMail(MailRecord $mail): void
    {
        if (count($this->mails) < self::MAX_MESSAGES) {
            $this->mails[] = $mail->toArray();
        }
    }

    public function addNotification(NotificationRecord $notification): void
    {
        if (count($this->notifications) < self::MAX_MESSAGES) {
            $this->notifications[] = $notification->toArray();
        }
    }

    public function addChange(ModelChangeData $change): void
    {
        $this->changes[] = $change;
        $this->changesCount++;
    }

    /**
     * @return list<ModelChangeData>
     */
    public function pullChanges(): array
    {
        $changes = $this->changes;
        $this->changes = [];

        return $changes;
    }

    public function hasSomethingWorthKeeping(): bool
    {
        return $this->changesCount > 0 || $this->deniedAbilitiesCount > 0;
    }

    public function toData(?string $completedAt = null, ?int $durationMs = null): EntryData
    {
        $a = $this->attributes;

        return new EntryData(
            ulid: $this->ulid,
            correlationId: $this->correlationId,
            type: $this->type,
            name: $this->name,
            startedAt: $this->startedAt,
            completedAt: $completedAt,
            userType: $this->user['type'] ?? null,
            userId: $this->user['id'] ?? null,
            guard: $this->user['guard'] ?? null,
            httpMethod: Values::nullableString($a, 'http_method'),
            url: Values::nullableString($a, 'url'),
            routeAction: Values::nullableString($a, 'route_action'),
            statusCode: Values::nullableInt($a, 'status_code'),
            failed: (bool) ($a['failed'] ?? false),
            ip: Values::nullableString($a, 'ip'),
            userAgent: Values::nullableString($a, 'user_agent'),
            osUser: Values::nullableString($a, 'os_user'),
            hostname: Values::nullableString($a, 'hostname'),
            durationMs: $durationMs,
            abilities: array_values($this->abilities),
            modelsAccessed: array_map(
                fn (array $model): array => ['ids' => array_map(strval(...), array_keys($model['ids'])), 'count' => $model['count']],
                $this->modelsAccessed,
            ),
            mails: $this->mails,
            notifications: $this->notifications,
            input: Values::nullableMap($a, 'input'),
            properties: $this->properties,
            tags: array_map(strval(...), array_keys($this->tags)),
            deniedAbilitiesCount: $this->deniedAbilitiesCount,
        );
    }
}
