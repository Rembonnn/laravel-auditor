<?php

declare(strict_types=1);

it('does not record excluded paths (P5)', function (string $path): void {
    $this->get($path);

    expect(entries())->toBeEmpty();
})->with(['/up', '/auditor', '/auditor/entries', '/auditor/assets/auditor.css']);

it('does not record excluded methods', function (): void {
    $this->call('OPTIONS', '/ping');
    $this->call('HEAD', '/ping');

    expect(entries())->toBeEmpty();
});

it('does not record the dashboard on a custom path', function (): void {
    config(['auditor.dashboard.path' => 'admin/audit']);

    $this->get('/admin/audit/entries');

    expect(entries())->toBeEmpty();
});

it('can be disabled for HTTP only', function (): void {
    config(['auditor.http.enabled' => false]);

    $this->get('/ping')->assertOk();

    expect(entries())->toBeEmpty();
});
