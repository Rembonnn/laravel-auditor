<?php

use Illuminate\Support\Facades\Route;
use Rembon\LaravelAuditor\Http\Controllers\AuditorController;

Route::group(['prefix' => 'auditor', 'as' => 'auditor.'], function () {
    Route::get('/', [AuditorController::class, 'index'])->name('index');
});
