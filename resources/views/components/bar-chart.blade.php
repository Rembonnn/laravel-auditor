@props(['series', 'labels', 'tones', 'title'])
{{-- Stacked bars, rendered on the server; colours follow the theme tokens. --}}
@php
    $keys = array_keys($series);
    $n = count($labels);
    $totals = array_map(fn ($i) => array_sum(array_map(fn ($k) => $series[$k][$i] ?? 0, $keys)), range(0, max(0, $n - 1)));
    $max = max(1, ...$totals);
    $width = 100 / max(1, $n);
@endphp
<figure {{ $attributes->class(['w-full']) }}>
    <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-40 w-full" role="img" aria-label="{{ $title }}">
        <title>{{ $title }}</title>
        @foreach ($labels as $i => $label)
            @php($y = 40)
            <g>
                <title>{{ $label }}: {{ collect($keys)->map(fn ($k) => __('auditor::auditor.types.'.$k).' '.($series[$k][$i] ?? 0))->implode(', ') }}</title>
                <rect x="{{ $i * $width }}" y="0" width="{{ $width }}" height="40" fill="transparent" />
                @foreach ($keys as $k)
                    @php($h = ($series[$k][$i] ?? 0) / $max * 38)
                    @if ($h > 0)
                        @php($y -= $h)
                        <rect x="{{ $i * $width + $width * 0.15 }}" y="{{ round($y, 3) }}" width="{{ $width * 0.7 }}" height="{{ round($h, 3) }}" class="text-{{ $tones[$k] }}" fill="currentColor" />
                    @endif
                @endforeach
            </g>
        @endforeach
    </svg>
    <div class="mt-1 flex justify-between text-xs text-subtle tabular" aria-hidden="true">
        <span>{{ $labels[0] ?? '' }}</span><span>{{ $labels[intdiv($n, 2)] ?? '' }}</span><span>{{ $labels[$n - 1] ?? '' }}</span>
    </div>
    <table class="sr-only">
        <caption>{{ $title }}</caption>
        <thead><tr><th scope="col">{{ __('auditor::auditor.entries.col_time') }}</th>@foreach ($keys as $k)<th scope="col">{{ __('auditor::auditor.types.'.$k) }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($labels as $i => $label)
                <tr><th scope="row">{{ $label }}</th>@foreach ($keys as $k)<td>{{ $series[$k][$i] ?? 0 }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</figure>
