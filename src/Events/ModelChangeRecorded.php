<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rembon\LaravelAuditor\Data\ModelChangeData;

final readonly class ModelChangeRecorded
{
    use Dispatchable;

    public function __construct(public ModelChangeData $change) {}
}
