<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Enums;

enum EntryType: string
{
    case Http = 'http';
    case Job = 'job';
    case Command = 'command';
    case Other = 'other';
}
