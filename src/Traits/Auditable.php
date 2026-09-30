<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Traits;

use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Observers\AuditableObserver;
use Rembon\LaravelAuditor\Relations\AuditsRelation;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Records created / updated / deleted / restored / force deleted changes
 * (with a diff) and which records were read during a request.
 *
 * Optional properties on the model:
 *   protected array $auditExclude = ['view_count'];
 *   protected array $auditEvents = ['updated', 'deleted'];
 *   protected bool $auditRetrieved = false;
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        // observe() cannot be called while the model is booting.
        foreach (['retrieved', 'created', 'updated', 'deleted', 'forceDeleted'] as $event) {
            static::registerModelEvent(
                $event,
                static fn (Model $model) => app(AuditableObserver::class)->{$event}($model),
            );
        }
    }

    /**
     * Recorded changes of this model, newest first.
     *
     * @return AuditsRelation<$this>
     */
    public function audits(): AuditsRelation
    {
        $instance = new ModelChange;

        $relation = new AuditsRelation(
            $instance->newQuery(),
            $this,
            $instance->qualifyColumn('auditable_type'),
            $instance->qualifyColumn('auditable_id'),
            $this->getKeyName(),
        );

        $relation->getQuery()->orderByDesc($instance->qualifyColumn('id'));

        return $relation;
    }

    /**
     * Attributes never recorded: the global `auditor.models.exclude` list
     * plus the model's own $auditExclude.
     *
     * @return list<string>
     */
    public function getAuditExclude(): array
    {
        $own = property_exists($this, 'auditExclude') ? Values::strings(['v' => $this->auditExclude], 'v') : [];

        return array_values(array_unique([...Settings::strings('auditor.models.exclude'), ...$own]));
    }

    /**
     * @return list<string>
     */
    public function getAuditEvents(): array
    {
        return property_exists($this, 'auditEvents')
            ? Values::strings(['v' => $this->auditEvents], 'v')
            : Settings::strings('auditor.models.events');
    }

    /**
     * The model's own $auditRetrieved, or null to follow the config.
     */
    public function auditsRetrieved(): ?bool
    {
        return property_exists($this, 'auditRetrieved') ? (bool) $this->auditRetrieved : null;
    }

    public function shouldAuditRetrieved(): bool
    {
        return property_exists($this, 'auditRetrieved')
            ? (bool) $this->auditRetrieved
            : (bool) config('auditor.models.track_retrieved', true);
    }
}
