@extends('auditor::layout.base')

@section('content')
    <div class="flex flex-col gap-3">
        <div class="p-5">
            <h1 class="text-3xl font-bold">Laravel Auditor</h1>
        </div>
        <div class="md:grid md:grid-cols-[1fr_5fr] flex flex-col">
            <div class="flex h-screen">
                <div class="text-white w-64 p-4">
                    <ul class="space-y-2">
                        <li class="w-full flex gap-2">
                            <i class="fa-home"></i>
                            <a href="#" class="w-full text-gray-800 hover:text-gray-400 p-2 rounded md:text-lg text-sm">Dashboard</a>
                        </li>
                        <li class="w-full flex gap-2">
                            <i class="fas-fa-computer"></i>
                            <a href="#" class="w-full text-gray-800 hover:text-gray-400 p-2 rounded md:text-lg text-sm">Monitoring</a>
                        </li>
                        <li class="w-full flex gap-2">
                            <i class="fas-fa-table"></i>
                            <a href="#" class="w-full text-gray-800 hover:text-gray-400 p-2 rounded md:text-lg text-sm">List Model</a>
                        </li>
                        <li class="w-full flex gap-2">
                            <i class="fas-fa-table"></i>
                            <a href="#" class="w-full text-gray-800 hover:text-gray-400 p-2 rounded md:text-lg text-sm">List Route</a>
                        </li>
                        <li class="w-full flex gap-2">
                            <i class="fas-fa-table"></i>
                            <a href="#" class="w-full text-gray-800 hover:text-gray-400 p-2 rounded md:text-lg text-sm">List Migration</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="container mx-auto">
                <div class="rounded-lg shadow p-6">
                    <div class="w-full mb-2 bg-white px-5 py-2">
                        <h1 class="text-2xl font-bold mb-4">Overview</h1>
                    </div>
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Jobs Per Minute</h2>
                            <p class="text-3xl font-bold">246</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Jobs Past Hour</h2>
                            <p class="text-3xl font-bold">1,100</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Failed Jobs Past 7 Days</h2>
                            <p class="text-3xl font-bold">0</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Status</h2>
                            <p class="text-3xl font-bold">Active</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Total Proccessed</h2>
                            <p class="text-3xl font-bold">2</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Max Wait Time</h2>
                            <p class="text-3xl font-bold">-</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Max Runtime</h2>
                            <p class="text-3xl font-bold">Default</p>
                        </div>
                        <div class="rounded-lg p-4 hover:bg-gray-200 hover:transition-all hover:ease-in-out cursor-pointer">
                            <h2 class="text-lg font-semibold">Max Troughtput</h2>
                            <p class="text-3xl font-bold">Default</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold mb-2">Current Workload</h2>
                        <div class="bg-white rounded-lg shadow p-4">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4 text-left">Queue</th>
                                        <th class="py-2 px-4 text-left">Jobs</th>
                                        <th class="py-2 px-4 text-left">Processes</th>
                                        <th class="py-2 px-4 text-left">Wait</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-2 px-4">default</td>
                                        <td class="py-2 px-4">200</td>
                                        <td class="py-2 px-4">2</td>
                                        <td class="py-2 px-4">A few seconds</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 px-4">nunomadurohome-xaZQ</td>
                                        <td class="py-2 px-4"></td>
                                        <td class="py-2 px-4"></td>
                                        <td class="py-2 px-4"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
