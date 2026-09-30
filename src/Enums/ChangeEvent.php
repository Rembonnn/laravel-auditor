<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Enums;

enum ChangeEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';

    /**
     * Map an Eloquent observer method name (e.g. "forceDeleted") to an event.
     */
    public static function fromObserverMethod(string $method): self
    {
        return match ($method) {
            'forceDeleted' => self::ForceDeleted,
            default => self::from($method),
        };
    }
}
