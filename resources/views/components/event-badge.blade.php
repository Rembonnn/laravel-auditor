@props(['event'])
@php($present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class)
<x-auditor::badge :tone="$present::eventTone($event)" :icon="$present::eventIcon($event)" {{ $attributes }}>{{ __('auditor::auditor.events.'.$event->value) }}</x-auditor::badge>
