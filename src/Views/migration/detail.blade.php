@extends('auditor::layout.base')

@section('content')
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow">
        <div class="flex justify-between">
            <h5 class="mb-5 text-2xl font-semibold tracking-tight text-gray-700">Detail Migration</h5>

            <a href="{{ route('auditor.migration.index') }}"
                class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 pt-3 h-11 text-center">Back</a>
        </div>

        <div class="w-full p-4 bg-white border border-gray-200 rounded-lg shadow sm:p-6 md:p-8">
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                    Migration File
                </label>
                <input type="text"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                    value="{{ $migrationName }}" disabled />
            </div>
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                    Migration Path
                </label>
                <input type="text"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                    value="{{ $migrationPath }}" disabled />
            </div>
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">
                    Migration Detail
                </label>
                <div class="relative overflow-x-auto">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3">
                                    #
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    Column Name
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    Column Type
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    Column Size
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($migrationDetail as $detail)
                                <tr class="bg-white border-b">
                                    <td class="px-6 py-4">
                                        {{ $loop->iteration }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $detail['name'] }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $detail['type'] }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $detail['length'] ?? 'Not Set' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
