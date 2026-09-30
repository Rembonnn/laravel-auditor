<?php

declare(strict_types=1);
use Rembon\LaravelAuditor\Tests\Fixtures\WelcomeMail;

it('B1/S2: records mail metadata into mails, never the body or tokens', function (): void {
    $this->post('/mail')->assertOk();

    $entry = entries('http')->sole();

    expect(sortedKeys($entry->mails))->toBe(sortedKeys([[
        'mailable' => WelcomeMail::class,
        'subject' => 'Welcome aboard',
        'to' => ['jane@example.com'],
        'cc' => ['boss@example.com'],
        'bcc' => [],
    ]]))
        ->and($entry->abilities)->toBeNull()
        ->and(json_encode($entry->getAttributes()))->not->toContain('super-secret-token')->not->toContain('Reset:');
});

it('can hash recipients', function (): void {
    config(['auditor.mail.hash_recipients' => true]);

    $this->post('/mail')->assertOk();

    expect(entries('http')->sole()->mails[0]['to'])->toBe([hash('sha256', 'jane@example.com')]);
});

it('can skip recipients entirely', function (): void {
    config(['auditor.mail.record_recipients' => false]);

    $this->post('/mail')->assertOk();

    expect(entries('http')->sole()->mails[0])->toMatchArray(['to' => [], 'cc' => [], 'bcc' => []]);
});

it('can be switched off', function (): void {
    config(['auditor.listeners.mail' => false]);

    $this->post('/mail')->assertOk();

    expect(entries('http')->sole()->mails)->toBeNull();
});
