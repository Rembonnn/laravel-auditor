<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Browser', 'Benchmark');

/**
 * @return Collection<int, Entry>
 */
function entries(?string $type = null): Collection
{
    return Entry::query()->when($type, fn ($q) => $q->where('type', $type))->orderBy('id')->get();
}

/**
 * @return Collection<int, ModelChange>
 */
function changes(): Collection
{
    return ModelChange::query()->orderBy('id')->get();
}

/**
 * Sorts object keys recursively. MySQL's JSON type does not keep key order,
 * so compare stored JSON with this on both sides.
 *
 * @template T
 *
 * @param  T  $value
 * @return T
 */
function sortedKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(sortedKeys(...), $value);

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}
