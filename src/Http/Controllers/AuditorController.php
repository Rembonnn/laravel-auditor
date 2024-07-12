<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Yajra\DataTables\Facades\DataTables;

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

    public function getMonitoringData(Request $request)
    {
        if ($request->ajax()) {
            $data = collect([
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
                ['id' => 3, 'name' => 'Robert Johnson', 'email' => 'robert@example.com'],
                // Tambahkan lebih banyak data dummy di sini
            ]);

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $btn = '<a href="javascript:void(0)" class="focus:outline-none text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-3 py-1.5 mb-2 dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-900 me-2">Edit</a>';
                    $btn .= '<a href="javascript:void(0)" class="focus:outline-none text-white bg-rose-700 hover:bg-rose-800 focus:ring-4 focus:ring-rose-300 font-medium rounded-lg text-sm px-3 py-1.5 me-2 mb-2 dark:bg-rose-600 dark:hover:bg-rose-700 dark:focus:ring-rose-900">Delete</a>';
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    }
}
