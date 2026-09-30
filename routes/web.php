<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Http\Controllers;
use Rembon\LaravelAuditor\Http\Middleware\Authorize;
use Rembon\LaravelAuditor\Http\Middleware\EnsureStorageIsReady;

Route::get('/assets/{file}', Controllers\AssetController::class)
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('assets')
    ->withoutMiddleware([Authorize::class, EnsureStorageIsReady::class]);

Route::get('/', Controllers\OverviewController::class)->name('overview');

Route::get('/entries', [Controllers\EntryController::class, 'index'])->name('entries.index');
Route::get('/entries/{ulid}', [Controllers\EntryController::class, 'show'])->name('entries.show');
Route::get('/entries/{ulid}/peek', [Controllers\EntryController::class, 'peek'])->name('entries.peek');

Route::get('/changes', [Controllers\ChangeController::class, 'index'])->name('changes.index');
Route::get('/changes/{ulid}/peek', [Controllers\ChangeController::class, 'peek'])->name('changes.peek');

Route::get('/models/{type}/{id}', Controllers\ModelHistoryController::class)
    ->where('id', '[^/]+')
    ->name('models.history');

Route::get('/integrity', Controllers\IntegrityController::class)->name('integrity');
Route::get('/search', Controllers\SearchController::class)->name('search');
Route::get('/poll/{scope}', Controllers\PollController::class)->whereIn('scope', ['entries', 'overview'])->name('poll');
Route::get('/export/{scope}', Controllers\ExportController::class)->whereIn('scope', ['entries', 'changes'])->name('export');
