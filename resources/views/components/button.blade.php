@props(['variant' => null, 'icon' => null, 'href' => null])
@php($classes = ['btn', 'btn-'.$variant => $variant !== null])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<x-auditor::icon :name="$icon" />@endif{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class($classes) }}>@if ($icon)<x-auditor::icon :name="$icon" />@endif{{ $slot }}</button>
@endif
