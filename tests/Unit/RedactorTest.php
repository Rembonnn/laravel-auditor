<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Tests\Fixtures\Secret;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

beforeEach(fn () => $this->redactor = app(Redactor::class));

it('redacts keys matching wildcard patterns, case-insensitively', function (): void {
    expect($this->redactor->redact([
        'password' => 'a',
        'Password_Confirmation' => 'b',
        'api_token' => 'c',
        'client_secret' => 'd',
        'CVV' => '123',
        'name' => 'Jane',
    ]))->toBe([
        'password' => '[REDACTED]',
        'Password_Confirmation' => '[REDACTED]',
        'api_token' => '[REDACTED]',
        'client_secret' => '[REDACTED]',
        'CVV' => '[REDACTED]',
        'name' => 'Jane',
    ]);
});

it('redacts nested arrays recursively', function (): void {
    expect($this->redactor->redact(['user' => ['profile' => ['secret_answer' => 'x', 'city' => 'Bandung']]]))
        ->toBe(['user' => ['profile' => ['secret_answer' => '[REDACTED]', 'city' => 'Bandung']]]);
});

it('redacts a whole sensitive branch', function (): void {
    expect($this->redactor->redact(['tokens' => ['a', 'b']]))->toBe(['tokens' => '[REDACTED]']);
});

it('uses the configured replacement', function (): void {
    config(['auditor.redaction.replacement' => '***']);

    expect($this->redactor->redact(['pin' => '1234']))->toBe(['pin' => '***']);
});

it('redacts query string values in URLs and keeps the rest intact', function (): void {
    expect($this->redactor->redactUrl('https://x.test/reset?token=abc&email=a%40b.c&user%5Bpassword%5D=p#frag'))
        ->toBe('https://x.test/reset?token=%5BREDACTED%5D&email=a%40b.c&user%5Bpassword%5D=%5BREDACTED%5D#frag')
        ->and($this->redactor->redactUrl('https://x.test/plain'))->toBe('https://x.test/plain')
        ->and($this->redactor->redactUrl('https://x.test/?flag&page=2'))->toBe('https://x.test/?flag&page=2');
});

it('supports a custom redaction callback', function (): void {
    $this->redactor->using(fn (string $key, mixed $value): bool => $key === 'nik' || $value === 'leak-me');

    expect($this->redactor->redact(['nik' => '3201', 'note' => 'leak-me', 'city' => 'Bogor']))
        ->toBe(['nik' => '[REDACTED]', 'note' => '[REDACTED]', 'city' => 'Bogor']);

    $this->redactor->using(null);
});

it('redacts hidden attributes and encrypted casts of models', function (): void {
    $attributes = (new Secret)->forceFill([
        'label' => 'Stripe',
        'value' => 'encrypted-blob',
        'payload' => 'encrypted-array-blob',
        'internal_note' => 'hidden',
    ])->getAttributes();

    expect(app(Redactor::class)->redactModelAttributes(new Secret, $attributes))->toMatchArray([
        'label' => 'Stripe',
        'value' => '[REDACTED]',
        'payload' => '[REDACTED]',
        'internal_note' => '[REDACTED]',
    ]);
});

it('can keep hidden attributes and encrypted casts when configured', function (): void {
    config(['auditor.redaction.redact_hidden_attributes' => false, 'auditor.redaction.redact_encrypted_casts' => false]);

    expect(app(Redactor::class)->redactModelAttributes(new User, ['remember_token' => 'x', 'name' => 'Jane']))
        ->toBe(['remember_token' => '[REDACTED]', 'name' => 'Jane']) // still matches *token*
        ->and(app(Redactor::class)->redactModelAttributes(new Secret, ['internal_note' => 'n']))
        ->toBe(['internal_note' => 'n']);
});
