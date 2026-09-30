@extends('auditor::layout')

@php
    $present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class;
    $chips = $filters->chips('auditor.entries.index');
    $presets = [
        'today' => ['range' => 'today'],
        'denied' => ['denied' => '1'],
        'failed' => ['failed' => '1'],
        'changes' => ['changes' => '1'],
    ];
    if ($currentUser instanceof \Illuminate\Database\Eloquent\Model) {
        $presets['mine'] = ['user' => $currentUser->getMorphClass().':'.$currentUser->getKey()];
    }
    $columns = ['type', 'name', 'user', 'status', 'duration', 'time'];
@endphp

@section('title', __('auditor::auditor.entries.title'))

@section('breadcrumb')
    <li class="font-medium">{{ __('auditor::auditor.entries.title') }}</li>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ __('auditor::auditor.entries.title') }}</h1>
        <div class="flex items-center gap-2">
            <a class="btn" href="{{ $filters->url('auditor.export', ['scope' => 'entries']) }}"><x-auditor::icon name="download" />{{ __('auditor::auditor.common.export_csv') }}</a>
            <details class="relative" x-data="columns" data-table="entries">
                <summary class="btn list-none"><x-auditor::icon name="columns" />{{ __('auditor::auditor.common.columns') }}</summary>
                <div class="raised absolute right-0 z-20 mt-1 w-48 p-2">
                    @foreach ($columns as $column)
                        <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-sunken">
                            <input type="checkbox" :checked="isVisible('{{ $column }}')" @change="toggle('{{ $column }}')">
                            {{ __('auditor::auditor.entries.col_'.$column) }}
                        </label>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2" x-data="filters" data-scope="entries" data-saved-label="{{ __('auditor::auditor.common.view_saved') }}">
        @foreach ($presets as $key => $query)
            @php($active = collect($query)->every(fn ($v, $k) => $filters->get($k) === $v))
            <a href="{{ route('auditor.entries.index', $query) }}" class="btn {{ $active ? 'border-accent text-accent' : '' }}" @if ($active) aria-current="true" @endif>{{ __('auditor::auditor.presets.'.$key) }}</a>
        @endforeach
        <details class="relative">
            <summary class="btn btn-ghost list-none"><x-auditor::icon name="star" />{{ __('auditor::auditor.common.saved_views') }}<x-auditor::icon name="chevron-down" /></summary>
            <div class="raised absolute left-0 z-20 mt-1 w-64 p-2">
                <template x-for="view in views" :key="view.name">
                    <div class="flex items-center justify-between rounded px-2 py-1.5 text-sm hover:bg-sunken">
                        <a :href="href(view)" class="link truncate" x-text="view.name"></a>
                        <button type="button" class="text-muted hover:text-danger" @click="remove(view.name)" aria-label="{{ __('auditor::auditor.common.remove') }}"><x-auditor::icon name="x" class="size-3.5" /></button>
                    </div>
                </template>
                <p x-show="!hasViews" class="px-2 py-1.5 text-sm text-muted">{{ __('auditor::auditor.common.no_saved_views') }}</p>
                <div class="mt-2 border-t border-line pt-2">
                    <button type="button" x-show="!saving" class="btn btn-ghost w-full" @click="startSaving()"><x-auditor::icon name="plus" />{{ __('auditor::auditor.common.save_view') }}</button>
                    <form x-show="saving" x-cloak class="flex gap-1" @submit.prevent="save()">
                        <label for="view-name" class="sr-only">{{ __('auditor::auditor.common.view_name') }}</label>
                        <input id="view-name" x-ref="name" x-model="name" class="input" placeholder="{{ __('auditor::auditor.common.view_name') }}">
                        <button type="submit" class="btn btn-primary">{{ __('auditor::auditor.common.save') }}</button>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <form method="get" action="{{ route('auditor.entries.index') }}" class="card mb-4 flex flex-wrap items-center gap-2 p-2" role="search">
        @foreach ($filters->toArray() as $key => $value)
            @unless ($key === 'q')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endunless
        @endforeach
        <label for="entries-filter" class="sr-only">{{ __('auditor::auditor.common.filter') }}</label>
        <div class="flex min-w-60 flex-1 items-center gap-2 px-2">
            <x-auditor::icon name="filter" class="size-4 text-muted" />
            <input id="entries-filter" data-filter-input name="q" value="{{ $filters->get('q') }}" type="search" class="h-8 w-full bg-transparent outline-none"
                   placeholder="{{ __('auditor::auditor.filters.placeholder') }}">
        </div>
        <label class="sr-only" for="filter-type">{{ __('auditor::auditor.filters.type') }}</label>
        <select id="filter-type" name="type" class="input w-auto">
            <option value="">{{ __('auditor::auditor.filters.type') }}: {{ __('auditor::auditor.filters.any') }}</option>
            @foreach (\Rembon\LaravelAuditor\Enums\EntryType::cases() as $type)
                <option value="{{ $type->value }}" @selected($filters->get('type') === $type->value)>{{ __('auditor::auditor.types.'.$type->value) }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filter-status">{{ __('auditor::auditor.filters.status') }}</label>
        <select id="filter-status" name="status" class="input w-auto">
            <option value="">{{ __('auditor::auditor.filters.status') }}: {{ __('auditor::auditor.filters.any') }}</option>
            @foreach (['2xx', '3xx', '4xx', '5xx'] as $status)
                <option value="{{ $status }}" @selected($filters->get('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filter-range">{{ __('auditor::auditor.filters.range') }}</label>
        <select id="filter-range" name="range" class="input w-auto">
            <option value="">{{ __('auditor::auditor.filters.range') }}: {{ __('auditor::auditor.filters.any') }}</option>
            @foreach (['15m', '1h', '24h', '7d', '30d', 'today'] as $option)
                <option value="{{ $option }}" @selected($filters->get('range') === $option)>{{ __('auditor::auditor.ranges.'.$option) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">{{ __('auditor::auditor.common.apply') }}</button>
    </form>

    @if ($chips !== [])
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach ($chips as $chip)
                <x-auditor::filter-chip :chip="$chip" />
            @endforeach
            <a href="{{ route('auditor.entries.index') }}" class="link text-sm">{{ __('auditor::auditor.common.reset') }}</a>
        </div>
    @endif

    <div x-data="live" data-interval="{{ $pollInterval }}"
         data-poll-url="{{ $filters->url('auditor.poll', ['scope' => 'entries', 'after' => (string) $latestId]) }}"
         data-new-label="{{ __('auditor::auditor.common.new_entries', ['count' => ':count']) }}">
        <div x-show="hasNew" x-cloak class="mb-3 flex justify-center">
            <button type="button" class="btn btn-primary" @click="reload()"><x-auditor::icon name="arrow-up" /><span x-text="label"></span></button>
        </div>
    </div>

    <div class="card overflow-hidden">
        @if ($entries->isEmpty())
            @if ($filters->active())
                <x-auditor::empty-state icon="filter" :title="__('auditor::auditor.entries.empty_filtered')">
                    <a href="{{ route('auditor.entries.index') }}" class="link">{{ __('auditor::auditor.common.reset') }}</a>
                </x-auditor::empty-state>
            @else
                <x-auditor::empty-state icon="list" :title="__('auditor::auditor.entries.empty_title')">{{ __('auditor::auditor.entries.empty_body') }}</x-auditor::empty-state>
            @endif
        @else
            {{-- Desktop: table. Mobile: cards. --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th scope="col" data-column="{{ $column }}" @class(['text-right' => in_array($column, ['duration', 'time'], true)])>{{ __('auditor::auditor.entries.col_'.$column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody data-rows>
                        @foreach ($entries as $entry)
                            <tr data-row data-href="{{ route('auditor.entries.show', $entry->ulid) }}" data-peek-url="{{ route('auditor.entries.peek', $entry->ulid) }}" aria-selected="false">
                                <td data-column="type"><x-auditor::type-badge :type="$entry->type" /></td>
                                <td data-column="name" class="max-w-[28rem]">
                                    <div class="flex items-center gap-2">
                                        <x-auditor::method-badge :method="$entry->http_method" />
                                        <a href="{{ route('auditor.entries.show', $entry->ulid) }}" class="min-w-0 truncate font-medium hover:underline" title="{{ $entry->url }}">{{ $entry->name ?? $entry->url ?? __('auditor::auditor.types.'.$entry->type->value) }}</a>
                                        <span class="flex shrink-0 items-center gap-1 text-muted">
                                            @if ($entry->model_changes_count > 0)<x-auditor::icon name="arrow-left-right" class="size-3.5" :label="__('auditor::auditor.entries.has_changes')" />@endif
                                            @if ($entry->denied_abilities_count > 0)<x-auditor::icon name="triangle-alert" class="size-3.5 text-danger" :label="__('auditor::auditor.entries.has_denied')" />@endif
                                            @if (! empty($entry->mails))<x-auditor::icon name="mail" class="size-3.5" :label="__('auditor::auditor.entries.sent_mail')" />@endif
                                            @if (! empty($entry->notifications))<x-auditor::icon name="bell" class="size-3.5" :label="__('auditor::auditor.entries.sent_notification')" />@endif
                                        </span>
                                    </div>
                                    @if ($entry->name && $entry->url)
                                        <div class="truncate font-mono text-xs text-muted" title="{{ $entry->url }}">{{ parse_url($entry->url, PHP_URL_PATH) ?? $entry->url }}</div>
                                    @endif
                                </td>
                                <td data-column="user"><x-auditor::user-chip :user="$users->get($entry->user_type, $entry->user_id)" :os-user="$entry->os_user" /></td>
                                <td data-column="status"><x-auditor::status-badge :entry="$entry" /></td>
                                <td data-column="duration" class="text-right tabular {{ ($entry->duration_ms ?? 0) > 1000 ? 'text-warning' : 'text-muted' }}">{{ $present::duration($entry->duration_ms) }}</td>
                                <td data-column="time" class="text-right text-muted"><x-auditor::relative-time :date="$entry->started_at" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-line md:hidden">
                @foreach ($entries as $entry)
                    <li>
                        <a href="{{ route('auditor.entries.show', $entry->ulid) }}" class="block min-h-11 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2">
                                    <x-auditor::type-badge :type="$entry->type" />
                                    <x-auditor::method-badge :method="$entry->http_method" />
                                    <span class="truncate font-medium">{{ $entry->name ?? $entry->url ?? '—' }}</span>
                                </span>
                                <x-auditor::status-badge :entry="$entry" />
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs text-muted">
                                <x-auditor::user-chip :user="$users->get($entry->user_type, $entry->user_id)" :os-user="$entry->os_user" :link="false" class="text-xs" />
                                <span class="flex items-center gap-2"><span class="tabular">{{ $present::duration($entry->duration_ms) }}</span><x-auditor::relative-time :date="$entry->started_at" /></span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <x-auditor::pagination :paginator="$entries" />
        @endif
    </div>
@endsection
