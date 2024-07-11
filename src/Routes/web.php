<?php

use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Http\Controllers\AuditorController;

Route::group(['prefix' => 'auditor', 'as' => 'auditor.'], function () {
    Route::get('/', [AuditorController::class, 'index'])->name('index');
    Route::get('/monitoring', [AuditorController::class, 'monitoring'])->name('monitoring');
    Route::get('/listmodel', [AuditorController::class, 'listmodel'])->name('listmodel');
    Route::get('/listroute', [AuditorController::class, 'listroute'])->name('listroute');
    Route::get('/listmigration', [AuditorController::class, 'listmigration'])->name('listmigration');
    Route::get('get-monitoring-data', [AuditorController::class, 'getMonitoringData'])->name('getMonitoringData');
});
