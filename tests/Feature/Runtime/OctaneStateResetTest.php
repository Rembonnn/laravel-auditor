<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('B9/R6: does not leak state between requests in one worker', function (): void {
    $first = app(Recorder::class);
    $first->start(EntryType::Http); // a request that never terminated
    $first->withProperty('secret_of_request_one', 'x');

    // What Octane does before every request.
    app()->forgetScopedInstances();

    $second = app(Recorder::class);

    expect($second)->not->toBe($first)->and($second->current())->toBeNull();

    $this->actingAs(User::make())->get('/ping');

    expect(entries('http')->sole()->properties)->toBeNull();
});
