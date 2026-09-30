@props(['tone' => 'neutral', 'icon' => null])
<span {{ $attributes->class(['badge', 'tone-'.$tone]) }}>
    @if ($icon)<x-auditor::icon :name="$icon" class="size-3.5" />@endif
    {{ $slot }}
</span>
