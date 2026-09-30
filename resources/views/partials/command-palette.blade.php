@php
    $pages = [
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.nav.overview'), 'url' => route('auditor.overview')],
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.nav.entries'), 'url' => route('auditor.entries.index')],
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.nav.changes'), 'url' => route('auditor.changes.index')],
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.nav.integrity'), 'url' => route('auditor.integrity')],
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.presets.denied'), 'url' => route('auditor.entries.index', ['denied' => 1])],
        ['group' => __('auditor::auditor.search.pages'), 'label' => __('auditor::auditor.presets.failed'), 'url' => route('auditor.entries.index', ['failed' => 1])],
    ];
@endphp
<div x-data="commandPalette"
     data-search-url="{{ route('auditor.search') }}"
     data-pages="{{ json_encode($pages) }}"
     data-actions-label="{{ __('auditor::auditor.search.actions') }}"
     data-theme-label="{{ __('auditor::auditor.search.toggle_theme') }}"
     data-live-label="{{ __('auditor::auditor.search.toggle_live') }}"
     data-copy-label="{{ __('auditor::auditor.search.copy_url') }}">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-[var(--overlay)] p-4 pt-[12vh]" @click.self="hide()">
        <div class="raised w-full max-w-xl overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="palette-label" x-trap="open" @keydown="onKey($event)">
            <label id="palette-label" for="palette-input" class="sr-only">{{ __('auditor::auditor.search.label') }}</label>
            <div class="flex items-center gap-2 border-b border-line px-3">
                <x-auditor::icon name="search" class="size-4 text-muted" />
                <input id="palette-input" x-ref="input" x-model="query" @input="onInput()" type="text" autocomplete="off" spellcheck="false"
                       class="h-12 w-full bg-transparent text-base outline-none" placeholder="{{ __('auditor::auditor.search.label') }}"
                       role="combobox" aria-expanded="true" aria-controls="palette-results" aria-autocomplete="list">
                <x-auditor::kbd>Esc</x-auditor::kbd>
            </div>
            <ul id="palette-results" role="listbox" class="max-h-[50vh] overflow-y-auto p-2" aria-label="{{ __('auditor::auditor.search.label') }}">
                <template x-for="(item, index) in results" :key="index">
                    <li role="option" :aria-selected="isActive(index)" @mouseenter="hover(index)" @click="choose(index)"
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-sm aria-selected:bg-sunken">
                        <span class="min-w-0">
                            <span class="block truncate font-medium" x-text="item.label"></span>
                            <span class="block truncate text-xs text-muted" x-show="item.description" x-text="item.description"></span>
                        </span>
                        <span class="shrink-0 text-xs text-subtle" x-text="item.group"></span>
                    </li>
                </template>
                <li x-show="empty" class="px-3 py-6 text-center text-sm text-muted">{{ __('auditor::auditor.search.no_results') }}</li>
            </ul>
            <p class="border-t border-line px-3 py-2 text-xs text-muted">{{ __('auditor::auditor.search.hint') }}</p>
        </div>
    </div>
</div>
