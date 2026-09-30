@props(['chip'])
<span class="badge tone-neutral gap-1 pr-1">
    <span class="text-muted">{{ $chip['label'] }}:</span>
    <span class="font-medium">{{ $chip['value'] }}</span>
    <a href="{{ $chip['url'] }}" class="inline-flex size-5 items-center justify-center rounded-full hover:bg-sunken"
       aria-label="{{ __('auditor::auditor.common.remove_filter', ['name' => $chip['label']]) }}"><x-auditor::icon name="x" class="size-3" /></a>
</span>
