@props(['value', 'label' => null])
<button type="button" data-copy="{{ $value }}"
        {{ $attributes->class(['inline-flex size-6 items-center justify-center rounded text-muted hover:bg-sunken hover:text-fg']) }}
        aria-label="{{ $label ?? __('auditor::auditor.common.copy') }}" title="{{ __('auditor::auditor.common.copy') }}">
    <x-auditor::icon name="copy" class="size-3.5" />
</button>
