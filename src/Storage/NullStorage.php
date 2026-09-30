<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Storage;

use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;

final class NullStorage implements Storage
{
    public function store(?EntryData $entry, array $changes = []): void
    {
        //
    }
}
