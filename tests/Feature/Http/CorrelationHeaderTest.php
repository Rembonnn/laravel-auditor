<?php

declare(strict_types=1);

it('sends a fresh correlation id in the response header', function (): void {
    $response = $this->get('/ping', ['X-Request-Id' => 'client-supplied']);

    expect($response->headers->get('X-Request-Id'))->not->toBe('client-supplied')->toHaveLength(26);
});

it('reuses a valid incoming id when trusted', function (): void {
    config(['auditor.http.trust_incoming_correlation_id' => true]);

    $this->get('/ping', ['X-Request-Id' => 'gateway-123'])->assertHeader('X-Request-Id', 'gateway-123');

    expect(entries()->first()->correlation_id)->toBe('gateway-123');
});

it('rejects a malformed incoming id even when trusted', function (): void {
    config(['auditor.http.trust_incoming_correlation_id' => true]);

    $response = $this->get('/ping', ['X-Request-Id' => '<script>alert(1)</script>']);

    expect($response->headers->get('X-Request-Id'))->toHaveLength(26);
});

it('uses a configurable header name, or none', function (): void {
    config(['auditor.http.correlation_header' => 'X-Correlation-Id']);
    $this->get('/ping')->assertHeader('X-Correlation-Id')->assertHeaderMissing('X-Request-Id');

    config(['auditor.http.correlation_header' => null]);
    $this->get('/ping')->assertHeaderMissing('X-Correlation-Id')->assertHeaderMissing('X-Request-Id');
});

it('gives every request its own correlation id', function (): void {
    $this->get('/ping');
    $this->get('/ping');

    expect(entries()->pluck('correlation_id')->unique())->toHaveCount(2);
});
