@php
    $present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class;
    $denied = collect($entry->abilities ?? [])->filter(fn ($a) => ($a['result'] ?? null) !== true);
@endphp
<div class="space-y-4">
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <x-auditor::type-badge :type="$entry->type" />
            <x-auditor::method-badge :method="$entry->http_method" />
            <x-auditor::status-badge :entry="$entry" />
            <span class="tabular text-sm text-muted">{{ $present::duration($entry->duration_ms) }}</span>
        </div>
        <h2 class="mt-2 break-all text-base font-semibold">{{ $entry->name ?? $entry->url ?? '—' }}</h2>
        @if ($entry->name && $entry->url)<p class="break-all font-mono text-xs text-muted">{{ $entry->url }}</p>@endif
    </div>

    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entries.col_user') }}</dt><dd><x-auditor::user-chip :user="$users->get($entry->user_type, $entry->user_id)" :guard="$entry->guard" :os-user="$entry->os_user" /></dd></div>
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entries.col_time') }}</dt><dd><x-auditor::relative-time :date="$entry->started_at" /></dd></div>
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.correlation') }}</dt><dd class="flex items-center gap-1 font-mono text-xs">{{ $present::shortId($entry->correlation_id) }}<x-auditor::copy :value="$entry->correlation_id" /></dd></div>
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.entry.ip') }}</dt><dd class="font-mono text-xs">{{ $entry->ip ?? '—' }}</dd></div>
    </dl>

    @if ($denied->isNotEmpty())
        <div>
            <h3 class="mb-1 text-xs font-medium text-muted">{{ __('auditor::auditor.status.denied') }}</h3>
            <div class="flex flex-wrap gap-1">@foreach ($denied as $ability)<x-auditor::badge tone="danger" icon="shield-alert">{{ $ability['ability'] }}</x-auditor::badge>@endforeach</div>
        </div>
    @endif

    @if ($changes->isNotEmpty())
        <div class="space-y-3">
            <h3 class="text-xs font-medium text-muted">{{ __('auditor::auditor.entry.tabs.changes') }} ({{ $entry->model_changes_count }})</h3>
            @foreach ($changes as $change)
                <div class="rounded-md border border-line p-3">
                    <div class="mb-2 flex items-center gap-2 text-sm"><x-auditor::event-badge :event="$change->event" /><span class="font-medium">{{ class_basename($change->auditable_type) }}</span><span class="font-mono text-muted">#{{ $change->auditable_id }}</span></div>
                    <x-auditor::diff :change="$change" />
                </div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('auditor.entries.show', $entry->ulid) }}" class="btn btn-primary">{{ __('auditor::auditor.common.open_full') }} <x-auditor::icon name="external-link" /></a>
</div>
