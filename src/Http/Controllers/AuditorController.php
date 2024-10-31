<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
                'count' => Audit::whereDay(column: 'created_at', operator: now()->format('d'))->count(),
                'url' => route(name: 'auditor.monitoring.index', parameters: ['d' => now()->format('d')])
            ],

            'total_activity' => [
                'count' => Audit::count(),
                'url' => route(name: 'auditor.monitoring.index')
            ],

            'total_model' => [
                'count' => count(value: $this->getAllModels()),
                'url' => route(name: 'auditor.model.index')
            ],

            'total_migration' => [
                'count' => \Illuminate\Support\Facades\DB::table('migrations')->count(),
                'url' => route(name: 'auditor.migration.index')
            ],

            'total_route' => [
                'count' => count(value: $this->getAllRouteList()),
                'url' => route(name: 'auditor.route.index')
            ]

        ];

        $performanceData = Performance::all();

        return view(view: 'auditor::index', data: compact('stats', 'performanceData'));
    }

    /**
     * @return View
     */
    public function monitoringIndexView(): View
    {
        return view(view: 'auditor::monitoring.index');
    }

    /**
     * @param string|array|int $key
     * @return View
     */
    public function monitoringDetailView(string|array|int $key): View
    {
        $auditorData = Audit::find($key);

        return view(view: 'auditor::monitoring.detail', data: compact('auditorData'));
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

        $monitoringData = Audit::orderBy(column: 'created_at', direction: 'desc')
            ->when(value: $request->has('d'), callback: function ($query) use ($request): Builder {
                return $query->whereDay(column: 'created_at', operator: $request->get('d'));
            })
            ->get();

        return (new DataTableService())
            ->setCustomableField(fields: $customableField)
            ->setupDataTables(data: $monitoringData, actionUrl: [
                'view' => route(name: 'auditor.monitoring.detail', parameters: 'key')
            ]);
    }

    /**
     * @return View
     */
    public function modelIndexView(): View
    {
        return view(view: 'auditor::model.index');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function modelIndexData(Request $request): JsonResponse
    {
        if ($request->ajax()) {

            $data = collect($this->getAllModels())
                ->map(callback: fn ($modelName): array =>
                    array(
                        'model_classname' => "App/Models/$modelName::class",
                        'model_name' => $modelName,
                        'guard_name' => config('auth.defaults.guard')
                    )
                );

            return (new DataTableService())
                ->setupDataTables(data: $data);
        }
    }

    /**
     * @return View
     */
    public function migrationIndexView(): View
    {
        return view(view: 'auditor::migration.index');
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
                ->setupDataTables(data: $data, actionUrl: [
                    'view' => route(name: 'auditor.migration.detail', parameters: 'key'),
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

        return view(view: 'auditor::migration.detail', data: compact('migrationName', 'migrationDetail', 'migrationPath'));
    }

    /**
     * @return View
     */
    public function routeIndexView(): View
    {
        return view(view: 'auditor::route.index');
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
