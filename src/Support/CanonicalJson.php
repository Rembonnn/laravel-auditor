<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

/**
 * Deterministic JSON: object keys are sorted recursively so the same data
 * always produces the same string (and therefore the same hash).
 */
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = $value instanceof \JsonSerializable ? $value->jsonSerialize() : get_object_vars($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::normalize(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);

            // An associative array must stay a JSON object even when keys are numeric.
            return (object) $value;
        }

        return $value;
    }
}
