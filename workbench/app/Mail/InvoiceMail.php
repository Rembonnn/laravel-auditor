<?php

declare(strict_types=1);

namespace Workbench\App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Workbench\App\Models\Order;

class InvoiceMail extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invoice '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Thanks for your order.</p>');
    }
}
