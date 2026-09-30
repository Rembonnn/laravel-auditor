@props(['lines' => 3])
<div {{ $attributes->class(['space-y-3']) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4" style="width: {{ 100 - ($i % 3) * 18 }}%"></div>
    @endfor
</div>
