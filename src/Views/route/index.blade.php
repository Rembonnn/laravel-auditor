@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Route List</h5>
        <x-auditor::data-table
            :id="'route-table'"
            :title="'DataTables'"
            :columns="['method', 'uri', 'name']"
            :ajax-url="route('auditor.route.data')"
            :column-defs="[
                ['data' => 'method', 'name' => 'method'],
                ['data' => 'uri', 'name' => 'uri'],
                ['data' => 'name', 'name' => 'name'],
            ]"
        />
    </div>
@endsection
