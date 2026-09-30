<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

/**
 * Attribute-level diff of a model change. Nested JSON is diffed per key;
 * unchanged keys are collapsed; redacted values are never compared.
 */
final class Diff
{
    /**
     * @param  array<array-key, mixed>|null  $old
     * @param  array<array-key, mixed>|null  $new
     * @return list<array{key: string, status: string, old: mixed, new: mixed, children: list<array<string, mixed>>, unchanged: int}>
     */
    public static function rows(?array $old, ?array $new): array
    {
        $keys = array_values(array_unique([...array_keys($new ?? []), ...array_keys($old ?? [])]));
        $rows = [];

        foreach ($keys as $key) {
            $hasOld = $old !== null && array_key_exists($key, $old);
            $hasNew = $new !== null && array_key_exists($key, $new);
            $before = $hasOld ? $old[$key] : null;
            $after = $hasNew ? $new[$key] : null;

            $row = ['key' => (string) $key, 'status' => 'changed', 'old' => $before, 'new' => $after, 'children' => [], 'unchanged' => 0];

            if (Present::isRedacted($before) || Present::isRedacted($after)) {
                $row['status'] = 'redacted';
            } elseif (! $hasOld) {
                $row['status'] = 'added';
            } elseif (! $hasNew) {
                $row['status'] = 'removed';
            } elseif ($before === $after) {
                $row['status'] = 'unchanged';
            } elseif (is_array($before) && is_array($after) && ! array_is_list($before) && ! array_is_list($after)) {
                $children = self::rows($before, $after);
                $row['children'] = array_values(array_filter($children, fn (array $child): bool => $child['status'] !== 'unchanged'));
                $row['unchanged'] = count($children) - count($row['children']);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return list<string>
     */
    public static function keys(?array $values): array
    {
        return array_map(strval(...), array_keys($values ?? []));
    }
}
