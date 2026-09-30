<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;

/**
 * Fixed-size time buckets for the overview charts, grouped in SQL with a
 * driver-specific date format (whereDate / date ranges, never whereDay).
 */
final readonly class TimeBuckets
{
    /** @var array<string, array{int, string, int}> range => [count, unit, step] */
    public const array RANGES = [
        '1h' => [12, 'minute', 5],
        '24h' => [24, 'hour', 1],
        '7d' => [7, 'day', 1],
        '30d' => [30, 'day', 1],
    ];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    private function __construct(
        public string $range,
        public int $count,
        public string $unit,
        public int $step,
    ) {
        $now = CarbonImmutable::instance(Date::now());
        $end = match ($unit) {
            'minute' => $now->startOfMinute()->subMinutes($now->minute % $step)->addMinutes($step),
            'hour' => $now->startOfHour()->addHour(),
            default => $now->startOfDay()->addDay(),
        };

        $this->to = $end;
        $this->from = $end->sub($unit, $count * $step);
    }

    public static function for(string $range): self
    {
        [$count, $unit, $step] = self::RANGES[$range] ?? self::RANGES['24h'];

        return new self(isset(self::RANGES[$range]) ? $range : '24h', $count, $unit, $step);
    }

    /**
     * The window right before this one (for "▲ 8%" deltas).
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function previous(): array
    {
        return [$this->from->sub($this->unit, $this->count * $this->step), $this->from];
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function starts(): array
    {
        $starts = [];

        for ($i = 0; $i < $this->count; $i++) {
            $starts[] = $this->from->add($this->unit, $i * $this->step);
        }

        return $starts;
    }

    /**
     * SQL that formats created_at as the bucket key. Built from literals only.
     *
     * @return literal-string
     */
    public function expression(Connection $connection): string
    {
        $driver = $connection->getDriverName();

        return match ($this->unit) {
            'minute' => match ($driver) {
                'sqlite' => "strftime('%Y-%m-%d %H:%M', created_at)",
                'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:MI')",
                'sqlsrv' => "FORMAT(created_at, 'yyyy-MM-dd HH:mm')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')",
            },
            'hour' => match ($driver) {
                'sqlite' => "strftime('%Y-%m-%d %H', created_at)",
                'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24')",
                'sqlsrv' => "FORMAT(created_at, 'yyyy-MM-dd HH')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d %H')",
            },
            default => match ($driver) {
                'sqlite' => "strftime('%Y-%m-%d', created_at)",
                'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
                'sqlsrv' => "FORMAT(created_at, 'yyyy-MM-dd')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
            },
        };
    }

    /**
     * Index of the bucket a SQL key ("2026-09-29 10" etc.) falls into.
     */
    public function index(string $key): ?int
    {
        $format = match ($this->unit) {
            'minute' => 'Y-m-d H:i',
            'hour' => 'Y-m-d H',
            default => 'Y-m-d',
        };

        $date = CarbonImmutable::createFromFormat('!'.$format, $key, $this->from->getTimezone());

        if ($date === null || $date < $this->from || $date >= $this->to) {
            return null;
        }

        $minutes = (int) $this->from->diffInMinutes($date);
        $size = match ($this->unit) {
            'minute' => $this->step,
            'hour' => 60 * $this->step,
            default => 1440 * $this->step,
        };

        $index = intdiv($minutes, $size);

        return $index < $this->count ? $index : null;
    }

    public function labelFormat(): string
    {
        return $this->unit === 'day' ? 'd M' : 'H:i';
    }
}
