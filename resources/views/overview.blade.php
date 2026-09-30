@extends('auditor::layout')

@php($present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class)

@section('title', __('auditor::auditor.overview.title'))

@section('breadcrumb')
    <li class="font-medium">{{ __('auditor::auditor.overview.title') }}</li>
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ __('auditor::auditor.overview.title') }}</h1>
        <nav class="flex gap-1 rounded-md border border-line bg-surface p-0.5" aria-label="{{ __('auditor::auditor.overview.range') }}">
            @foreach (array_keys(\Rembon\LaravelAuditor\Support\Dashboard\TimeBuckets::RANGES) as $option)
                <a href="{{ route('auditor.overview', ['range' => $option]) }}"
                   class="rounded px-2.5 py-1 text-sm {{ $range === $option ? 'bg-sunken font-medium' : 'text-muted hover:text-fg' }}"
                   @if ($range === $option) aria-current="true" @endif>{{ $option }}</a>
            @endforeach
        </nav>
    </div>

    <x-auditor::setup-checklist :items="$checklist" />

    <div x-data="live" data-interval="{{ $pollInterval }}" data-poll-url="{{ route('auditor.poll', ['scope' => 'overview', 'after' => $recent->first()?->id ?? 0]) }}"
         data-new-label="{{ __('auditor::auditor.common.new_entries', ['count' => ':count']) }}">
        <div x-show="hasNew" x-cloak class="mb-4 flex justify-center">
            <button type="button" class="btn btn-primary" @click="reload()"><x-auditor::icon name="arrow-up" /><span x-text="label"></span></button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-auditor::stat-card :label="__('auditor::auditor.overview.entries')" :stat="$stats['entries']" tone="accent" icon="list" />
        <x-auditor::stat-card :label="__('auditor::auditor.overview.model_changes')" :stat="$stats['changes']" tone="warning" icon="arrow-left-right" />
        <x-auditor::stat-card :label="__('auditor::auditor.overview.denied')" :stat="$stats['denied']" tone="danger" icon="shield-alert" />
        <x-auditor::stat-card :label="__('auditor::auditor.overview.failed')" :stat="$stats['failed']" tone="danger" icon="circle-x" />
    </div>

    <x-auditor::card class="mt-4" :title="__('auditor::auditor.overview.activity')">
        <x-slot:actions>
            <div class="flex items-center gap-3 text-xs text-muted">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-neutral"></span>{{ __('auditor::auditor.types.http') }}</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-info"></span>{{ __('auditor::auditor.types.job') }}</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-sm bg-accent"></span>{{ __('auditor::auditor.types.command') }}</span>
            </div>
        </x-slot:actions>
        <div class="p-4">
            <x-auditor::bar-chart
                :series="$activity"
                :labels="array_map(fn ($d) => $d->format($buckets->labelFormat()), $buckets->starts())"
                :tones="['http' => 'neutral', 'job' => 'info', 'command' => 'accent']"
                :title="__('auditor::auditor.overview.chart_label', ['unit' => $buckets->unit])" />
        </div>
    </x-auditor::card>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-auditor::card :title="__('auditor::auditor.overview.recent_entries')" :href="route('auditor.entries.index')">
            @forelse ($recent as $entry)
                <a href="{{ route('auditor.entries.show', $entry->ulid) }}" class="flex items-center gap-3 border-b border-line px-4 py-2.5 text-sm last:border-b-0 hover:bg-sunken">
                    <x-auditor::type-badge :type="$entry->type" />
                    <span class="min-w-0 flex-1 truncate">
                        @if ($entry->http_method)<span class="font-mono text-xs text-muted">{{ $entry->http_method }}</span>@endif
                        {{ $entry->name ?? $entry->url ?? '—' }}
                    </span>
                    <x-auditor::user-chip :user="$users->get($entry->user_type, $entry->user_id)" :os-user="$entry->os_user" :link="false" class="hidden max-w-40 sm:inline-flex" />
                    <x-auditor::status-badge :entry="$entry" />
                    <x-auditor::relative-time :date="$entry->started_at" class="w-20 text-right text-xs text-muted" />
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">{{ __('auditor::auditor.overview.nothing_yet') }}</p>
            @endforelse
        </x-auditor::card>

        <x-auditor::card :title="__('auditor::auditor.overview.recent_denied')" :href="route('auditor.entries.index', ['denied' => 1])">
            @forelse ($denied as $entry)
                @php($ability = collect($entry->abilities ?? [])->first(fn ($a) => ($a['result'] ?? null) !== true))
                <a href="{{ route('auditor.entries.show', $entry->ulid) }}#abilities" class="flex items-center gap-3 border-b border-line px-4 py-2.5 text-sm last:border-b-0 hover:bg-sunken">
                    <x-auditor::icon name="shield-alert" class="size-4 text-danger" />
                    <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ $ability['ability'] ?? '—' }}</span>
                    <x-auditor::user-chip :user="$users->get($entry->user_type, $entry->user_id)" :link="false" class="max-w-40" />
                    <x-auditor::relative-time :date="$entry->started_at" class="w-20 text-right text-xs text-muted" />
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">{{ __('auditor::auditor.overview.nothing_yet') }}</p>
            @endforelse
        </x-auditor::card>

        <x-auditor::card :title="__('auditor::auditor.overview.active_users')">
            @forelse ($activeUsers as $row)
                <a href="{{ route('auditor.entries.index', ['user' => $row->user_type.':'.$row->user_id, 'range' => $range === '1h' ? '1h' : $range]) }}" class="flex items-center justify-between gap-3 border-b border-line px-4 py-2.5 text-sm last:border-b-0 hover:bg-sunken">
                    <x-auditor::user-chip :user="$users->get($row->user_type, $row->user_id)" :link="false" />
                    <span class="tabular text-muted">{{ $present::number((int) $row->aggregate) }}</span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">{{ __('auditor::auditor.overview.nothing_yet') }}</p>
            @endforelse
        </x-auditor::card>

        <x-auditor::card :title="__('auditor::auditor.overview.changed_models')" :href="route('auditor.changes.index')">
            @forelse ($changedModels as $row)
                <a href="{{ route('auditor.changes.index', ['model' => \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::encode($row->auditable_type)]) }}" class="flex items-center justify-between gap-3 border-b border-line px-4 py-2.5 text-sm last:border-b-0 hover:bg-sunken">
                    <span class="min-w-0 truncate" title="{{ $row->auditable_type }}">{{ class_basename($row->auditable_type) }}</span>
                    <span class="tabular text-muted">{{ $present::number((int) $row->aggregate) }}</span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">{{ __('auditor::auditor.overview.nothing_yet') }}</p>
            @endforelse
        </x-auditor::card>
    </div>
@endsection
