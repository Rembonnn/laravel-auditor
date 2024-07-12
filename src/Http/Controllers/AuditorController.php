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
                    $btn = '<a href="javascript:void(0)" class="edit btn btn-primary btn-sm">Edit</a>';
                    $btn .= '<a href="javascript:void(0)" class="delete btn btn-danger btn-sm">Delete</a>';
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    }
}
