@extends('auditor::layout.base')

@section('title', 'Auditor - Monitoring Detail')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <div class="flex justify-between">
            <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Detail Monitoring</h5>

            <a href="{{ route('auditor.monitoring.index') }}"
                class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 pt-3 h-11 text-center">Back</a>
        </div>

        <div class="w-full p-4 bg-white border border-gray-200 rounded-lg shadow sm:p-6 md:p-8">
            <form class="space-y-6" action="#">
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        User ID
                    </label>
                    <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="{{ $auditorData->user_id ?? '-' }}" disabled />
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        URL
                    </label>
                    <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="{{ $auditorData->url ?? '-' }}" disabled />
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Datetime
                    </label>
                    <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="{{ $auditorData->datetime ?? '-' }}" disabled />
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Request Time
                    </label>
                    <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="{{ $auditorData->request_time . ' seconds' ?? '-' }}" disabled />
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Route
                    </label>
                    <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="{{ $auditorData->route ?? '-' }}" disabled />
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Abilities
                    </label>
                    <textarea
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        rows="{{ $auditorData->abilities->count() < 1 ? '1' : '15' }}" disabled>{{ $auditorData->abilities->count() < 1 ? '-' : $auditorData->abilities }}</textarea>
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Emails Payload
                    </label>
                    <textarea
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        rows="{{ $auditorData->emails->count() < 1 ? '1' : '15' }}" disabled>{{ $auditorData->emails->count() < 1 ? '-' : $auditorData->emails }}</textarea>
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Models
                    </label>
                    @if($auditorData->models->count() < 1)
                        <input type="text"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        value="-" disabled />
                    @else
                        <div class="relative overflow-x-auto">
                            @php
                                $modelData = $auditorData->models->first()[0];
                            @endphp
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        @foreach (array_keys($modelData) as $header)
                                            <th scope="col" class="px-6 py-3">
                                                {{ $header }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="bg-white border-b">
                                        @foreach (array_values($modelData) as $value)
                                            <td class="px-6 py-4">
                                                {{ $value }}
                                            </td>
                                        @endforeach
                                        </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif

                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Notifications Payload
                    </label>
                    <textarea
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        rows="{{ $auditorData->notifications->count() < 1 ? '1' : '8' }}" disabled>{{ $auditorData->notifications->count() < 1 ? '-' : $auditorData->notifications }}</textarea>
                </div>
                <div>
                    <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                        Properties Payload
                    </label>
                    <textarea
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        rows="{{ $auditorData->properties->count() < 1 ? '1' : '8' }}" disabled>{{ $auditorData->properties->count() < 1 ? '-' : $auditorData->properties }}</textarea>
                </div>
            </form>
        </div>

    </div>
@endsection
