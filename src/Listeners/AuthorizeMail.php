<?php

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Rembon\LaravelAuditor\Contracts\Auditor;

class AuthorizeMail
{
    /**
     * @param Auditor $auditor
     * @return void
     */
    public function __construct(private Auditor $auditor) {}

    /**
     * @param MessageSent $event
     * @return void
     */
    public function handle(MessageSent $event): void
    {
        $this->auditor->addMail($event->sent->toString());
    }
}
