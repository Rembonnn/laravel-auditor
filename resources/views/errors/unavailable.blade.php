@extends('auditor::errors.minimal')

@section('title', __('auditor::auditor.errors.unavailable_title'))

@section('body')
    <x-auditor::icon name="database" class="size-8 text-warning" />
    <h1 class="text-xl font-semibold">{{ __('auditor::auditor.errors.unavailable_title') }}</h1>
    @if ($reason === 'driver')
        <p class="text-sm text-muted">{{ __('auditor::auditor.errors.unavailable_driver', ['driver' => $driver]) }}</p>
    @else
        <p class="text-sm text-muted">{{ __('auditor::auditor.errors.unavailable_tables') }}</p>
        <pre class="w-full rounded-md border border-line bg-sunken p-3 text-left font-mono text-xs">php artisan auditor:install</pre>
    @endif
@endsection
