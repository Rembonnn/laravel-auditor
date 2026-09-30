@extends('auditor::layout')

@php
    $present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class;
    $user = $users->get($entry->user_type, $entry->user_id);
    $properties = array_filter(['properties' => $entry->properties, 'input' => $entry->input], fn ($v) => ! empty($v));
    $messages = count($entry->mails ?? []) + count($entry->notifications ?? []);
    $tabs = [
        'summary' => null,
        'changes' => $changes->count(),
        'abilities' => count($entry->abilities ?? []),
        'models' => count($entry->models_accessed ?? []),
        'messages' => $messages,
        'properties' => count($properties),
        'timeline' => $timeline->count(),
    ];
    $maxIds = (int) config('auditor.models.max_ids_per_model', 50);
@endphp

@section('title', ($entry->name ?? $entry->url ?? $entry->ulid))

@section('breadcrumb')
    <li><a href="{{ route('auditor.entries.index') }}" class="link">{{ __('auditor::auditor.entries.back') }}</a></li>
    <li aria-hidden="true" class="text-subtle">›</li>
    <li class="truncate font-mono text-muted" aria-current="page">{{ $present::shortId($entry->ulid) }}</li>
@endsection

@section('content')
    <a href="{{ route('auditor.entries.index') }}" class="link mb-3 inline-flex items-center gap-1 text-sm"><x-auditor::icon name="arrow-left" class="size-3.5" />{{ __('auditor::auditor.entries.back') }}</a>

    <header class="card mb-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <x-auditor::type-badge :type="$entry->type" />
                    <x-auditor::method-badge :method="$entry->http_method" />
                    <h1 class="min-w-0 break-all text-lg font-semibold">{{ $entry->url ?? $entry->name ?? __('auditor::auditor.types.'.$entry->type->value) }}</h1>
                </div>
                @if ($entry->name || $entry->route_action)
                    <p class="mt-1 break-all font-mono text-xs text-muted">{{ collect([$entry->name, $entry->route_action])->filter()->implode(' · ') }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 text-sm">
                <x-auditor::status-badge :entry="$entry" />
                <span class="tabular text-muted">{{ $present::duration($entry->duration_ms) }}</span>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
            <x-auditor::user-chip :user="$user" :guard="$entry->guard" :os-user="$entry->os_user" />
            <span class="inline-flex items-center gap-1"><x-auditor::icon name="clock" class="size-3.5" /><time data-absolute datetime="{{ $present::iso($entry->started_at) }}">{{ $present::absolute($entry->started_at) }}</time> (<x-auditor::relative-time :date="$entry->started_at" />)</span>
            @if ($entry->ip)<span class="inline-flex items-center gap-1"><x-auditor::icon name="globe" class="size-3.5" /><span class="font-mono">{{ $entry->ip }}</span></span>@endif
            @if ($entry->hostname)<span class="inline-flex items-center gap-1"><x-auditor::icon name="server" class="size-3.5" />{{ $entry->hostname }}</span>@endif
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
            <span class="inline-flex items-center gap-1">{{ __('auditor::auditor.entry.correlation') }} <a class="link font-mono" href="{{ route('auditor.entries.index', ['correlation' => $entry->correlation_id]) }}">{{ $present::shortId($entry->correlation_id) }}</a><x-auditor::copy :value="$entry->correlation_id" /></span>
            <span class="inline-flex items-center gap-1">{{ __('auditor::auditor.entry.ulid') }} <span class="font-mono">{{ $present::shortId($entry->ulid) }}</span><x-auditor::copy :value="$entry->ulid" /></span>
            @if (! empty($entry->tags))
                <span class="inline-flex items-center gap-1">{{ __('auditor::auditor.entry.tags') }}:
                    @foreach ($entry->tags as $tag)<a href="{{ route('auditor.entries.index', ['tag' => $tag]) }}" class="badge tone-neutral">{{ $tag }}</a>@endforeach
                </span>
            @endif
        </div>
    </header>

    <div x-data="tabs">
        <div role="tablist" class="mb-4 flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('auditor::auditor.entry.summary') }}" @keydown="onKey($event)">
            @foreach ($tabs as $tab => $count)
                <button type="button" role="tab" id="tab-{{ $tab }}" data-tab="{{ $tab }}" aria-controls="panel-{{ $tab }}"
                        :aria-selected="isCurrent('{{ $tab }}')" :tabindex="isCurrent('{{ $tab }}') ? 0 : -1" @click="select('{{ $tab }}')"
                        class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 border-transparent px-3 py-2 text-sm aria-selected:border-accent aria-selected:text-fg {{ $count === 0 ? 'text-subtle' : 'text-muted' }}">
                    {{ __('auditor::auditor.entry.tabs.'.$tab) }}
                    @if ($count !== null)<span class="badge tone-neutral px-1.5 tabular">{{ $count }}</span>@endif
                </button>
            @endforeach
        </div>

        {{-- Summary --}}
        <section role="tabpanel" id="panel-summary" aria-labelledby="tab-summary" x-show="isCurrent('summary')">
            <dl class="card grid grid-cols-1 gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'route_action' => $entry->route_action,
                    'user_agent' => $entry->user_agent,
                    'os_user' => $entry->os_user,
                    'hostname' => $entry->hostname,
                ] as $label => $value)
                    @if ($value)
                        <div class="min-w-0"><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.'.$label) }}</dt><dd class="break-all font-mono text-xs">{{ $value }}</dd></div>
                    @endif
                @endforeach
                <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.started') }}</dt><dd><time data-absolute datetime="{{ $present::iso($entry->started_at) }}">{{ $present::absolute($entry->started_at) }}</time></dd></div>
                <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.completed') }}</dt><dd>@if ($entry->completed_at)<time data-absolute datetime="{{ $present::iso($entry->completed_at) }}">{{ $present::absolute($entry->completed_at) }}</time>@else{{ __('auditor::auditor.entry.not_completed') }}@endif</dd></div>
                @foreach (['changes' => $changes->count(), 'abilities' => count($entry->abilities ?? []), 'messages' => $messages] as $key => $count)
                    <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.tabs.'.$key) }}</dt><dd class="tabular">{{ $count }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- Changes --}}
        <section role="tabpanel" id="panel-changes" aria-labelledby="tab-changes" x-show="isCurrent('changes')" x-cloak class="space-y-3">
            @forelse ($changes as $change)
                <details class="card" @if ($loop->index < 5) open @endif>
                    <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-4 py-3 text-sm">
                        <x-auditor::event-badge :event="$change->event" />
                        <span class="font-medium">{{ class_basename($change->auditable_type) }}</span>
                        <span class="font-mono text-muted">#{{ $change->auditable_id }}</span>
                        <a href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($change->auditable_type, $change->auditable_id) }}" class="link ml-auto text-xs">{{ __('auditor::auditor.common.model_history') }} ↗</a>
                    </summary>
                    <div class="border-t border-line p-4"><x-auditor::diff :change="$change" /></div>
                </details>
            @empty
                <div class="card"><x-auditor::empty-state icon="arrow-left-right" :title="__('auditor::auditor.entry.no_changes')" /></div>
            @endforelse
        </section>

        {{-- Abilities --}}
        <section role="tabpanel" id="panel-abilities" aria-labelledby="tab-abilities" x-show="isCurrent('abilities')" x-cloak>
            <div class="card overflow-x-auto">
                @if (empty($entry->abilities))
                    <x-auditor::empty-state icon="shield" :title="__('auditor::auditor.entry.no_abilities')" />
                @else
                    <table class="data-table">
                        <thead><tr><th scope="col">{{ __('auditor::auditor.entry.ability') }}</th><th scope="col">{{ __('auditor::auditor.entry.result') }}</th><th scope="col">{{ __('auditor::auditor.entry.arguments') }}</th><th scope="col" class="text-right">{{ __('auditor::auditor.entry.times') }}</th></tr></thead>
                        <tbody>
                            @foreach ($entry->abilities as $ability)
                                <tr class="cursor-default">
                                    <td class="font-mono text-xs">{{ $ability['ability'] }}</td>
                                    <td>
                                        @if (($ability['result'] ?? null) === true)
                                            <x-auditor::badge tone="success" icon="circle-check">{{ __('auditor::auditor.status.granted') }}</x-auditor::badge>
                                        @else
                                            <x-auditor::badge tone="danger" icon="circle-x">{{ __('auditor::auditor.status.denied') }}</x-auditor::badge>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @foreach ($ability['arguments'] ?? [] as $argument)
                                            @if (is_array($argument) && isset($argument['type'], $argument['id']))
                                                <a class="link font-mono" href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($argument['type'], (string) $argument['id']) }}">{{ class_basename($argument['type']) }}#{{ $argument['id'] }}</a>
                                            @else
                                                <span class="font-mono">{{ is_scalar($argument) ? $argument : json_encode($argument) }}</span>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td class="text-right tabular">{{ $ability['count'] ?? 1 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>

        {{-- Models accessed --}}
        <section role="tabpanel" id="panel-models" aria-labelledby="tab-models" x-show="isCurrent('models')" x-cloak>
            <div class="card overflow-x-auto">
                @if (empty($entry->models_accessed))
                    <x-auditor::empty-state icon="database" :title="__('auditor::auditor.entry.no_models')" />
                @else
                    <table class="data-table">
                        <thead><tr><th scope="col">{{ __('auditor::auditor.entry.model') }}</th><th scope="col" class="text-right">{{ __('auditor::auditor.entry.loaded') }}</th><th scope="col">{{ __('auditor::auditor.entry.ids', ['max' => $maxIds]) }}</th></tr></thead>
                        <tbody>
                            @foreach ($entry->models_accessed as $class => $model)
                                <tr class="cursor-default">
                                    <td title="{{ $class }}">{{ class_basename($class) }}</td>
                                    <td class="text-right tabular">{{ $present::number($model['count'] ?? 0) }}</td>
                                    <td class="text-xs">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($model['ids'] ?? [] as $id)
                                                <a class="link font-mono" href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($class, (string) $id) }}">#{{ $id }}</a>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>

        {{-- Mail & notifications --}}
        <section role="tabpanel" id="panel-messages" aria-labelledby="tab-messages" x-show="isCurrent('messages')" x-cloak class="space-y-4">
            @if ($messages === 0)
                <div class="card"><x-auditor::empty-state icon="mail" :title="__('auditor::auditor.entry.no_messages')" /></div>
            @endif
            @if (! empty($entry->mails))
                <x-auditor::card :title="__('auditor::auditor.entry.mails')">
                    <ul class="divide-y divide-line">
                        @foreach ($entry->mails as $mail)
                            <li class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2"><x-auditor::icon name="mail" class="size-4 text-muted" /><span class="font-medium">{{ $mail['subject'] ?? '—' }}</span><span class="text-xs text-muted" title="{{ $mail['mailable'] ?? '' }}">{{ class_basename((string) ($mail['mailable'] ?? '')) }}</span></div>
                                <div class="mt-1 text-xs text-muted">{{ __('auditor::auditor.entry.recipients') }}: <span class="font-mono break-all">{{ implode(', ', array_merge($mail['to'] ?? [], $mail['cc'] ?? [], $mail['bcc'] ?? [])) ?: '—' }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                </x-auditor::card>
            @endif
            @if (! empty($entry->notifications))
                <x-auditor::card :title="__('auditor::auditor.entry.notifications')">
                    <ul class="divide-y divide-line">
                        @foreach ($entry->notifications as $notification)
                            <li class="flex flex-wrap items-center gap-2 px-4 py-3 text-sm">
                                <x-auditor::icon name="bell" class="size-4 text-muted" />
                                <span class="font-medium" title="{{ $notification['notification'] }}">{{ class_basename($notification['notification']) }}</span>
                                <span class="badge tone-neutral">{{ $notification['channel'] }}</span>
                                @if ($notification['notifiable_type'])
                                    <span class="text-xs text-muted">→ {{ class_basename($notification['notifiable_type']) }}@if ($notification['notifiable_id'])#{{ $notification['notifiable_id'] }}@endif</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-auditor::card>
            @endif
        </section>

        {{-- Properties & input --}}
        <section role="tabpanel" id="panel-properties" aria-labelledby="tab-properties" x-show="isCurrent('properties')" x-cloak class="space-y-4">
            @forelse ($properties as $key => $data)
                <x-auditor::card :title="__('auditor::auditor.entry.'.$key)"><div class="p-4"><x-auditor::json-tree :data="$data" /></div></x-auditor::card>
            @empty
                <div class="card"><x-auditor::empty-state icon="info" :title="__('auditor::auditor.entry.no_properties')" /></div>
            @endforelse
        </section>

        {{-- Correlation timeline --}}
        <section role="tabpanel" id="panel-timeline" aria-labelledby="tab-timeline" x-show="isCurrent('timeline')" x-cloak>
            <div class="card p-4">
                <p class="mb-4 text-sm text-muted">{{ __('auditor::auditor.entry.timeline_hint') }}</p>
                <x-auditor::timeline :entries="$timeline" :current="$entry" :users="$users" />
            </div>
        </section>
    </div>
@endsection
