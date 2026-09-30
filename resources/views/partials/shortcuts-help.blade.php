@php
    $rows = [
        [['⌘', 'K'], 'palette'], [['g', 'o/e/c/i'], 'go'], [['/'], 'filter'], [['j', 'k'], 'rows'],
        [['Enter'], 'open'], [['Space'], 'peek'], [['Esc'], 'close'], [['t'], 'theme'], [['l'], 'live'], [['?'], 'help'],
    ];
@endphp
<div x-show="helpOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-[var(--overlay)] p-4" @click.self="closeHelp()">
    <div class="raised w-full max-w-md p-5" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title" x-trap="helpOpen">
        <div class="mb-4 flex items-center justify-between">
            <h2 id="shortcuts-title" class="text-lg font-semibold">{{ __('auditor::auditor.shortcuts.title') }}</h2>
            <button type="button" class="btn btn-ghost btn-icon" @click="closeHelp()" aria-label="{{ __('auditor::auditor.common.close') }}"><x-auditor::icon name="x" /></button>
        </div>
        <dl class="space-y-2 text-sm">
            @foreach ($rows as [$keys, $label])
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted">{{ __('auditor::auditor.shortcuts.'.$label) }}</dt>
                    <dd class="flex items-center gap-1">
                        @foreach ($keys as $i => $key)
                            @if ($label === 'go' && $i === 1)<span class="text-xs text-subtle">{{ __('auditor::auditor.shortcuts.then') }}</span>@endif
                            <x-auditor::kbd>{{ $key }}</x-auditor::kbd>
                        @endforeach
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
