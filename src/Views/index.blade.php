@extends('auditor::layout.base')

@section('title', 'Auditor - Dashboard')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-2 text-2xl font-semibold tracking-tight text-gray-700">Overview</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 py-3">
            <a href="{{ $stats['today_activity']['url'] }}"
                class="block w-full p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Today</span> Activity
                </h5>
                <p class="font-normal text-gray-700">Count total of today activities:
                    <br><strong>{{ $stats['today_activity']['count'] }}</strong></p>
            </a>

            <a href="{{ $stats['total_activity']['url'] }}"
                class="block w-full p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Activity
                </h5>
                <p class="font-normal text-gray-700">Count total of entire activities:
                    <br><strong>{{ $stats['total_activity']['count'] }}</strong></p>
            </a>

        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <a href="{{ $stats['total_model']['url'] }}"
                class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Model</h5>
                <p class="font-normal text-gray-700">Count total of models:
                    <br><strong>{{ $stats['total_model']['count'] }}</strong></p>
            </a>

            <a href="{{ $stats['total_migration']['url'] }}"
                class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span>
                    Migration</h5>
                <p class="font-normal text-gray-700">Count total of migrations:
                    <br><strong>{{ $stats['total_migration']['count'] }}</strong></p>
            </a>

            <a href="{{ $stats['total_route']['url'] }}"
                class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Route</h5>
                <p class="font-normal text-gray-700">Count total of routes:
                    <br><strong>{{ $stats['total_route']['count'] }}</strong></p>
            </a>

        </div>
    </div>

    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow mt-10">
        <h5 class="mb-2 text-2xl font-semibold tracking-tight text-gray-700">Today Performance Metrics</h5>
        <canvas class="w-full" id="performanceChart" width="400" height="200"></canvas>
    </div>
    <script>
        var ctx = document.getElementById('performanceChart').getContext('2d');

        var performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($performanceData->pluck('timestamp')),
                datasets: [{
                        label: 'Response Time (ms)',
                        data: @json($performanceData->pluck('response_time')),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: 'CPU Usage (%)',
                        data: @json($performanceData->pluck('cpu_usage')),
                        borderColor: 'rgba(54, 162, 235, 1)',
                        fill: false
                    },
                    {
                        label: 'Memory Usage (MB)',
                        data: @json($performanceData->pluck('memory_usage')),
                        borderColor: 'rgba(153, 102, 255, 1)',
                        fill: false
                    },
                ]
            },
        });
    </script>
@endsection
