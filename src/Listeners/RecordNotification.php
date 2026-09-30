<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Data\NotificationRecord;
use Rembon\LaravelAuditor\Support\Values;

final readonly class RecordNotification
{
    public function __construct(private Auditor $auditor) {}

    public function handle(NotificationSent $event): void
    {
        if (! config('auditor.listeners.notifications', true)) {
            return;
        }

        $this->auditor->guard(function () use ($event): void {
            $notifiable = $event->notifiable;

            $this->auditor->recorder()->recordNotification(new NotificationRecord(
                notification: $event->notification::class,
                channel: is_string($event->channel) ? $event->channel : get_debug_type($event->channel),
                notifiableType: match (true) {
                    $notifiable instanceof Model => $notifiable->getMorphClass(),
                    is_object($notifiable) => $notifiable::class,
                    default => null,
                },
                notifiableId: $notifiable instanceof Model ? Values::toString($notifiable->getKey()) : null,
            ));
        });
    }
}
