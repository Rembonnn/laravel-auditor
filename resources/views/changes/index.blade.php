@extends('auditor::layout')

@php
    $chips = $filters->chips('auditor.changes.index');
    $columns = ['event', 'model', 'key', 'attributes', 'user', 'time'];
@endphp

@section('title', __('auditor::auditor.changes.title'))

@section('breadcrumb')
    <li class="font-medium">{{ __('auditor::auditor.changes.title') }}</li>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ __('auditor::auditor.changes.title') }}</h1>
        <a class="btn" href="{{ $filters->url('auditor.export', ['scope' => 'changes']) }}"><x-auditor::icon name="download" />{{ __('auditor::auditor.common.export_csv') }}</a>
    </div>

    <form method="get" action="{{ route('auditor.changes.index') }}" class="card mb-4 flex flex-wrap items-center gap-2 p-2" role="search">
        @foreach ($filters->toArray() as $key => $value)
            @unless (in_array($key, ['q', 'event', 'model'], true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endunless
        @endforeach
        <label for="changes-filter" class="sr-only">{{ __('auditor::auditor.common.filter') }}</label>
        <div class="flex min-w-60 flex-1 items-center gap-2 px-2">
            <x-auditor::icon name="filter" class="size-4 text-muted" />
            <input id="changes-filter" data-filter-input name="q" value="{{ $filters->get('q') }}" type="search" class="h-8 w-full bg-transparent outline-none" placeholder="{{ __('auditor::auditor.filters.changes_placeholder') }}">
        </div>
        <label class="sr-only" for="filter-event">{{ __('auditor::auditor.filters.event') }}</label>
        <select id="filter-event" name="event" class="input w-auto">
            <option value="">{{ __('auditor::auditor.changes.all_events') }}</option>
            @foreach (\Rembon\LaravelAuditor\Enums\ChangeEvent::cases() as $event)
                <option value="{{ $event->value }}" @selected($filters->get('event') === $event->value)>{{ __('auditor::auditor.events.'.$event->value) }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filter-model">{{ __('auditor::auditor.filters.model') }}</label>
        <select id="filter-model" name="model" class="input w-auto max-w-56">
            <option value="">{{ __('auditor::auditor.changes.all_models') }}</option>
            @foreach ($models as $model)
                @php($encoded = \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::encode($model))
                <option value="{{ $encoded }}" @selected($filters->get('model') === $encoded)>{{ class_basename($model) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">{{ __('auditor::auditor.common.apply') }}</button>
    </form>

    @if ($chips !== [])
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach ($chips as $chip)<x-auditor::filter-chip :chip="$chip" />@endforeach
            <a href="{{ route('auditor.changes.index') }}" class="link text-sm">{{ __('auditor::auditor.common.reset') }}</a>
        </div>
    @endif

    <div class="card overflow-hidden">
        @if ($changes->isEmpty())
            @if ($filters->active())
                <x-auditor::empty-state icon="filter" :title="__('auditor::auditor.changes.empty_filtered')"><a href="{{ route('auditor.changes.index') }}" class="link">{{ __('auditor::auditor.common.reset') }}</a></x-auditor::empty-state>
            @else
                @php($snippet = "use Rembon\\LaravelAuditor\\Traits\\Auditable;\n\nclass Post extends Model\n{\n    use Auditable;\n}")
                <x-auditor::empty-state icon="arrow-left-right" :title="__('auditor::auditor.changes.empty_title')">
                    <p>{{ __('auditor::auditor.changes.empty_body') }}</p>
                    <div class="relative mt-3 text-left">
                        <pre class="overflow-x-auto rounded-md border border-line bg-sunken p-3 font-mono text-xs">{{ $snippet }}</pre>
                        <x-auditor::copy :value="$snippet" class="absolute right-2 top-2" />
                    </div>
                    <a href="https://github.com/Rembonnn/laravel-auditor#readme" class="link mt-3 inline-block" target="_blank" rel="noopener noreferrer">{{ __('auditor::auditor.nav.docs') }} →</a>
                </x-auditor::empty-state>
            @endif
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead><tr>@foreach ($columns as $column)<th scope="col" @class(['text-right' => $column === 'time'])>{{ __('auditor::auditor.changes.col_'.$column) }}</th>@endforeach</tr></thead>
                    <tbody data-rows>
                        @foreach ($changes as $change)
                            @php($attributes = $change->changedAttributes())
                            <tr data-row data-peek-url="{{ route('auditor.changes.peek', $change->ulid) }}" data-href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($change->auditable_type, $change->auditable_id) }}" aria-selected="false">
                                <td><x-auditor::event-badge :event="$change->event" /></td>
                                <td title="{{ $change->auditable_type }}">{{ class_basename($change->auditable_type) }}</td>
                                <td><a class="link font-mono" href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($change->auditable_type, $change->auditable_id) }}">#{{ $change->auditable_id }}</a></td>
                                <td class="text-xs">
                                    <span class="font-mono">{{ implode(', ', array_slice($attributes, 0, 3)) }}</span>
                                    @if (count($attributes) > 3)<span class="badge tone-neutral">{{ __('auditor::auditor.changes.more', ['count' => count($attributes) - 3]) }}</span>@endif
                                </td>
                                <td><x-auditor::user-chip :user="$users->get($change->user_type, $change->user_id)" :os-user="$change->entry?->os_user" /></td>
                                <td class="text-right text-muted"><x-auditor::relative-time :date="$change->created_at" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-line md:hidden">
                @foreach ($changes as $change)
                    <li>
                        <a href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($change->auditable_type, $change->auditable_id) }}" class="block min-h-11 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2"><x-auditor::event-badge :event="$change->event" /><span class="truncate">{{ class_basename($change->auditable_type) }} <span class="font-mono text-muted">#{{ $change->auditable_id }}</span></span></span>
                                <x-auditor::relative-time :date="$change->created_at" class="text-xs text-muted" />
                            </div>
                            <div class="mt-1 truncate font-mono text-xs text-muted">{{ implode(', ', $change->changedAttributes()) }}</div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <x-auditor::pagination :paginator="$changes" />
        @endif
    </div>
@endsection
