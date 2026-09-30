@props(['method'])
@if ($method)
    <span {{ $attributes->class(['badge font-mono tone-'.\Rembon\LaravelAuditor\Support\Dashboard\Present::methodTone($method)]) }}>{{ $method }}</span>
@endif
