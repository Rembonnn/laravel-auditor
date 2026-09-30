<?php

declare(strict_types=1);

use Illuminate\Support\Lottery;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

beforeEach(fn () => config(['auditor.http.sample_rate' => 0.0]));

it('drops plain requests when sampled out', function (): void {
    $this->get('/ping')->assertOk();

    expect(entries('http'))->toBeEmpty();
});

it('always records requests with model changes', function (): void {
    $this->post('/posts', ['title' => 'Kept'])->assertCreated();

    expect(entries('http'))->toHaveCount(1)->and(changes()->first()->entry_id)->toBe(entries('http')->first()->id);
});

it('always records requests with denied abilities', function (): void {
    $post = Post::query()->create(['title' => 'x']);
    Entry::query()->delete();

    $this->actingAs(User::make())->delete("/posts/{$post->id}")->assertForbidden();

    expect(entries('http'))->toHaveCount(1)->and(entries('http')->first()->denied_abilities_count)->toBe(1);
});

it('records everything at rate 1', function (): void {
    config(['auditor.http.sample_rate' => 1.0]);

    $this->get('/ping');
    $this->get('/ping');

    expect(entries('http'))->toHaveCount(2);
});

it('keeps a request when the lottery wins and drops it when it loses', function (): void {
    config(['auditor.http.sample_rate' => 0.25]);

    Lottery::alwaysWin();
    $this->get('/ping');
    expect(entries('http'))->toHaveCount(1);

    Lottery::alwaysLose();
    $this->get('/ping');
    expect(entries('http'))->toHaveCount(1);

    Lottery::determineResultNormally();
});

it('never consults the lottery at rate 1 or above', function (float $rate): void {
    config(['auditor.http.sample_rate' => $rate]);
    Lottery::alwaysLose();

    $this->get('/ping');

    expect(entries('http'))->toHaveCount(1);
    Lottery::determineResultNormally();
})->with([1.0, 1.5]);
