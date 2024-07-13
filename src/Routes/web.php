<?php

use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Http\Controllers\AuditorController;

Route::group(['prefix' => 'auditor', 'as' => 'auditor.'], function () {
    Route::get('/', [AuditorController::class, 'index'])->name('index');

    Route::group(['prefix' => 'monitoring', 'as' => 'monitoring.'], function () {
        Route::get('/', [AuditorController::class, 'monitoringIndexView'])->name('index');
        Route::get('/data', [AuditorController::class, 'monitoringIndexData'])->name('data');
        Route::get('/{key}/show', [AuditorController::class, 'monitoringDetailView'])->name('detail');
    });

    Route::group(['prefix' => 'model', 'as' => 'model.'], function () {
        Route::get('/', [AuditorController::class, 'modelIndexView'])->name('index');
        Route::get('/data', [AuditorController::class, 'modelIndexData'])->name('data');
    });

    Route::get('/listmodel', [AuditorController::class, 'listmodel'])->name('listmodel');
    Route::get('/listroute', [AuditorController::class, 'listroute'])->name('listroute');
    Route::get('/listmigration', [AuditorController::class, 'listmigration'])->name('listmigration');
});
