@props(['items'])
@if (collect($items)->contains('done', false))
    <details open class="card mb-6" {{ $attributes }}>
        <summary class="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-semibold">
            <span class="inline-flex items-center gap-2"><x-auditor::icon name="info" class="size-4 text-info" />{{ __('auditor::auditor.checklist.title') }}</span>
            <span class="text-xs font-normal text-muted">{{ __('auditor::auditor.checklist.dismiss') }}</span>
        </summary>
        <ul class="space-y-2 border-t border-line px-4 py-3 text-sm">
            @foreach ($items as $item)
                <li class="flex items-start gap-2">
                    <x-auditor::icon :name="$item['done'] ? 'circle-check' : 'circle'" class="mt-0.5 size-4 {{ $item['done'] ? 'text-success' : 'text-subtle' }}" />
                    <span class="{{ $item['done'] ? 'text-muted line-through' : '' }}">{{ __('auditor::auditor.checklist.'.$item['key']) }}</span>
                    <span class="sr-only">{{ $item['done'] ? __('auditor::auditor.common.yes') : __('auditor::auditor.common.no') }}</span>
                </li>
            @endforeach
        </ul>
    </details>
@endif
