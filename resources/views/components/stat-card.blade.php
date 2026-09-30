@props(['label', 'stat', 'tone' => 'accent', 'icon' => null])
@php($present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class)
<a href="{{ $stat['url'] }}" {{ $attributes->class(['card block p-4 hover:border-line-strong']) }}>
    <div class="flex items-center justify-between text-sm text-muted">
        <span>{{ $label }}</span>
        @if ($icon)<x-auditor::icon :name="$icon" class="size-4 text-{{ $tone }}" />@endif
    </div>
    <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-semibold tabular">{{ $present::number($stat['total']) }}</span>
        @if ($stat['delta'] !== null)
            <span class="text-xs tabular {{ $stat['delta'] > 0 ? 'text-warning' : 'text-muted' }}"
                  title="{{ __('auditor::auditor.overview.vs_previous') }}">
                {{ $stat['delta'] > 0 ? '▲' : ($stat['delta'] < 0 ? '▼' : '•') }} {{ abs((int) $stat['delta']) }}%
                <span class="sr-only">{{ __('auditor::auditor.overview.vs_previous') }}</span>
            </span>
        @endif
    </div>
    <x-auditor::sparkline :series="$stat['series']" :label="$label" class="mt-3 text-{{ $tone }}" />
</a>
