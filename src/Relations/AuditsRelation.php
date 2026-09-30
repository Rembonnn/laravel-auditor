<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Values;

/**
 * MorphMany that compares keys as strings when auditable_id is a string
 * column, so integer keys work on strict databases such as PostgreSQL.
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<ModelChange, TDeclaringModel>
 */
final class AuditsRelation extends MorphMany
{
    public function getParentKey(): mixed
    {
        $key = parent::getParentKey();

        return self::usesStringKeys() && $key !== null ? Values::toString($key) : $key;
    }

    public function addEagerConstraints(array $models): void
    {
        if (! self::usesStringKeys()) {
            parent::addEagerConstraints($models);

            return;
        }

        $this->whereInEager(
            'whereIn',
            $this->foreignKey,
            array_map(strval(...), $this->getKeys($models, $this->localKey)),
            $this->getRelationQuery(),
        );

        $this->getRelationQuery()->where($this->morphType, $this->morphClass);
    }

    private static function usesStringKeys(): bool
    {
        return config('auditor.storage.database.morph_key_type', 'string') === 'string';
    }
}
