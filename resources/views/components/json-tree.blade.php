@props(['data'])
<div x-data="jsonTree" data-json="{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}" {{ $attributes->class(['min-w-0']) }}>
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <label class="sr-only" for="{{ $id = 'json-'.\Illuminate\Support\Str::random(6) }}">{{ __('auditor::auditor.common.search_keys') }}</label>
        <input id="{{ $id }}" type="search" x-model="term" @input="filter()" class="input max-w-60" placeholder="{{ __('auditor::auditor.common.search_keys') }}">
        <button type="button" class="btn btn-ghost min-h-7 px-2 text-xs" @click="expandAll()">{{ __('auditor::auditor.common.expand_all') }}</button>
        <button type="button" class="btn btn-ghost min-h-7 px-2 text-xs" @click="collapseAll()">{{ __('auditor::auditor.common.collapse_all') }}</button>
        <button type="button" class="btn btn-ghost min-h-7 px-2 text-xs" @click="copy()"><x-auditor::icon name="copy" class="size-3.5" />{{ __('auditor::auditor.common.copy') }}</button>
    </div>
    <div class="overflow-x-auto rounded-md border border-line bg-sunken p-3 font-mono text-xs leading-6">
        @include('auditor::partials.json-node', ['value' => $data, 'depth' => 0])
    </div>
</div>
