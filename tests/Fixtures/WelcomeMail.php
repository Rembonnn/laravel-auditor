<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeMail extends Mailable
{
    public function __construct(public string $resetUrl = 'https://example.com/reset?token=super-secret-token') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome aboard');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Reset: '.e($this->resetUrl).'</p>');
    }
}
