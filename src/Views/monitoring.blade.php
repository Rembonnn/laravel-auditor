@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-2 text-2xl font-semibold tracking-tight text-gray-700">Monitoring</h5>
        <x-auditor::data-table
            :id="'users-table'"
            :title="'DataTables'"
            :columns="['No', 'Name', 'Email', 'Action']"
            :ajax-url="route('auditor.getMonitoringData')"
            :column-defs="[
                ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex'],
                ['data' => 'name', 'name' => 'name'],
                ['data' => 'email', 'name' => 'email'],
                ['data' => 'action', 'name' => 'action', 'orderable' => false, 'searchable' => false],
            ]"
        />
    </div>
@endsection
