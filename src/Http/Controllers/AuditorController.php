<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Rembon\LaravelAuditor\Models\Audit;
use Rembon\LaravelAuditor\Services\DataTableService;

class AuditorController extends BaseController
{
    public function index(): View
    {
        return view('auditor::index');
    }

    public function monitoringIndexView(): View
    {
        return view('auditor::monitoring.index');
    }

    public function monitoringIndexData(Request $request)
    {
        if ($request->ajax()) {

            $customableField = [
                ['field' => 'request_time', 'format' => fn ($time) => sprintf("%.2f", $time).'s']
            ];

            return (new DataTableService())
                ->setCustomableField($customableField)
                ->setupDataTables(Audit::orderBy('created_at', 'desc')->get(), ['view' => route('auditor.monitoring.detail', 'key')]);
        }
    }

    public function monitoringDetailView(string|array|int $key): View
    {
        $auditorData = Audit::find($key);

        return view('auditor::monitoring.detail', compact('auditorData'));
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
