@extends('auditor::layout.base')

@section('content')
    <div class="flex flex-col gap-3">
        <div class="p-5">
            <h1 class="text-3xl font-bold">Laravel Auditor | List Model</h1>
        </div>
        <div class="md:grid md:grid-cols-[1fr_5fr] flex flex-col">
        <x-sidebar />
            <div class="container mx-auto">
                <div class="rounded-lg shadow p-6">
                    <div class="w-full mb-2 bg-white px-5 py-2">
                        <h1 class="text-2xl font-bold mb-4">Overview</h1>
                    </div>
                    <x-overview />
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
