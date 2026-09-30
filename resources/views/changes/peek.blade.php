<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <x-auditor::event-badge :event="$change->event" />
        <h2 class="text-base font-semibold">{{ class_basename($change->auditable_type) }} <span class="font-mono text-muted">#{{ $change->auditable_id }}</span></h2>
    </div>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.changes.col_user') }}</dt><dd><x-auditor::user-chip :user="$users->get($change->user_type, $change->user_id)" :os-user="$change->entry?->os_user" /></dd></div>
        <div><dt class="text-xs text-muted">{{ __('auditor::auditor.changes.col_time') }}</dt><dd><x-auditor::relative-time :date="$change->created_at" /></dd></div>
    </dl>
    <x-auditor::diff :change="$change" />
    <div class="flex flex-wrap gap-2">
        <a href="{{ \Rembon\LaravelAuditor\Support\Dashboard\ModelRef::url($change->auditable_type, $change->auditable_id) }}" class="btn btn-primary"><x-auditor::icon name="history" />{{ __('auditor::auditor.common.model_history') }}</a>
        @if ($change->entry)
            <a href="{{ route('auditor.entries.show', $change->entry->ulid) }}#changes" class="btn">{{ __('auditor::auditor.common.open_full') }} <x-auditor::icon name="external-link" /></a>
        @endif
    </div>
</div>
