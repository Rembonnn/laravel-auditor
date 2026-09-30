@props(['entries', 'current' => null, 'users'])
<ol {{ $attributes->class(['relative space-y-4 border-l border-line pl-6']) }}>
    @foreach ($entries as $item)
        @php($isCurrent = $current !== null && $item->id === $current->id)
        <li class="relative">
            <span class="absolute -left-[33px] top-0.5 inline-flex size-4 items-center justify-center rounded-full border border-line bg-surface {{ $isCurrent ? 'ring-2 ring-accent' : '' }}">
                <x-auditor::icon :name="\Rembon\LaravelAuditor\Support\Dashboard\Present::typeIcon($item->type)" class="size-2.5" />
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <x-auditor::type-badge :type="$item->type" />
                <a href="{{ route('auditor.entries.show', $item->ulid) }}" class="link font-medium" @if ($isCurrent) aria-current="true" @endif>{{ $item->name ?? $item->url ?? '—' }}</a>
                @if ($isCurrent)<span class="text-xs text-muted">({{ __('auditor::auditor.entry.current') }})</span>@endif
                <x-auditor::status-badge :entry="$item" />
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                <x-auditor::relative-time :date="$item->started_at" />
                <span class="tabular">{{ \Rembon\LaravelAuditor\Support\Dashboard\Present::duration($item->duration_ms) }}</span>
                <x-auditor::user-chip :user="$users->get($item->user_type, $item->user_id)" :os-user="$item->os_user" class="text-xs" />
            </div>
        </li>
    @endforeach
</ol>
