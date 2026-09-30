@props(['icon' => 'info', 'title'])
<div {{ $attributes->class(['flex flex-col items-center gap-3 px-6 py-12 text-center']) }}>
    <span class="inline-flex size-10 items-center justify-center rounded-full bg-sunken text-muted"><x-auditor::icon :name="$icon" class="size-5" /></span>
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    <div class="max-w-md text-sm text-muted">{{ $slot }}</div>
</div>
