<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rembon\LaravelAuditor\Data\EntryData;

final readonly class EntryRecorded
{
    use Dispatchable;

    public function __construct(public EntryData $entry) {}
}
