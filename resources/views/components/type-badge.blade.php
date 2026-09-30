@props(['type'])
@php($present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class)
<x-auditor::badge :tone="$present::typeTone($type)" :icon="$present::typeIcon($type)" {{ $attributes }}>{{ __('auditor::auditor.types.'.$type->value) }}</x-auditor::badge>
