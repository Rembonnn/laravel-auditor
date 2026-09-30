@props(['title' => null, 'href' => null, 'linkLabel' => null])
<section {{ $attributes->class(['card min-w-0']) }}>
    @if ($title)
        <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold">{{ $title }}</h2>
            @if ($href)
                <a href="{{ $href }}" class="link text-sm">{{ $linkLabel ?? __('auditor::auditor.common.view_all') }} →</a>
            @endif
            {{ $actions ?? '' }}
        </header>
    @endif
    {{ $slot }}
</section>
