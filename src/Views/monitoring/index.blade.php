@extends('auditor::layout.base')

@section('title', 'Auditor - Monitoring')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Monitoring</h5>
        @php
            $ajaxUrl = request()->has('d')
                ? route('auditor.monitoring.data', ['d' => request()->get('d')])
                : route('auditor.monitoring.data');
        @endphp
        <x-auditor::data-table
            :id="'monitoring-table'"
            :title="'DataTables'"
            :columns="['datetime', 'url', 'request_time', 'Action']"
            :ajax-url="$ajaxUrl"
            :column-defs="[
                ['data' => 'datetime', 'name' => 'datetime'],
                ['data' => 'url', 'name' => 'url'],
                ['data' => 'request_time', 'name' => 'request_time'],
                ['data' => 'action', 'name' => 'action', 'orderable' => false, 'searchable' => false],
            ]"
        />
    </div>
@endsection
