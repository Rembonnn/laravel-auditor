<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Tests\Fixtures\Post;

beforeEach(function (): void {
    Auditor::auth(fn (): true => true);
    $this->post('/posts', ['title' => 'Searchable']);
    $this->entry = Entry::query()->where('type', 'http')->first();
    $this->model = Post::query()->first();
});

it('finds an entry by ULID and a correlation id', function (): void {
    $this->getJson('/auditor/search?q='.$this->entry->ulid)->assertOk()->assertJsonFragment(['url' => route('auditor.entries.show', $this->entry->ulid)]);
    $this->getJson('/auditor/search?q='.$this->entry->correlation_id)->assertOk()->assertJsonFragment(['description' => $this->entry->correlation_id]);
});

it('understands Post#12, user:5 and route:name', function (): void {
    $this->getJson('/auditor/search?q=Post%23'.$this->model->id)->assertOk()->assertJsonFragment(['label' => 'Post #'.$this->model->id]);
    $this->getJson('/auditor/search?q='.urlencode($this->model->getMorphClass().':'.$this->model->id))->assertOk()->assertJsonFragment(['label' => 'Post #'.$this->model->id]);
    $this->getJson('/auditor/search?q=user:5')->assertOk()->assertJsonFragment(['url' => route('auditor.entries.index', ['user' => '5'])]);
    $this->getJson('/auditor/search?q=route:posts.store')->assertOk()->assertJsonFragment(['url' => route('auditor.entries.index', ['name' => 'posts.store'])]);
});

it('falls back to name and URL search', function (): void {
    $this->getJson('/auditor/search?q=posts.st')->assertOk()->assertJsonCount(1);
    $this->getJson('/auditor/search?q=x')->assertOk()->assertJsonCount(0);
});

it('counts new entries for live mode', function (): void {
    $latest = Entry::query()->max('id');

    $this->getJson('/auditor/poll/entries?after='.$latest)->assertOk()->assertJson(['count' => 0]);

    $this->get('/ping');

    $this->getJson('/auditor/poll/entries?after='.$latest)->assertJson(['count' => 1]);
    $this->getJson('/auditor/poll/entries?after='.$latest.'&type=job')->assertJson(['count' => 0]);
    $this->getJson('/auditor/poll/overview?after='.$latest)->assertJson(['count' => 1]);
    $this->get('/auditor/poll/other')->assertNotFound();
});
