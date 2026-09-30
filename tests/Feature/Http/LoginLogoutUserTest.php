<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('attributes a login request to the user who logged in', function (): void {
    $user = User::make();

    $this->post("/login/{$user->id}")->assertOk();

    expect(entries('http')->first()->user_id)->toBe((string) $user->id);
});

it('keeps the user on a logout request', function (): void {
    $user = User::make();

    $this->actingAs($user)->post('/logout')->assertOk();

    expect(entries('http')->first()->user_id)->toBe((string) $user->id);
});

it('records guests without a user', function (): void {
    $this->get('/ping');

    expect(entries('http')->first())->user_id->toBeNull()->user_type->toBeNull();
});
