<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\StrictCsp;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

/*
 * Real browser (Playwright via pestphp/pest-plugin-browser). Run with
 * `composer test:browser` after `npx playwright install chromium`.
 */

beforeEach(function (): void {
    Auditor::auth(fn (): true => true);

    $user = Auditor::withoutAuditing(fn (): User => User::make(['name' => 'Budi Santoso']));
    $this->actingAs($user)->post('/posts', ['title' => 'Hello']);
    $this->actingAs($user)->put('/posts/'.Post::query()->value('id'), ['title' => 'Hello again']);
    $this->actingAs($user)->delete('/posts/'.Post::query()->value('id'));
    $this->actingAs($user)->post('/mail');
    auth()->logout();

    $this->entry = Entry::query()->where('name', 'posts.store')->firstOrFail();
});

dataset('pages', fn (): array => [
    'overview' => '/auditor',
    'entries' => '/auditor/entries',
    'entry' => fn (): string => '/auditor/entries/'.Entry::query()->where('name', 'posts.store')->value('ulid'),
    'changes' => '/auditor/changes',
    'integrity' => '/auditor/integrity',
]);

it('applies dark mode before first paint, without flash', function (): void {
    $page = visit('/auditor')->inDarkMode();

    $page->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('document.documentElement.dataset.theme', 'system')
        ->assertNoJavaScriptErrors();
})->group('browser');

it('cycles light / dark / system and remembers the choice', function (): void {
    $page = visit('/auditor')->inLightMode();

    $page->assertScript('document.documentElement.classList.contains("dark")', false);
    $page->script('window.Alpine.store("theme").set("dark")');
    $page->assertScript('document.documentElement.classList.contains("dark")', true);

    $page->navigate('/auditor/entries')
        ->assertScript('document.documentElement.dataset.theme', 'dark')
        ->assertScript('document.documentElement.classList.contains("dark")', true);
})->group('browser');

it('passes axe (no serious or critical issues) in both themes', function (string $url): void {
    visit($url)->inLightMode()->assertNoAccessibilityIssues(1);
    visit($url)->inDarkMode()->assertNoAccessibilityIssues(1);
})->with('pages')->group('browser');

it('has no JavaScript errors on any page', function (string $url): void {
    visit($url)->assertNoJavaScriptErrors();
    visit($url)->on()->mobile()->assertNoJavaScriptErrors();
})->with('pages')->group('browser');

it('works under a strict script CSP without unsafe-eval or unsafe-inline', function (): void {
    Auditor::useNonce('test-nonce');
    app('router')->pushMiddlewareToGroup('web', StrictCsp::class);

    visit('/auditor/entries')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertScript('typeof window.Alpine', 'object');
})->group('browser');

it('opens the command palette and jumps to a model history', function (): void {
    $post = Post::withTrashed()->firstOrFail();

    visit('/auditor/entries')
        ->keys('#main', ['Meta+k'])
        ->type('#palette-input', 'Post#'.$post->id)
        ->assertSee('Model history')
        ->keys('#palette-input', ['Enter'])
        ->assertPathContains('/auditor/models/');
})->group('browser');

it('navigates rows with j / k and peeks with space', function (): void {
    visit('/auditor/entries')
        ->keys('#main', ['j', ' '])
        ->assertSee('Open full details')
        ->keys('#main', ['Escape'])
        ->keys('#main', ['?'])
        ->assertSee('Keyboard shortcuts');
})->group('browser');

it('lays the entries out as cards on mobile without horizontal scroll', function (): void {
    visit('/auditor/entries')->on()->mobile()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertSee('posts.store');
})->group('browser');
