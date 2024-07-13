@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Migration</h5>
        <x-auditor::data-table
            :id="'migration-table'"
            :title="'DataTables'"
            :columns="['migration', 'batch', 'Action']"
            :ajax-url="route('auditor.migration.data')"
            :column-defs="[
                ['data' => 'migration', 'name' => 'migration'],
                ['data' => 'batch', 'name' => 'batch'],
                ['data' => 'action', 'name' => 'action', 'orderable' => false, 'searchable' => false],
            ]"
        />
    </div>
@endsection
