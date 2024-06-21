<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Contracts\View\View;

class AuditorController extends BaseController
{
    public function index(): View
    {
        return view('auditor::index');
    }
}
