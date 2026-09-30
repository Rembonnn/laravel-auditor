<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Storage;

use Psr\Log\LoggerInterface;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;

/**
 * Writes one log record per entry / model change, with the data as context
 * (a JSON line with a JSON formatter).
 */
final readonly class LogStorage implements Storage
{
    public function __construct(private LoggerInterface $logger) {}

    public function store(?EntryData $entry, array $changes = []): void
    {
        foreach ($changes as $change) {
            $this->logger->info('auditor.model_change', $change->toArray());
        }

        if ($entry?->isComplete()) {
            $this->logger->info('auditor.entry', $entry->toArray());
        }
    }
}
