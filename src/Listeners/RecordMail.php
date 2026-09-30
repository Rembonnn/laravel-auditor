<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Data\MailRecord;
use Rembon\LaravelAuditor\Support\Values;
use Symfony\Component\Mime\Address;

/**
 * Records mail metadata only. The body is never stored.
 */
final readonly class RecordMail
{
    public function __construct(private Auditor $auditor) {}

    public function handle(MessageSent $event): void
    {
        if (! config('auditor.listeners.mail', true)) {
            return;
        }

        $this->auditor->guard(function () use ($event): void {
            $message = $event->message;
            $data = $event->data;

            $this->auditor->recorder()->recordMail(new MailRecord(
                mailable: Values::nullableString($data, '__laravel_mailable') ?? Values::nullableString($data, '__laravel_notification'),
                subject: $message->getSubject(),
                to: self::addresses($message->getTo()),
                cc: self::addresses($message->getCc()),
                bcc: self::addresses($message->getBcc()),
            ));
        });
    }

    /**
     * @param  array<Address>  $addresses
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        if (! config('auditor.mail.record_recipients', true)) {
            return [];
        }

        $hash = (bool) config('auditor.mail.hash_recipients', false);

        return array_values(array_map(
            fn (Address $address): string => $hash
                ? hash('sha256', Str::lower($address->getAddress()))
                : $address->getAddress(),
            $addresses,
        ));
    }
}
