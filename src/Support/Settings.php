<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

/**
 * Typed, lenient reads of configuration values.
 *
 * @internal
 */
final class Settings
{
    public static function string(string $key, string $default = ''): string
    {
        return Values::toString(config($key)) ?? $default;
    }

    public static function nullableString(string $key): ?string
    {
        $value = Values::toString(config($key));

        return $value === '' ? null : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        return Values::toInt(config($key)) ?? $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = config($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return list<string>
     */
    public static function strings(string $key): array
    {
        $value = config($key);

        return is_array($value) ? Values::strings(['v' => $value], 'v') : [];
    }
}
