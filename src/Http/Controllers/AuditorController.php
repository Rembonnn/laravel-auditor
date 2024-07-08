<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Contracts\View\View;

class AuditorController extends BaseController
{
    public function index(): View
    {
        return view('auditor::index');
    }

    public function monitoring(): View 
    {
        return view('auditor::monitoring');
    }

    public function listmodel(): View 
    {
        return view('auditor::list-model');
    }

    public function listroute(): View 
    {
        return view('auditor::list-route');
    }

    public function listmigration(): View 
    {
        return view('auditor::list-migration');
    }
}
