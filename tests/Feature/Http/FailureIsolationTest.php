<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Facades\Auditor;

beforeEach(function (): void {
    Auditor::extend('broken', fn (): Storage => new class implements Storage
    {
        public function store(?EntryData $entry, array $changes = []): void
        {
            throw new RuntimeException('storage is down');
        }
    });

    config(['auditor.storage.driver' => 'broken']);
});

it('never breaks the response when storage fails', function (): void {
    config(['auditor.throw_exceptions' => false]);
    Exceptions::fake();

    $this->post('/posts', ['title' => 'Still works'])->assertCreated();

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'storage is down');
});

it('rethrows when throw_exceptions is enabled', function (): void {
    config(['auditor.throw_exceptions' => true]);

    $this->withoutExceptionHandling();

    expect(fn () => Auditor::guard(fn () => throw new RuntimeException('storage is down')))
        ->toThrow(RuntimeException::class, 'storage is down');
});

it('returns the default value when guarded code fails', function (): void {
    config(['auditor.throw_exceptions' => false]);
    Exceptions::fake();

    expect(Auditor::guard(fn () => throw new RuntimeException('x'), 'fallback'))->toBe('fallback');
});
