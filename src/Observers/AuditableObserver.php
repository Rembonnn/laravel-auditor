<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Observers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Observer attached by the Auditable trait.
 *
 * Soft deletes are recorded as "deleted" (with the new deleted_at value),
 * a restore() as "restored", and a forceDelete() only as "force_deleted".
 */
final readonly class AuditableObserver
{
    private const array JSON_CASTS = ['array', 'json', 'object', 'collection', 'json:unicode'];

    public function __construct(private Container $app) {}

    /**
     * Hot path (every loaded model): no closures, no config() lookups.
     */
    public function retrieved(Model $model): void
    {
        try {
            if (method_exists($model, 'auditsRetrieved') && $model->auditsRetrieved() === false) {
                return;
            }

            $this->recorder()->recordModelAccess($model);
        } catch (\Throwable $e) {
            $this->auditor()->guard(fn () => throw $e);
        }
    }

    public function created(Model $model): void
    {
        $this->record($model, ChangeEvent::Created, null, $this->values($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->withoutExcluded($model, $model->getChanges());

        if ($changes === []) {
            return; // e.g. touch(): nothing but excluded attributes changed.
        }

        $event = $this->isRestore($model, $changes) ? ChangeEvent::Restored : ChangeEvent::Updated;
        // Attributes absent from the original (never set before) were null.
        $original = [];

        foreach (array_keys($changes) as $key) {
            $original[$key] = $model->getRawOriginal($key);
        }

        $this->record($model, $event, $this->values($model, $original), $this->values($model, $changes));
    }

    public function deleted(Model $model): void
    {
        $column = $this->softDeleteColumn($model);

        if ($column === null) {
            $this->record($model, ChangeEvent::Deleted, $this->values($model, $this->withoutExcluded($model, $model->getAttributes())), null);

            return;
        }

        // A force delete fires "deleted" too; "forceDeleted" records it.
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        $old = $this->withoutExcluded($model, [$column => null] + $model->getAttributes());

        $this->record($model, ChangeEvent::Deleted, $this->values($model, $old), [$column => $model->getAttributes()[$column] ?? null]);
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, ChangeEvent::ForceDeleted, $this->values($model, $this->withoutExcluded($model, $model->getAttributes())), null);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function record(Model $model, ChangeEvent $event, ?array $old, ?array $new): void
    {
        $this->auditor()->guard(function () use ($model, $event, $old, $new): void {
            $events = method_exists($model, 'getAuditEvents')
                ? Values::strings(['v' => $model->getAuditEvents()], 'v')
                : Settings::strings('auditor.models.events');

            if (! in_array($event->value, $events, true)) {
                return;
            }

            $this->recorder()->recordModelChange($model, $event, $old, $new);
        });
    }

    /**
     * Excluded attributes are dropped, JSON columns decoded, secrets redacted.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function values(Model $model, array $attributes): array
    {
        $attributes = $this->withoutExcluded($model, $attributes);

        foreach ($model->getCasts() as $key => $cast) {
            if (array_key_exists($key, $attributes) && is_string($attributes[$key]) && self::isJsonCast((string) $cast)) {
                $decoded = json_decode($attributes[$key], true);
                $attributes[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $attributes[$key];
            }
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $attributes[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $attributes[$key] = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $attributes[$key] = $value->format($model->getDateFormat());
            }
        }

        return $this->app->make(Redactor::class)->redactModelAttributes($model, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutExcluded(Model $model, array $attributes): array
    {
        $exclude = method_exists($model, 'getAuditExclude')
            ? Values::strings(['v' => $model->getAuditExclude()], 'v')
            : Settings::strings('auditor.models.exclude');

        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function isRestore(Model $model, array $changes): bool
    {
        $column = $this->softDeleteColumn($model);

        return $column !== null
            && array_key_exists($column, $changes)
            && $changes[$column] === null
            && ($model->getRawOriginal($column) !== null);
    }

    /**
     * The deleted_at column when the model uses SoftDeletes, null otherwise.
     */
    private function softDeleteColumn(Model $model): ?string
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true) || ! method_exists($model, 'getDeletedAtColumn')) {
            return null;
        }

        return Values::toString($model->getDeletedAtColumn());
    }

    private static function isJsonCast(string $cast): bool
    {
        $type = Str::lower(Str::before($cast, ':'));

        return in_array($type, self::JSON_CASTS, true)
            || $cast === 'json:unicode'
            || in_array(Str::before($cast, ':'), [AsArrayObject::class, AsCollection::class], true);
    }

    private function recorder(): Recorder
    {
        return $this->app->make(Recorder::class);
    }

    private function auditor(): Auditor
    {
        return $this->app->make(Auditor::class);
    }
}
