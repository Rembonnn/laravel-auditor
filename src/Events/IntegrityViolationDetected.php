<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class IntegrityViolationDetected
{
    use Dispatchable;

    public function __construct(
        public string $table,
        public int $id,
        public string $reason,
        public ?string $expectedHash,
        public ?string $actualHash,
    ) {}
}
