<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Contracts\UserResolver;
use Rembon\LaravelAuditor\Data\AbilityCheck;
use Rembon\LaravelAuditor\Data\MailRecord;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Data\NotificationRecord;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\CorrelationId;
use Rembon\LaravelAuditor\Support\PendingEntry;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Holds the audit state of the current lifecycle. Bound as a scoped
 * instance, so Octane and queue workers get a fresh one per request / job.
 *
 * Entries form a stack: a job that runs synchronously inside a request gets
 * its own entry (with the same correlation id) on top of the request entry.
 */
final class Recorder
{
    /** @var list<PendingEntry> */
    private array $stack = [];

    /** @var list<string|null> Correlation id in Context before each entry started. */
    private array $previousCorrelation = [];

    private int $paused = 0;

    public function __construct(private readonly Container $app) {}

    public function enabled(): bool
    {
        return (bool) config('auditor.enabled', true);
    }

    public function isRecording(): bool
    {
        return $this->paused === 0 && $this->enabled();
    }

    public function current(): ?PendingEntry
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    /**
     * Find a running entry by the key it was started with.
     */
    public function find(string $key): ?PendingEntry
    {
        foreach (array_reverse($this->stack) as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{type: string, id: string, guard: string|null}|null  $user
     */
    public function start(
        EntryType $type,
        ?string $name = null,
        array $attributes = [],
        ?string $key = null,
        ?string $correlationId = null,
        ?array $user = null,
    ): PendingEntry {
        $previous = CorrelationId::current();
        $correlationId ??= $this->current()->correlationId ?? $previous ?? CorrelationId::generate();

        if ($previous !== $correlationId) {
            CorrelationId::set($correlationId);
        }

        $entry = new PendingEntry(
            ulid: (string) Str::ulid(),
            correlationId: $correlationId,
            type: $type,
            name: $name,
            startedAt: self::now(),
            startedHrtime: hrtime(true),
            key: $key,
        );

        $entry->attributes = $attributes;
        $entry->trackRetrieved = (bool) config('auditor.models.track_retrieved', true);
        $entry->maxIds = Settings::int('auditor.models.max_ids_per_model', 50);

        $this->stack[] = $entry;
        $this->previousCorrelation[] = $previous;

        // Resolved after pushing so a user model loaded here is attributed.
        $entry->user = $user ?? $this->resolveUser();

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes  Entry fields, plus the
     *                                            special keys "duration_ms" and "sampled_out".
     */
    public function finish(PendingEntry $entry, array $attributes = []): void
    {
        $index = array_search($entry, $this->stack, true);

        if ($index === false) {
            return;
        }

        // Anything started inside this entry that never finished (e.g. an
        // exception skipped its end event) is closed as failed first.
        while (array_key_last($this->stack) > $index) {
            $this->finish($this->stack[array_key_last($this->stack)], ['failed' => true]);
        }

        array_pop($this->stack);
        $previous = array_pop($this->previousCorrelation);

        try {
            $this->complete($entry, $attributes);
        } finally {
            if ($this->stack === [] && $previous !== $entry->correlationId) {
                $previous === null
                    ? Context::forgetHidden(CorrelationId::CONTEXT_KEY)
                    : CorrelationId::set($previous);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function recordModelChange(Model $model, ChangeEvent $event, ?array $oldValues, ?array $newValues): void
    {
        if (! $this->isRecording()) {
            return;
        }

        $entry = $this->current();
        $standalone = $entry === null;

        // A change outside any request/job/command still gets an entry.
        $entry ??= $this->start(EntryType::Other);

        $user = $this->resolveUser() ?? $entry->user;

        $entry->addChange(new ModelChangeData(
            ulid: (string) Str::ulid(),
            entryUlid: $entry->ulid,
            correlationId: $entry->correlationId,
            auditableType: $model->getMorphClass(),
            auditableId: Values::toString($model->getKey()) ?? '',
            event: $event,
            oldValues: $oldValues,
            newValues: $newValues,
            userType: $user['type'] ?? null,
            userId: $user['id'] ?? null,
            createdAt: self::now(),
        ));

        if ($standalone) {
            $this->finish($entry);
        } elseif (count($entry->changes) >= max(1, Settings::int('auditor.buffer_size', 100))) {
            $this->flush($entry);
        }
    }

    /**
     * Called for every model loaded, so it avoids config() and container
     * lookups: settings are snapshotted when the entry starts.
     */
    public function recordModelAccess(Model $model): void
    {
        if ($this->paused > 0 || $this->stack === []) {
            return;
        }

        $entry = $this->stack[array_key_last($this->stack)];

        if ($entry->trackRetrieved) {
            $entry->addModelAccess($model->getMorphClass(), Values::toString($model->getKey()) ?? '', $entry->maxIds);
        }
    }

    public function recordAbility(AbilityCheck $check): void
    {
        if ($this->isRecording()) {
            $this->current()?->addAbility($check);
        }
    }

    public function recordMail(MailRecord $mail): void
    {
        if ($this->isRecording()) {
            $this->current()?->addMail($mail);
        }
    }

    public function recordNotification(NotificationRecord $notification): void
    {
        if ($this->isRecording()) {
            $this->current()?->addNotification($notification);
        }
    }

    public function withProperty(string $key, mixed $value): void
    {
        if ($entry = $this->current()) {
            $entry->properties[$key] = $value;
        }
    }

    public function tag(string ...$tags): void
    {
        if ($entry = $this->current()) {
            foreach ($tags as $tag) {
                $entry->tags[$tag] = true;
            }
        }
    }

    /**
     * Do not store the current entry. Its model changes are still stored.
     */
    public function ignore(): void
    {
        if ($entry = $this->current()) {
            $entry->ignored = true;
        }
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $this->paused++;

        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    public function correlationId(): string
    {
        if ($id = $this->current()->correlationId ?? CorrelationId::current()) {
            return $id;
        }

        CorrelationId::set($id = CorrelationId::generate());

        return $id;
    }

    /**
     * The user to attribute work dispatched right now (e.g. a queued job).
     *
     * @return array{type: string, id: string, guard: string|null}|null
     */
    public function causer(): ?array
    {
        return $this->resolveUser() ?? $this->current()?->user;
    }

    /**
     * Persist buffered model changes now, before the entry is finished.
     */
    public function flush(PendingEntry $entry): void
    {
        $changes = $entry->pullChanges();

        if ($changes === []) {
            return;
        }

        if ($entry->ignored) {
            $this->auditor()->persist(null, array_map(fn (ModelChangeData $c): ModelChangeData => $c->withoutEntry(), $changes));

            return;
        }

        $this->auditor()->persist($entry->toData(), $changes);
        $entry->persisted = true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function complete(PendingEntry $entry, array $attributes): void
    {
        $durationMs = $attributes['duration_ms'] ?? (int) round((hrtime(true) - $entry->startedHrtime) / 1_000_000);
        $sampledOut = (bool) ($attributes['sampled_out'] ?? false);

        unset($attributes['duration_ms'], $attributes['sampled_out']);

        $entry->attributes = array_merge($entry->attributes, $attributes);
        $entry->user = $this->resolveUser() ?? $entry->user;

        $changes = $entry->pullChanges();
        $data = $entry->toData(self::now(), Values::toInt($durationMs) ?? 0);

        $keep = $entry->persisted || (
            ! $entry->ignored
            && ! ($sampledOut && ! $entry->hasSomethingWorthKeeping())
            && $this->auditor()->passesFilter($data)
        );

        if ($keep) {
            $this->auditor()->persist($data, $changes);
        } elseif ($changes !== []) {
            $this->auditor()->persist(null, array_map(fn (ModelChangeData $c): ModelChangeData => $c->withoutEntry(), $changes));
        }
    }

    /**
     * @return array{type: string, id: string, guard: string|null}|null
     */
    private function resolveUser(): ?array
    {
        $user = $this->auditor()->guard(fn () => $this->app->make(UserResolver::class)->resolve());

        if ($user !== null) {
            return $user;
        }

        $causer = Context::getHidden(CorrelationId::CAUSER_CONTEXT_KEY);

        if (! is_array($causer)) {
            return null;
        }

        $type = Values::nullableString($causer, 'type');
        $id = Values::nullableString($causer, 'id');

        return $type !== null && $id !== null
            ? ['type' => $type, 'id' => $id, 'guard' => Values::nullableString($causer, 'guard')]
            : null;
    }

    private function auditor(): Auditor
    {
        return $this->app->make(Auditor::class);
    }

    public static function now(): string
    {
        return Date::now()->format('Y-m-d H:i:s.u');
    }
}
