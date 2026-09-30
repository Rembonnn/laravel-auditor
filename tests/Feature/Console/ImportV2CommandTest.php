<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

beforeEach(function (): void {
    Schema::create('audits', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('url');
        $table->dateTime('datetime');
        $table->double('request_time');
        $table->string('route')->nullable();
        $table->json('abilities')->nullable();
        $table->json('emails')->nullable();
        $table->json('models')->nullable();
        $table->json('notifications')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });
    Schema::create('performances', fn (Blueprint $table) => $table->id());

    DB::table('audits')->insert([
        'user_id' => 7,
        'url' => 'https://app.test/orders',
        'datetime' => '2024-07-01 10:00:00',
        'request_time' => 0.256,
        'route' => 'orders.index',
        'abilities' => json_encode(['view-orders' => true, 'delete-orders' => false, 0 => 'leaked@mail.test']),
        'emails' => json_encode(['Subject: hi\r\n\r\nraw body with token=abc']),
        'models' => json_encode(['App\\Models\\Order' => [['id' => 1, 'total' => 5], [['id' => 1], ['id' => 2]]]]),
        'notifications' => json_encode(['n-1' => ['notification' => 'App\\Notifications\\Shipped', 'channel' => 'mail']]),
        'properties' => json_encode(['source' => 'web']),
        'created_at' => '2024-07-01 10:00:00',
        'updated_at' => '2024-07-01 10:00:00',
    ]);
    DB::table('audits')->insert([
        'user_id' => null, 'url' => 'https://app.test/', 'datetime' => '2024-07-01 11:00:00',
        'request_time' => 0.01, 'route' => 'unknown', 'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('imports v2 audits without raw emails', function (): void {
    $this->artisan('auditor:import-v2', ['--user-model' => User::class])->assertSuccessful();

    $entries = entries('http');
    $first = $entries->first();

    expect($entries)->toHaveCount(2)
        ->and($first->url)->toBe('https://app.test/orders')
        ->and($first->name)->toBe('orders.index')
        ->and($first->user_type)->toBe((new User)->getMorphClass())
        ->and($first->user_id)->toBe('7')
        ->and($first->duration_ms)->toBe(256)
        ->and($first->started_at->format('Y-m-d H:i:s'))->toBe('2024-07-01 10:00:00')
        ->and($first->models_accessed)->toBe(['App\\Models\\Order' => ['ids' => ['1', '2'], 'count' => 2]])
        ->and($first->abilities)->toHaveCount(2)
        ->and($first->denied_abilities_count)->toBe(1)
        ->and($first->notifications[0]['notification'])->toBe('App\\Notifications\\Shipped')
        ->and($first->properties)->toBe(['source' => 'web'])
        ->and($first->tags)->toBe(['imported-v2'])
        ->and($first->mails)->toBeNull()
        ->and(json_encode($first->getAttributes()))->not->toContain('raw body')->not->toContain('leaked@mail.test')
        ->and($entries->last()->name)->toBeNull()
        ->and($entries->last()->user_id)->toBeNull();
});

it('skips rows that were already imported', function (): void {
    $this->artisan('auditor:import-v2')->assertSuccessful();
    $this->artisan('auditor:import-v2')->expectsOutputToContain('2 already imported')->assertSuccessful();

    expect(entries('http'))->toHaveCount(2);
});

it('drops the old tables after confirmation', function (): void {
    $this->artisan('auditor:import-v2', ['--drop-old' => true])
        ->expectsConfirmation('Drop the v2 "audits" and "performances" tables? This cannot be undone.', 'yes')
        ->assertSuccessful();

    expect(Schema::hasTable('audits'))->toBeFalse()->and(Schema::hasTable('performances'))->toBeFalse();
});

it('does nothing without a v2 table', function (): void {
    Schema::drop('audits');

    $this->artisan('auditor:import-v2')->expectsOutputToContain('No v2')->assertSuccessful();
});
