<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('boots the service provider', function (): void {
    expect(config('auditor.enabled'))->toBeTrue();
});

it('records exactly one entry per request with its details', function (): void {
    $response = $this->get('/ping?page=2', ['User-Agent' => 'PestBrowser/1.0'])->assertOk();

    expect(entries('http'))->toHaveCount(1);

    $entry = entries('http')->first();

    expect($entry->type)->toBe(EntryType::Http)
        ->and($entry->name)->toBe('ping')
        ->and($entry->http_method)->toBe('GET')
        ->and($entry->url)->toBe('http://localhost/ping?page=2')
        ->and($entry->status_code)->toBe(200)
        ->and($entry->failed)->toBeFalse()
        ->and($entry->ip)->toBe('127.0.0.1')
        ->and($entry->user_agent)->toBe('PestBrowser/1.0')
        ->and($entry->duration_ms)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($entry->route_action)->toBe('Closure')
        ->and($entry->hostname)->toBe(gethostname())
        ->and($entry->completed_at)->not->toBeNull()
        ->and($entry->ulid)->toHaveLength(26)
        ->and($entry->correlation_id)->toBe($response->headers->get('X-Request-Id'));
});

it('records the authenticated user polymorphically (B3)', function (): void {
    $user = User::make();

    $this->actingAs($user)->get('/ping')->assertOk();

    expect(entries('http')->first())
        ->user_type->toBe($user->getMorphClass())
        ->user_id->toBe((string) $user->id)
        ->guard->toBe('web');
});

it('works without LARAVEL_START and measures with hrtime (B8)', function (): void {
    expect(defined('LARAVEL_START'))->toBeFalse();

    $this->get('/ping')->assertOk();

    expect(entries('http')->first()->duration_ms)->toBeInt();
});

it('marks server errors as failed', function (): void {
    $this->get('/boom')->assertStatus(500);

    expect(entries('http')->first())->status_code->toBe(500)->failed->toBeTrue();
});

it('stores properties and tags added during the request', function (): void {
    $this->post('/posts', ['title' => 'Hello'])->assertCreated();

    expect(entries('http')->first())
        ->properties->toBe(['source' => 'test'])
        ->tags->toBe(['posts', 'create'])
        ->model_changes_count->toBe(1);
});

it('records unnamed routes with a null name', function (): void {
    $this->get('/unnamed')->assertOk();

    expect(entries('http')->first()->name)->toBeNull();
});

it('redacts sensitive query parameters in the stored URL', function (): void {
    $this->get('/ping?token=abc123&page=1')->assertOk();

    expect(entries('http')->first()->url)->toBe('http://localhost/ping?token=%5BREDACTED%5D&page=1');
});

it('can anonymize or skip the ip and user agent', function (): void {
    config(['auditor.http.capture.anonymize_ip' => true, 'auditor.http.capture.user_agent' => false]);

    $this->get('/ping', ['REMOTE_ADDR' => '203.0.113.42'])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])->get('/ping')->assertOk();

    expect(entries('http')->last())->ip->toBe('203.0.113.0')->user_agent->toBeNull();
});

it('does not record anything when disabled', function (): void {
    config(['auditor.enabled' => false]);

    $this->post('/posts', ['title' => 'Hello'])->assertCreated();

    expect(entries('http'))->toBeEmpty()->and(changes())->toBeEmpty();
});

it('ignores the current entry but keeps its model changes', function (): void {
    $this->get('/ignored')->assertOk();

    expect(entries('http'))->toBeEmpty()
        ->and(changes())->toHaveCount(1)
        ->and(changes()->first()->entry_id)->toBeNull();
});

it('applies Auditor::filter()', function (): void {
    Auditor::filter(fn ($entry): bool => $entry->name !== 'ping');

    $this->get('/ping')->assertOk();
    $this->get('/unnamed')->assertOk();

    expect(entries('http'))->toHaveCount(1)->and(entries('http')->first()->name)->toBeNull();
});

it('falls back to safe defaults when an older published config lacks keys', function (): void {
    // mergeConfigFrom() only merges top-level keys: a published file from an
    // older version replaces the whole "http" array.
    config(['auditor.http' => ['enabled' => true]]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
        ->get('/ping', ['User-Agent' => 'Old/1.0', 'X-Request-Id' => 'client'])
        ->assertOk()
        ->assertHeaderMissing('X-Request-Id');

    expect(entries('http')->sole())
        ->ip->toBe('203.0.113.42')
        ->user_agent->toBe('Old/1.0')
        ->input->toBeNull();
});

it('does not record the IP when capture is off', function (): void {
    config(['auditor.http.capture.ip' => false]);

    $this->get('/ping');

    expect(entries('http')->sole()->ip)->toBeNull();
});
