<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

it('writes entries and changes to a log channel', function (): void {
    config(['auditor.storage.driver' => 'log', 'auditor.storage.log.channel' => 'audit', 'logging.channels.audit' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);

    $this->post('/posts', ['title' => 'Logged'])->assertCreated();

    $records = Log::channel('audit')->getLogger()->getHandlers()[0]->getRecords();
    $messages = array_map(fn ($r) => $r->message, $records);

    expect($messages)->toBe(['auditor.model_change', 'auditor.entry'])
        ->and($records[1]->context['type'])->toBe('http')
        ->and(entries())->toBeEmpty();
});

it('stores nothing with the null driver', function (): void {
    config(['auditor.storage.driver' => 'null']);

    $this->post('/posts', ['title' => 'Nowhere'])->assertCreated();

    expect(entries())->toBeEmpty()->and(changes())->toBeEmpty();
});
