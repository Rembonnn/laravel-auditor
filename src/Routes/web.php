<?php

use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Http\Controllers\AuditorController;

if (config('laravel-auditor.views')) {
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

        Route::group(['prefix' => 'migraton', 'as' => 'migration.'], function () {
            Route::get('/', [AuditorController::class, 'migrationIndexView'])->name('index');
            Route::get('/data', [AuditorController::class, 'migrationIndexData'])->name('data');
            Route::get('/{key}/show', [AuditorController::class, 'migrationDetailView'])->name('detail');
        });

        Route::group(['prefix' => 'route', 'as' => 'route.'], function () {
            Route::get('/', [AuditorController::class, 'routeIndexView'])->name('index');
            Route::get('/data', [AuditorController::class, 'routeIndexData'])->name('data');
        });
    });
}
