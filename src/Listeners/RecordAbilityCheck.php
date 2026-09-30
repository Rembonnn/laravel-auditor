<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Data\AbilityCheck;
use Rembon\LaravelAuditor\Support\Values;

final readonly class RecordAbilityCheck
{
    public function __construct(private Auditor $auditor) {}

    public function handle(GateEvaluated $event): void
    {
        if (! config('auditor.listeners.gate', true)) {
            return;
        }

        $this->auditor->guard(function () use ($event): void {
            $this->auditor->recorder()->recordAbility(new AbilityCheck(
                ability: $event->ability,
                result: $event->result === null ? null : (bool) $event->result,
                arguments: array_values(array_map(self::compact(...), (array) $event->arguments)),
            ));
        });
    }

    /**
     * Models become {type, id}; other objects their class; long strings are
     * truncated. Arguments have no name, so strings are kept as is.
     */
    private static function compact(mixed $argument): mixed
    {
        return match (true) {
            $argument instanceof Model => ['type' => $argument->getMorphClass(), 'id' => Values::toString($argument->getKey())],
            is_object($argument) => $argument::class,
            is_array($argument) => '[array]',
            is_string($argument) => Str::limit($argument, 255),
            default => $argument,
        };
    }
}
