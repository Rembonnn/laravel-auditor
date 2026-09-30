<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Tests\Fixtures\Secret;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('S3: never stores password hashes or remember tokens', function (): void {
    $user = User::make(['password' => 'plain-password']);
    $user->update(['password' => 'another-password', 'remember_token' => 'tok']);

    $raw = json_encode(changes()->map->only('old_values', 'new_values'));

    expect(changes()->first()->new_values['password'])->toBe('[REDACTED]')
        ->and(changes()->last()->old_values['password'])->toBe('[REDACTED]')
        ->and(changes()->last()->new_values)->toBe(['password' => '[REDACTED]', 'remember_token' => '[REDACTED]'])
        ->and($raw)->not->toContain('$2y$')->not->toContain('plain-password')->not->toContain('tok"');
});

it('never stores encrypted casts in plaintext, old or new', function (): void {
    $secret = Secret::query()->create(['label' => 'stripe', 'value' => 'sk_live_123', 'payload' => ['k' => 'v']]);

    $secret->update(['value' => 'sk_live_456', 'payload' => ['k' => 'w']]);

    $raw = json_encode(changes()->map->only('old_values', 'new_values'));

    expect(changes()->last()->old_values)->toBe(['value' => '[REDACTED]', 'payload' => '[REDACTED]'])
        ->and($raw)->not->toContain('sk_live')->not->toContain('"k"');
});

it('redacts hidden attributes and sensitive keys', function (): void {
    Secret::query()->create(['label' => 'x', 'internal_note' => 'hidden note', 'api_token' => 'abc']);

    expect(changes()->first()->new_values)->toMatchArray([
        'label' => 'x',
        'internal_note' => '[REDACTED]',
        'api_token' => '[REDACTED]',
    ]);
});
