<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

it('does not capture input by default', function (): void {
    $this->post('/posts', ['title' => 'Hi', 'password' => 'p'])->assertCreated();

    expect(entries()->first()->input)->toBeNull();
});

it('captures redacted input when enabled', function (): void {
    config(['auditor.http.capture.input' => true]);

    $this->post('/posts', [
        'title' => 'Hi',
        'password' => 'secret',
        'nested' => ['api_key' => 'k', 'ok' => 1],
        'avatar' => UploadedFile::fake()->create('me.png', 3),
    ])->assertCreated();

    expect(entries()->first()->input)->toMatchArray([
        'title' => 'Hi',
        'password' => '[REDACTED]',
        'nested' => ['api_key' => '[REDACTED]', 'ok' => '1'],
        'avatar' => ['file' => 'me.png', 'size' => 3072],
    ]);
});
