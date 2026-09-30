<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Rembon\LaravelAuditor\Events\IntegrityViolationDetected;
use Rembon\LaravelAuditor\Integrity\RowHasher;
use Rembon\LaravelAuditor\Integrity\Verifier;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'auditor:verify')]
final class VerifyCommand extends Command
{
    public const string CACHE_KEY = 'auditor:integrity:last_verify';

    protected $signature = 'auditor:verify
        {--table= : Only verify one table (entries or model_changes)}
        {--from= : Start at this id, trusting the chain before it}';

    protected $description = 'Verify the tamper-evident hash chain of the audit tables';

    public function handle(Cache $cache): int
    {
        if (! config('auditor.integrity.enabled')) {
            $this->components->warn('Integrity is disabled (AUDITOR_INTEGRITY=false). Nothing to verify.');

            return self::SUCCESS;
        }

        try {
            $verifier = new Verifier(RowHasher::fromConfig());
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $tables = $this->option('table') ? [(string) $this->option('table')] : RowHasher::tables();

        if (array_diff($tables, RowHasher::tables()) !== []) {
            $this->components->error('Unknown table. Use one of: '.implode(', ', RowHasher::tables()).'.');

            return self::FAILURE;
        }

        $valid = true;
        $report = [];

        foreach ($tables as $table) {
            $result = $verifier->verify($table, $this->option('from') === null ? null : (int) $this->option('from'));
            $report[$table] = $result->toArray();

            $this->components->twoColumnDetail(
                $table,
                $result->valid()
                    ? "<fg=green>valid</> ({$result->checked} checked, {$result->unsealed} unsealed)"
                    : '<fg=red>'.count($result->violations).' violation(s)</>',
            );

            foreach ($result->violations as $violation) {
                $valid = false;

                event(new IntegrityViolationDetected($table, $violation['id'], $violation['reason'], $violation['expected'], $violation['actual']));
            }

            if (! $result->valid()) {
                $this->table(
                    ['Id', 'Reason', 'Expected', 'Actual'],
                    array_map(fn (array $v): array => [$v['id'], $v['reason'], $v['expected'] ?? '-', $v['actual'] ?? '-'], array_slice($result->violations, 0, 20)),
                );
            }
        }

        $cache->forever(self::CACHE_KEY, ['at' => Date::now()->toIso8601String(), 'valid' => $valid, 'tables' => $report]);

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
