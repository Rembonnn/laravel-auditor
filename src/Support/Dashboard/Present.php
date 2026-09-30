<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Support\Settings;

/**
 * Small presentation helpers shared by the dashboard views. Colour is never
 * the only signal: every tone comes with an icon and/or text.
 */
final class Present
{
    public static function methodTone(?string $method): string
    {
        return match (strtoupper((string) $method)) {
            'POST' => 'success',
            'PUT', 'PATCH' => 'warning',
            'DELETE' => 'danger',
            default => 'neutral',
        };
    }

    public static function statusTone(?int $status, bool $failed = false): string
    {
        return match (true) {
            $failed || ($status !== null && $status >= 500) => 'danger',
            $status !== null && $status >= 400 => 'warning',
            $status !== null && $status >= 300 => 'neutral',
            default => 'success',
        };
    }

    public static function eventTone(ChangeEvent $event): string
    {
        return match ($event) {
            ChangeEvent::Created => 'success',
            ChangeEvent::Updated => 'warning',
            ChangeEvent::Deleted, ChangeEvent::ForceDeleted => 'danger',
            ChangeEvent::Restored => 'info',
        };
    }

    public static function eventIcon(ChangeEvent $event): string
    {
        return match ($event) {
            ChangeEvent::Created => 'circle-plus',
            ChangeEvent::Updated => 'pencil',
            ChangeEvent::Deleted, ChangeEvent::ForceDeleted => 'trash',
            ChangeEvent::Restored => 'rotate-ccw',
        };
    }

    public static function typeIcon(EntryType $type): string
    {
        return match ($type) {
            EntryType::Http => 'globe',
            EntryType::Job => 'briefcase',
            EntryType::Command => 'terminal',
            EntryType::Other => 'circle',
        };
    }

    public static function typeTone(EntryType $type): string
    {
        return match ($type) {
            EntryType::Http => 'neutral',
            EntryType::Job => 'info',
            EntryType::Command => 'accent',
            EntryType::Other => 'neutral',
        };
    }

    public static function duration(?int $ms): string
    {
        return match (true) {
            $ms === null => '—',
            $ms < 1000 => $ms.' ms',
            $ms < 60_000 => rtrim(rtrim(number_format($ms / 1000, 2), '0'), '.').' s',
            default => intdiv($ms, 60_000).'m '.intdiv($ms % 60_000, 1000).'s',
        };
    }

    public static function shortClass(?string $class): string
    {
        return $class === null ? '' : class_basename($class);
    }

    public static function shortId(string $id, int $keep = 6): string
    {
        return strlen($id) <= $keep * 2 + 1 ? $id : substr($id, 0, $keep).'…'.substr($id, -$keep);
    }

    public static function iso(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::RFC3339_EXTENDED);
    }

    /**
     * Server-side fallback text for a <time> element (JS replaces it).
     */
    public static function absolute(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        $zone = Settings::nullableString('auditor.dashboard.timezone') ?? Settings::string('app.timezone', 'UTC');

        return Carbon::instance($date)->setTimezone($zone)->format('Y-m-d H:i:s T');
    }

    public static function number(int|float $value): string
    {
        return number_format($value, 0, '', '.');
    }

    public static function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '#';
        }

        $words = preg_split('/[\s._@-]+/', $name) ?: [$name];

        return Str::upper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    }

    /**
     * How a scalar value is shown: null, booleans and empty strings get their
     * own style so they are never ambiguous.
     *
     * @return array{kind: string, text: string}
     */
    public static function value(mixed $value): array
    {
        return match (true) {
            $value === null => ['kind' => 'null', 'text' => 'null'],
            $value === true => ['kind' => 'literal', 'text' => 'true'],
            $value === false => ['kind' => 'literal', 'text' => 'false'],
            $value === '' => ['kind' => 'empty', 'text' => ''],
            is_string($value) && $value === config('auditor.redaction.replacement', '[REDACTED]') => ['kind' => 'redacted', 'text' => ''],
            is_int($value), is_float($value) => ['kind' => 'number', 'text' => (string) $value],
            is_array($value) => ['kind' => 'json', 'text' => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
            default => ['kind' => 'string', 'text' => is_scalar($value) ? (string) $value : get_debug_type($value)],
        };
    }

    public static function isRedacted(mixed $value): bool
    {
        return is_string($value) && $value === config('auditor.redaction.replacement', '[REDACTED]');
    }
}
