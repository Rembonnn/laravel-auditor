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
    /**
     * @return View
     */
    public function index(): View
    {
        return view('auditor::index');
    }

    /**
     * @return View
     */
    public function monitoringIndexView(): View
    {
        return view('auditor::monitoring.index');
    }

    /**
     * @return View
     */
    public function monitoringDetailView(string|array|int $key): View
    {
        $auditorData = Audit::find($key);

        return view('auditor::monitoring.detail', compact('auditorData'));
    }

    /**
     * @return JsonResponse
     */
    public function monitoringIndexData(Request $request): JsonResponse
    {
        if ($request->ajax()) {

            $customableField = [
                ['field' => 'request_time', 'format' => fn ($time) => sprintf("%.2f", $time).'s']
            ];

            return (new DataTableService())
                ->setCustomableField($customableField)
                ->setupDataTables(Audit::orderBy('created_at', 'desc')->get(), [
                    'view' => route('auditor.monitoring.detail', 'key')
                ]);
        }
    }

    /**
     * @return View
     */
    public function modelIndexView(): View
    {
        return view('auditor::model.index');
    }

    /**
     * @return JsonResponse
     */
    public function modelIndexData(Request $request): JsonResponse
    {
        if ($request->ajax()) {

            $data = collect($this->getAllModels())
                ->map(fn ($modelName) =>
                    array(
                        'model_classname' => "App/Models/$modelName::class",
                        'model_name' => $modelName,
                        'guard_name' => config('auth.defaults.guard')
                    )
                );

            return (new DataTableService())
                ->setupDataTables($data);
        }
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
