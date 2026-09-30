@props(['entry'])
@php
    $present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class;
    $tone = $present::statusTone($entry->status_code, $entry->failed);
    $running = $entry->completed_at === null;
@endphp
@if ($running)
    <x-auditor::badge tone="info" icon="clock">{{ __('auditor::auditor.status.running') }}</x-auditor::badge>
@elseif ($entry->type === \Rembon\LaravelAuditor\Enums\EntryType::Job)
    <x-auditor::badge :tone="$entry->failed ? 'danger' : 'success'" :icon="$entry->failed ? 'circle-x' : 'circle-check'">{{ $entry->failed ? __('auditor::auditor.status.failed') : __('auditor::auditor.status.ok') }}</x-auditor::badge>
@elseif ($entry->status_code !== null)
    <x-auditor::badge :tone="$tone" :icon="$tone === 'danger' ? 'circle-x' : ($tone === 'warning' ? 'triangle-alert' : null)" class="font-mono">{{ $entry->status_code }}</x-auditor::badge>
@else
    <span class="text-subtle">—</span>
@endif
