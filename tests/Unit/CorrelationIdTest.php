<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Rembon\LaravelAuditor\Support\CorrelationId;

it('generates ULIDs', function (): void {
    expect(CorrelationId::generate())->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('validates the format', function (mixed $id, bool $valid): void {
    expect(CorrelationId::isValid($id))->toBe($valid);
})->with([
    ['abc-DEF_123', true],
    [str_repeat('a', 64), true],
    [str_repeat('a', 65), false],
    ['', false],
    ['has space', false],
    ["new\nline", false],
    ['<script>', false],
    [null, false],
    [123, false],
]);

it('ignores the incoming header unless trusted', function (): void {
    $request = Request::create('/', server: ['HTTP_X_REQUEST_ID' => 'from-client']);

    expect(CorrelationId::fromRequest($request))->not->toBe('from-client');

    config(['auditor.http.trust_incoming_correlation_id' => true]);

    expect(CorrelationId::fromRequest($request))->toBe('from-client');
});

it('ignores a trusted header with an invalid format', function (): void {
    config(['auditor.http.trust_incoming_correlation_id' => true]);

    $request = Request::create('/', server: ['HTTP_X_REQUEST_ID' => str_repeat('x', 100)]);

    expect(CorrelationId::fromRequest($request))->toHaveLength(26);
});

it('stores the id in hidden context', function (): void {
    CorrelationId::set('abc');

    expect(CorrelationId::current())->toBe('abc')
        ->and(Context::get(CorrelationId::CONTEXT_KEY))->toBeNull();
});
