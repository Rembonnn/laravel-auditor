<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel DataTables with Tailwind CSS</title>
    <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.dataTables.min.css" rel="stylesheet">
    <link href="https://unpkg.com/tailwindcss@^2.0/dist/tailwind.min.css" rel="stylesheet">
    <link href="{{ asset('css/index.css') }}" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
</head>

<body>
    <div>
        <table class="w-full divide-y divide-gray-200" id="{{ $id }}">
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($columns as $column)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
        </table>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            $('#{{ $id }}').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ $ajaxUrl }}",
                columns: @json($columnDefs),
                dom: '<"flex flex-col sm:flex-row sm:items-center mb-4"<"flex-1"l><"flex-1 flex justify-end"f>>rt<"flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4"ip>',
                renderer: 'bootstrap',
                language: {
                    lengthMenu: 'Show _MENU_ entries',
                    search: 'Search:',
                    searchPlaceholder: 'Search...',
                    paginate: {
                        previous: '<',
                        next: '>',
                    },
                },
                initComplete: function() {
                    $('div.dataTables_length select').addClass(
                        'mt-1 block w-16 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md'
                        );
                    $('div.dataTables_filter input').addClass(
                        'mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md'
                        );
                }
            });
        });

    </script>
</body>

</html>
