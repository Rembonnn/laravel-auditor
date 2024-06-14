<?php

namespace Rembon\LaravelAuditor\Listeners;

use Rembon\LaravelAuditor\Contracts\Auditor;
use Illuminate\Notifications\Events\NotificationSent;

class AuthorizeNotification
{
    /**
     * @param Auditor $auditor
     * @return void
     */
    public function __construct(private Auditor $auditor) {}

    /**
     * @param NotificationSent $event
     * @return void
     */
    public function handle(NotificationSent $event): void
    {
        $this->auditor->addNotification($event->notification, $event->channel);
    }
}
