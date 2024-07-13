@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Model</h5>
        <x-auditor::data-table
            :id="'model-table'"
            :title="'DataTables'"
            :columns="['model_classname', 'model_name', 'guard_name']"
            :ajax-url="route('auditor.model.data')"
            :column-defs="[
                ['data' => 'model_classname', 'name' => 'model_classname'],
                ['data' => 'model_name', 'name' => 'model_name'],
                ['data' => 'guard_name', 'name' => 'guard_name'],
            ]"
        />
    </div>
@endsection
