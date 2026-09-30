@extends('auditor::layout', [
    'nonce' => null, 'brandName' => 'Acme', 'brandLogo' => null, 'environment' => 'local', 'environmentTone' => 'neutral',
    'themeDefault' => 'system', 'pollInterval' => 0, 'timezone' => null, 'integrityFailed' => false, 'version' => 'dev',
])

@php
    use Rembon\LaravelAuditor\Enums\ChangeEvent;
    use Rembon\LaravelAuditor\Enums\EntryType;
    use Rembon\LaravelAuditor\Models\ModelChange;
    $change = new ModelChange([
        'event' => ChangeEvent::Updated,
        'old_values' => ['title' => 'Old title', 'meta' => ['lang' => 'en', 'tags' => ['a']], 'price' => 10, 'password' => '[REDACTED]', 'notes' => ''],
        'new_values' => ['title' => 'New title', 'meta' => ['lang' => 'id', 'tags' => ['a']], 'price' => null, 'password' => '[REDACTED]', 'notes' => 'Filled'],
    ]);
@endphp

@section('title', 'Styleguide')

@section('breadcrumb')
    <li class="font-medium">Styleguide</li>
@endsection

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Styleguide</h1>
    <div class="grid gap-6">
        <x-auditor::card title="Buttons & badges">
            <div class="flex flex-wrap items-center gap-2 p-4">
                <button class="btn btn-primary">Primary</button>
                <button class="btn">Default</button>
                <button class="btn btn-ghost">Ghost</button>
                <x-auditor::kbd>⌘</x-auditor::kbd><x-auditor::kbd>K</x-auditor::kbd>
                @foreach (['success', 'warning', 'danger', 'info', 'neutral', 'accent'] as $tone)
                    <x-auditor::badge :tone="$tone" icon="circle">{{ $tone }}</x-auditor::badge>
                @endforeach
                @foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method)<x-auditor::method-badge :method="$method" />@endforeach
                @foreach (EntryType::cases() as $type)<x-auditor::type-badge :type="$type" />@endforeach
                @foreach (ChangeEvent::cases() as $event)<x-auditor::event-badge :event="$event" />@endforeach
                <x-auditor::env-badge environment="production" tone="danger" />
                <x-auditor::env-badge environment="staging" tone="warning" />
            </div>
        </x-auditor::card>
        <x-auditor::card title="Values">
            <div class="flex flex-wrap items-center gap-4 p-4">
                <x-auditor::value :value="null" /><x-auditor::value :value="true" /><x-auditor::value :value="''" />
                <x-auditor::value :value="42" /><x-auditor::value value="[REDACTED]" /><x-auditor::value value="plain text" />
            </div>
        </x-auditor::card>
        <x-auditor::card title="Diff"><div class="p-4"><x-auditor::diff :change="$change" /></div></x-auditor::card>
        <x-auditor::card title="JSON tree"><div class="p-4"><x-auditor::json-tree :data="['order_id' => 42, 'channel' => 'web', 'items' => [['sku' => 'BOOK-1', 'qty' => 2]], 'secret' => '[REDACTED]', 'flag' => false, 'none' => null]" /></div></x-auditor::card>
        <x-auditor::card title="Charts">
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <x-auditor::sparkline :series="[1, 3, 2, 5, 8, 6, 4, 7, 9, 3]" label="Sparkline" class="text-accent" />
                <x-auditor::bar-chart :series="['http' => [3, 5, 2, 8], 'job' => [1, 2, 1, 3], 'command' => [0, 1, 0, 0]]" :labels="['00:00', '01:00', '02:00', '03:00']" :tones="['http' => 'neutral', 'job' => 'info', 'command' => 'accent']" title="Chart" />
            </div>
        </x-auditor::card>
        <x-auditor::card title="Empty & loading">
            <x-auditor::empty-state icon="list" title="No entries yet">Entries appear as soon as a request runs.</x-auditor::empty-state>
            <div class="p-4"><x-auditor::skeleton :lines="3" /></div>
        </x-auditor::card>
    </div>
@endsection
