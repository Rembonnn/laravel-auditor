<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Console;

use Illuminate\Console\Command;
use Rembon\LaravelAuditor\Models\Checkpoint;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Settings;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'auditor:prune')]
final class PruneCommand extends Command
{
    protected $signature = 'auditor:prune {--days= : Keep this many days (default: auditor.prune.keep_days)}';

    protected $description = 'Delete audit data older than the retention window';

    public function handle(): int
    {
        $option = $this->option('days');
        $days = is_numeric($option) ? (int) $option : Settings::int('auditor.prune.keep_days', 90);

        if ($days < 1) {
            $this->components->error('--days must be at least 1.');

            return self::FAILURE;
        }

        // Changes first: pruning entries would otherwise null their entry_id.
        foreach (['model_changes' => ModelChange::class, 'entries' => Entry::class] as $table => $model) {
            $boundary = $model::pruneBoundary($days);
            $last = $model::query()->where('id', '<', $boundary)->orderByDesc('id')->first(['id', 'hash']);

            if ($last === null) {
                $this->components->twoColumnDetail($table, '0 pruned');

                continue;
            }

            if (config('auditor.integrity.enabled')) {
                Checkpoint::query()->create([
                    'table' => $table,
                    'last_id' => $last->id,
                    'last_hash' => $last->hash,
                ]);
            }

            $model::$pruneBefore = $boundary;

            try {
                $count = (new $model)->pruneAll();
            } finally {
                $model::$pruneBefore = null;
            }

            $this->components->twoColumnDetail($table, "{$count} pruned");
        }

        return self::SUCCESS;
    }
}
