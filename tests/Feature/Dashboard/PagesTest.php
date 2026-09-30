<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Dashboard\ModelRef;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

beforeEach(function (): void {
    Auditor::auth(fn (): true => true);

    $this->user = Auditor::withoutAuditing(fn (): User => User::make(['name' => 'Budi Santoso']));
    $this->actingAs($this->user)->post('/posts', ['title' => 'Hello', 'body' => 'World']);
    $this->post = Post::query()->first();
    $this->actingAs($this->user)->delete("/posts/{$this->post->id}");
    $this->actingAs($this->user)->post('/mail');
    auth()->logout();
});

it('renders the overview with stats, lists and the setup checklist', function (string $range): void {
    $this->get('/auditor?range='.$range)
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('Budi Santoso')
        ->assertSee('Finish setting up')
        ->assertSee('delete');
})->with(['1h', '24h', '7d', '30d', 'bogus']);

it('renders the entries list with filters and chips', function (): void {
    $this->get('/auditor/entries')->assertOk()->assertSee('posts.store')->assertSee('Budi Santoso');

    $this->get('/auditor/entries?type=http&denied=1&range=24h')
        ->assertOk()
        ->assertSee('posts.destroy')
        ->assertDontSee('posts.store')
        ->assertSee('Remove filter Denied', false);

    $this->get('/auditor/entries?status=4xx')->assertOk()->assertSee('posts.destroy')->assertDontSee('posts.store');
    $this->get('/auditor/entries?q=zzz-nothing')->assertOk()->assertSee('No entries match these filters.');
    $this->get('/auditor/entries?user='.$this->user->getMorphClass().':'.$this->user->id)->assertOk()->assertSee('posts.store');
    $this->get('/auditor/entries?tag=posts')->assertOk()->assertSee('posts.store')->assertDontSee('posts.destroy');
    $this->get('/auditor/entries?changes=1&failed=0&method=post')->assertOk();
    $this->get('/auditor/entries?from=2000-01-01&to=not-a-date')->assertOk();
});

it('paginates with a cursor', function (): void {
    config(['auditor.dashboard.per_page' => 2]);

    $response = $this->get('/auditor/entries')->assertOk()->assertSee('rel="next"', false);

    preg_match('/href="([^"]+cursor=[^"]+)"/', $response->getContent(), $m);

    $this->get(html_entity_decode($m[1]))->assertOk()->assertSee('rel="prev"', false);
});

it('renders an entry with all its tabs', function (): void {
    $entry = Entry::query()->where('name', 'posts.store')->first();

    $this->get('/auditor/entries/'.$entry->ulid)
        ->assertOk()
        ->assertSee($entry->correlation_id)
        ->assertSee('Hello')
        ->assertSee('source')
        ->assertSee('Timeline');

    $mail = Entry::query()->where('name', 'mail')->first();
    $this->get('/auditor/entries/'.$mail->ulid)->assertOk()->assertSee('Welcome aboard');

    $this->get('/auditor/entries/does-not-exist')->assertNotFound();
});

it('renders the quick peek partials', function (): void {
    $entry = Entry::query()->where('name', 'posts.destroy')->first();
    $change = ModelChange::query()->first();

    $this->get('/auditor/entries/'.$entry->ulid.'/peek')->assertOk()->assertSee('delete')->assertDontSee('<html', false);
    $this->get('/auditor/changes/'.$change->ulid.'/peek')->assertOk()->assertSee('Hello');
});

it('renders the changes list and filters it', function (): void {
    $this->get('/auditor/changes')->assertOk()->assertSee('Post')->assertSee('title');
    $this->get('/auditor/changes?event=deleted')->assertOk()->assertSee('deleted');
    $this->get('/auditor/changes?model='.ModelRef::encode($this->post->getMorphClass()))->assertOk()->assertSee('#'.$this->post->id);
});

it('shows a helpful empty state without changes', function (): void {
    ModelChange::query()->delete();

    $this->get('/auditor/changes')->assertOk()->assertSee('use Auditable;');
});

it('renders the model history timeline', function (): void {
    Auditor::withoutAuditing(fn () => $this->post->delete());
    $url = ModelRef::url($this->post->getMorphClass(), (string) $this->post->id);

    $this->get($url)->assertOk()->assertSee('History of Post #'.$this->post->id)->assertSee('Hello')->assertSee('soft deleted');
    $this->get($url.'?event=created')->assertOk();
    $this->get(ModelRef::url('App\\Nope', '1'))->assertNotFound();
});

it('renders the integrity page in every state', function (): void {
    $this->get('/auditor/integrity')->assertOk()->assertSee('Integrity is not enabled');

    config(['auditor.integrity.enabled' => true, 'auditor.integrity.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'auditor.integrity.seal_delay' => 0]);
    $this->get('/auditor/integrity')->assertOk()->assertSee('Not verified yet')->assertSee('auditor:seal has not run yet');

    $this->travel(1)->seconds();
    $this->artisan('auditor:seal');
    $this->artisan('auditor:verify');
    $this->get('/auditor/integrity')->assertOk()->assertSee('Chain valid');

    DB::table('auditor_entries')->orderBy('id')->limit(1)->update(['status_code' => 999]);
    $this->artisan('auditor:verify');
    $this->get('/auditor/integrity')->assertOk()->assertSee('Chain broken')->assertSee('How to investigate');
});

it('explains when the dashboard cannot read the storage', function (): void {
    config(['auditor.storage.driver' => 'log']);
    $this->get('/auditor')->assertStatus(503)->assertSee('"log"');

    config(['auditor.storage.driver' => 'database', 'auditor.storage.database.tables.entries' => 'missing_table']);
    $this->get('/auditor/entries')->assertStatus(503)->assertSee('auditor:install');
});

it('renders in Indonesian', function (): void {
    app()->setLocale('id');

    $this->get('/auditor')->assertOk()->assertSee('Ringkasan');
});
