<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Data;

use Rembon\LaravelAuditor\Support\Values;

/**
 * Metadata of a sent mail. The body is never part of it.
 */
final readonly class MailRecord
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        public ?string $mailable,
        public ?string $subject,
        public array $to = [],
        public array $cc = [],
        public array $bcc = [],
    ) {}

    /**
     * @return array{mailable: string|null, subject: string|null, to: list<string>, cc: list<string>, bcc: list<string>}
     */
    public function toArray(): array
    {
        return [
            'mailable' => $this->mailable,
            'subject' => $this->subject,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::nullableString($data, 'mailable'),
            Values::nullableString($data, 'subject'),
            Values::strings($data, 'to'),
            Values::strings($data, 'cc'),
            Values::strings($data, 'bcc'),
        );
    }
}
