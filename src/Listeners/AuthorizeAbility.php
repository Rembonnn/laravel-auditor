<?php

namespace Rembon\LaravelAuditor\Listeners;

use Rembon\LaravelAuditor\Contracts\Auditor;
use Illuminate\Auth\Access\Events\GateEvaluated;

class AuthorizeAbility
{
    /**
     * @param Auditor $auditor
     * @return void
     */
    public function __construct(private Auditor $auditor) {}

    /**
     * @param GateEvaluated $event
     * @return void
     */
    public function handle(GateEvaluated $event): void
    {
        $this->auditor->addAbility($event->ability, $event->result);
    }
}
