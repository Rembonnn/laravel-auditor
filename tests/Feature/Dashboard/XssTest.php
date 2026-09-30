<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;

it('S4: escapes attacker-controlled data everywhere', function (): void {
    Auditor::auth(fn (): true => true);
    $payload = '<script>alert(1)</script>';

    $this->get('/'.rawurlencode($payload), ['User-Agent' => $payload]);
    $this->get('/ping?q='.rawurlencode($payload), ['User-Agent' => $payload]);
    config(['auditor.http.capture.input' => true]);
    $this->post('/posts', ['title' => $payload, 'body' => '"><img src=x onerror=alert(2)>']);

    $entry = Entry::query()->where('name', 'posts.store')->first();
    $pages = [
        '/auditor',
        '/auditor/entries',
        '/auditor/entries/'.$entry->ulid,
        '/auditor/entries/'.$entry->ulid.'/peek',
        '/auditor/changes',
        '/auditor/search?q='.rawurlencode($payload),
        '/auditor/entries?q='.rawurlencode($payload),
    ];

    foreach ($pages as $page) {
        $content = $this->get($page)->assertOk()->getContent();

        expect($content)->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<img src=x onerror')
            ->not->toContain('"><img');
    }
});
