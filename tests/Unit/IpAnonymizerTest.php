<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Support\IpAnonymizer;

it('anonymizes IP addresses', function (?string $ip, ?string $expected): void {
    expect(IpAnonymizer::anonymize($ip))->toBe($expected);
})->with([
    ['203.0.113.42', '203.0.113.0'],
    ['10.1.2.3', '10.1.2.0'],
    ['2001:db8:85a3:8d3:1319:8a2e:370:7348', '2001:db8:85a3::'],
    ['::1', '::'],
    ['not-an-ip', null],
    ['', null],
    [null, null],
]);
