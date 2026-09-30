<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Support\Dashboard\Assets;

it('serves built assets with long-lived cache headers', function (string $entry, string $type): void {
    $file = Assets::manifest()[$entry]['file'];

    $this->get('/auditor/assets/'.$file)
        ->assertOk()
        ->assertHeader('Content-Type', $type)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
})->with([
    ['resources/css/auditor.css', 'text/css; charset=utf-8'],
    ['resources/js/auditor.js', 'text/javascript; charset=utf-8'],
]);

it('refuses path traversal and unknown files', function (string $path): void {
    $this->get('/auditor/assets/'.$path)->assertNotFound();
})->with([
    '../../.env',
    '..%2F..%2F.env',
    '....//....//composer.json',
    'manifest.json',
    'theme-init.js',
    'nope.css',
    '%2e%2e%2fcomposer.json',
]);

it('inlines the theme bootstrap with the CSP nonce and no external requests', function (): void {
    Auditor::auth(fn (): true => true);
    Auditor::useNonce('abc123');

    $html = $this->get('/auditor')->assertOk()->getContent();

    expect($html)->toContain('<script nonce="abc123">(function(){')
        ->and(strpos($html, 'auditor.theme'))->toBeLessThan(strpos($html, 'rel="stylesheet"'))
        ->and($html)->not->toMatch('/(src|href)="https?:\/\/(?!localhost)[^"]+\.(js|css|woff2?)"/')
        ->and($html)->not->toContain('onclick=')
        ->and($html)->not->toContain('onchange=');
});

it('applies a valid accent colour only', function (): void {
    Auditor::auth(fn (): true => true);

    config(['auditor.dashboard.accent' => '#6366f1']);
    $this->get('/auditor')->assertSee('--accent:#6366f1', false);

    config(['auditor.dashboard.accent' => 'red;}</style><script>x()</script>']);
    $this->get('/auditor')->assertDontSee('x()</script>', false)->assertDontSee('--accent:red', false);
});
