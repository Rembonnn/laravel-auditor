<?php

declare(strict_types=1);

namespace Workbench\App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Workbench\App\Models\Order;

class OrderShipped extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Order shipped')->line('Your order is on its way.');
    }
}
