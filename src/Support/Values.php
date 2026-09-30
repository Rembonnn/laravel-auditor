<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

/**
 * Typed reads from loosely typed arrays (queue payloads, database rows,
 * decoded JSON). Invalid values fall back instead of failing.
 *
 * @internal
 */
final class Values
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        return self::nullableString($data, $key) ?? $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        return self::toString($data[$key] ?? null);
    }

    public static function toString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        return self::nullableInt($data, $key) ?? $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        return self::toInt($data[$key] ?? null);
    }

    public static function toInt(mixed $value): ?int
    {
        return is_numeric($value) || is_bool($value) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function bool(array $data, string $key): bool
    {
        return in_array($data[$key] ?? false, [true, 1, '1', 't', 'true'], true);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::stringKeys($value) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function nullableMap(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::stringKeys($value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        return array_values(array_filter(array_map(self::toString(...), self::list($data, $key)), is_string(...)));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    public static function stringKeys(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item; // @pest-mutate-ignore RemoveStringCast (PHP normalises numeric string keys back to int)
        }

        return $result;
    }
}
