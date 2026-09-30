@props(['series', 'label' => ''])
@php
    $max = max(1, ...$series);
    $count = max(1, count($series) - 1);
    $points = collect($series)->map(fn ($v, $i) => round($i / $count * 100, 2).','.round(22 - ($v / $max) * 20, 2))->implode(' ');
@endphp
<svg viewBox="0 0 100 24" preserveAspectRatio="none" {{ $attributes->class(['h-6 w-full']) }} role="img" aria-label="{{ $label }}">
    <title>{{ $label }}</title>
    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
</svg>
