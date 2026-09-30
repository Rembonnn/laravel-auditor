<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Rembon\LaravelAuditor\Integrity\RowHasher;
use Rembon\LaravelAuditor\Integrity\Sealer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'auditor:seal')]
final class SealCommand extends Command
{
    public const string CACHE_KEY = 'auditor:integrity:last_seal';

    protected $signature = 'auditor:seal {--limit= : Maximum number of rows to seal per table}';

    protected $description = 'Compute the tamper-evident hash chain for rows that are not sealed yet';

    public function handle(Cache $cache): int
    {
        if (! config('auditor.integrity.enabled')) {
            $this->components->warn('Integrity is disabled (AUDITOR_INTEGRITY=false). Nothing to seal.');

            return self::SUCCESS;
        }

        try {
            $sealer = new Sealer(RowHasher::fromConfig(), $cache);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $sealed = $sealer->sealAll($limit);

        $cache->forever(self::CACHE_KEY, ['at' => Date::now()->toIso8601String(), 'sealed' => $sealed]);

        foreach ($sealed as $table => $count) {
            $this->components->twoColumnDetail($table, "{$count} sealed");
        }

        return self::SUCCESS;
    }
}
