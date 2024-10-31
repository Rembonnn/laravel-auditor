<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Rembon\LaravelAuditor\Models\Audit;
use Rembon\LaravelAuditor\Models\Performance;
use Rembon\LaravelAuditor\Services\DataTableService;

class AuditorController extends BaseController
{
    /**
     * @return View
     */
    public function index(): View
    {
        $stats = [

            'today_activity' => [
                'count' => Audit::whereDay('created_at', now()->format('d'))->count(),
                'url' => route('auditor.monitoring.index', ['d' => now()->format('d')])
            ],

            'total_activity' => [
                'count' => Audit::count(),
                'url' => route('auditor.monitoring.index')
            ],

            'total_model' => [
                'count' => count($this->getAllModels()),
                'url' => route('auditor.model.index')
            ],

            'total_migration' => [
                'count' => \Illuminate\Support\Facades\DB::table('migrations')->count(),
                'url' => route('auditor.migration.index')
            ],

            'total_route' => [
                'count' => count($this->getAllRouteList()),
                'url' => route('auditor.route.index')
            ]

        ];

        $performanceData = Performance::all();

        return view('auditor::index', compact('stats', 'performanceData'));
    }

    /**
     * @return View
     */
    public function monitoringIndexView(): View
    {
        return view('auditor::monitoring.index');
    }

    /**
     * @param string|array|int $key
     * @return View
     */
    public function monitoringDetailView(string|array|int $key): View
    {
        $auditorData = Audit::find($key);

        return view('auditor::monitoring.detail', compact('auditorData'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function monitoringIndexData(Request $request)
    {
        $customableField = [
            ['field' => 'request_time', 'format' => fn ($time) => sprintf("%.2f", $time).'s']
        ];

        $monitoringData = Audit::orderBy('created_at', 'desc')
            ->when($request->has('d'), function ($query) use ($request) {
                return $query->whereDay('created_at', $request->get('d'));
            })
            ->get();

        return (new DataTableService())
            ->setCustomableField($customableField)
            ->setupDataTables($monitoringData, [
                'view' => route('auditor.monitoring.detail', 'key')
            ]);
    }

    /**
     * @return View
     */
    public function modelIndexView(): View
    {
        return view('auditor::model.index');
    }

    /**
     * @param Request $request
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

    /**
     * @return View
     */
    public function migrationIndexView(): View
    {
        return view('auditor::migration.index');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function migrationIndexData(Request $request): JsonResponse
    {
        if ($request->ajax()) {

            $data = \Illuminate\Support\Facades\DB::table('migrations')->orderBy('id', 'desc')->get();

            return (new DataTableService())
                ->setupDataTables($data, [
                    'view' => route('auditor.migration.detail', 'key'),
                    'viewKey' => 'migration'
                ]);
        }
    }

    /**
     * @param string|array|int $key
     * @return View
     */
    public function migrationDetailView(string|array|int $key): View
    {
        $migrationName = $key;
        $migrationPath = "./database/migrations/$migrationName";
        $migrationDetail = $this->accessMigrationData($migrationName.'.php');

        return view('auditor::migration.detail', compact('migrationName', 'migrationDetail', 'migrationPath'));
    }

    /**
     * @return View
     */
    public function routeIndexView(): View
    {
        return view('auditor::route.index');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function routeIndexData(Request $request): JsonResponse
    {
        if ($request->ajax()) {

            $data = collect($this->getAllRouteList());

            return (new DataTableService())
                ->setupDataTables($data);
        }
    }
}
