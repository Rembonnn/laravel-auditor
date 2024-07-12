@extends('auditor::layout.base')

@section('content')
    <div class="flex flex-col gap-3">
        <div class="p-5">
            <h1 class="text-3xl font-bold">Laravel Auditor | Monitoring</h1>
        </div>
        <div class="md:grid md:grid-cols-[1fr_5fr] flex flex-col">
        <x-auditor::sidebar />
            <div class="container mx-auto">
                <div class="rounded-lg shadow p-6">
                    <x-auditor::overview />
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
            </div>
        </div>
    </div>
@endsection
