<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Enums\ChangeEvent;

/**
 * In-memory auditor for tests: everything is stored synchronously in
 * memory (never queued, never written to the database).
 */
class AuditorFake extends Auditor implements Storage
{
    /** @var array<string, EntryData> keyed by ulid */
    protected array $entries = [];

    /** @var list<ModelChangeData> */
    protected array $changes = [];

    public function __construct(Container $app, ?Auditor $original = null)
    {
        parent::__construct($app);

        if ($original !== null) {
            $this->authUsing = $original->authUsing;
            $this->filterUsing = $original->filterUsing;
            $this->displayUserUsing = $original->displayUserUsing;
            $this->nonce = $original->nonce;
        }
    }

    public function storage(): Storage
    {
        return $this;
    }

    public function persist(?EntryData $entry, array $changes = []): void
    {
        if ($entry !== null || $changes !== []) {
            $this->storeNow($entry, $changes);
        }
    }

    public function store(?EntryData $entry, array $changes = []): void
    {
        if ($entry !== null) {
            $existing = $this->entries[$entry->ulid] ?? null;

            if ($existing === null || $entry->isComplete() || ! $existing->isComplete()) {
                $this->entries[$entry->ulid] = $entry;
            }
        }

        array_push($this->changes, ...$changes);
    }

    /**
     * @return list<EntryData>
     */
    public function entries(?Closure $callback = null): array
    {
        $entries = array_values(array_filter($this->entries, fn (EntryData $e): bool => $e->isComplete()));

        return $callback === null ? $entries : array_values(array_filter($entries, $callback));
    }

    /**
     * @return list<ModelChangeData>
     */
    public function changes(?Closure $callback = null): array
    {
        return $callback === null ? $this->changes : array_values(array_filter($this->changes, $callback));
    }

    /**
     * @param  (Closure(EntryData): bool)|null  $callback
     */
    public function assertEntryRecorded(?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty($this->entries($callback), 'The expected audit entry was not recorded.');
    }

    /**
     * @param  (Closure(EntryData): bool)|null  $callback
     */
    public function assertEntryNotRecorded(?Closure $callback = null): void
    {
        PHPUnit::assertEmpty($this->entries($callback), 'An unexpected audit entry was recorded.');
    }

    /**
     * @param  class-string<Model>|string  $auditableType  Model class or morph alias
     * @param  (Closure(ModelChangeData): bool)|null  $callback
     */
    public function assertChangeRecorded(
        string $auditableType,
        int|string|null $auditableId = null,
        ChangeEvent|string|null $event = null,
        ?Closure $callback = null,
    ): void {
        PHPUnit::assertNotEmpty(
            $this->matchingChanges($auditableType, $auditableId, $event, $callback),
            "The expected change of [{$auditableType}] was not recorded.",
        );
    }

    /**
     * @param  class-string<Model>|string  $auditableType
     */
    public function assertChangeNotRecorded(string $auditableType, int|string|null $auditableId = null, ChangeEvent|string|null $event = null): void
    {
        PHPUnit::assertEmpty(
            $this->matchingChanges($auditableType, $auditableId, $event, null),
            "An unexpected change of [{$auditableType}] was recorded.",
        );
    }

    public function assertAbilityDenied(string $ability): void
    {
        PHPUnit::assertTrue($this->abilityChecked($ability, denied: true), "Ability [{$ability}] was not recorded as denied.");
    }

    public function assertAbilityGranted(string $ability): void
    {
        PHPUnit::assertTrue($this->abilityChecked($ability, denied: false), "Ability [{$ability}] was not recorded as granted.");
    }

    public function assertNothingRecorded(): void
    {
        PHPUnit::assertEmpty($this->entries(), 'Audit entries were recorded unexpectedly.');
        PHPUnit::assertEmpty($this->changes, 'Model changes were recorded unexpectedly.');
    }

    /**
     * @return list<ModelChangeData>
     */
    private function matchingChanges(string $type, int|string|null $id, ChangeEvent|string|null $event, ?Closure $callback): array
    {
        $types = [$type];

        if (class_exists($type) && is_subclass_of($type, Model::class)) {
            $types[] = (new $type)->getMorphClass();
        }

        $event = is_string($event) ? ChangeEvent::from($event) : $event;

        return $this->changes(fn (ModelChangeData $change): bool => in_array($change->auditableType, $types, true)
            && ($id === null || $change->auditableId === (string) $id)
            && ($event === null || $change->event === $event)
            && ($callback === null || $callback($change)));
    }

    private function abilityChecked(string $ability, bool $denied): bool
    {
        foreach ($this->entries() as $entry) {
            foreach ($entry->abilities as $check) {
                if ($check['ability'] === $ability && ($check['result'] !== true) === $denied) {
                    return true;
                }
            }
        }

        return false;
    }
}
