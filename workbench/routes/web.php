<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Workbench only: every dashboard component in both themes. It is the base
// for visual regression screenshots and is never registered by the package.
Route::get('/auditor/_styleguide', fn () => view('styleguide'))->name('workbench.styleguide');
