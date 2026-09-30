<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Http\Controllers\ExportController;

beforeEach(fn () => Auditor::auth(fn (): true => true));

it('streams entries as CSV following the active filters', function (): void {
    $this->post('/posts', ['title' => 'A']);
    $this->get('/ping');

    $response = $this->get('/auditor/export/entries?type=http&changes=1');
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('auditor-entries-')
        ->and(substr_count(trim($csv), "\n"))->toBe(1)
        ->and($csv)->toContain('posts.store')->not->toContain(',ping,');
});

it('streams changes as CSV', function (): void {
    $this->post('/posts', ['title' => 'Exported']);

    expect($this->get('/auditor/export/changes')->streamedContent())->toContain('Exported')->toContain('title');
});

it('neutralises spreadsheet formulas (CSV injection)', function (): void {
    $this->post('/posts', ['title' => '=HYPERLINK("http://evil","x")']);

    $csv = $this->get('/auditor/export/changes')->streamedContent();

    expect(ExportController::safe('=1+1'))->toBe("'=1+1")
        ->and(ExportController::safe('+1'))->toBe("'+1")
        ->and(ExportController::safe('-1'))->toBe("'-1")
        ->and(ExportController::safe('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(ExportController::safe("\tx"))->toBe("'\tx")
        ->and(ExportController::safe('safe'))->toBe('safe')
        ->and(ExportController::safe(null))->toBe('');

    expect(ExportController::safe('{"title":"=HYPERLINK"}'))->toBe('{"title":"=HYPERLINK"}');
    expect($csv)->not->toMatch('/(^|,)=HYPERLINK/m');
});
