@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <h5 class="mb-2 text-2xl font-semibold tracking-tight text-gray-700">Overview</h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 py-3">
            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Today</span> Activity</h5>
                <p class="font-normal text-gray-700">Count total of today activities: <br><strong>0</strong></p>
            </a>

            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Past 30 Days</span>
                    Activity</h5>
                <p class="font-normal text-gray-700">Count total of past 30 days activities: <br><strong>0</strong></p>
            </a>

            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Activity</h5>
                <p class="font-normal text-gray-700">Count total of entire activities: <br><strong>0</strong></p>
            </a>

        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Model</h5>
                <p class="font-normal text-gray-700">Count total of models: <br><strong>0</strong></p>
            </a>

            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span>
                    Migration</h5>
                <p class="font-normal text-gray-700">Count total of migrations: <br><strong>0</strong></p>
            </a>

            <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">

                <h5 class="mb-2 text-2xl tracking-tight text-gray-700"><span class="text-indigo-700">Total</span> Route</h5>
                <p class="font-normal text-gray-700">Count total of routes: <br><strong>0</strong></p>
            </a>

        </div>
    </div>
@endsection
