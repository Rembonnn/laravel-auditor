@props(['change'])
{{-- Attribute diff: inline (default on small screens) or side by side. --}}
@php
    $rows = \Rembon\LaravelAuditor\Support\Dashboard\Diff::rows($change->old_values, $change->new_values);
    $event = $change->event;
    $showOld = $event !== \Rembon\LaravelAuditor\Enums\ChangeEvent::Created;
    $showNew = ! in_array($event, [\Rembon\LaravelAuditor\Enums\ChangeEvent::Deleted, \Rembon\LaravelAuditor\Enums\ChangeEvent::ForceDeleted], true) || $change->new_values !== null;
@endphp
<div x-data="diff" {{ $attributes->class(['min-w-0']) }}>
    @if ($rows === [])
        <p class="px-1 py-2 text-sm text-muted">{{ __('auditor::auditor.diff.no_values') }}</p>
    @else
        @if ($showOld && $showNew)
            <div class="mb-2 flex justify-end gap-1" role="group" aria-label="{{ __('auditor::auditor.diff.mode') }}">
                <button type="button" class="btn btn-ghost min-h-7 px-2 text-xs" :aria-pressed="isInline" @click="setMode('inline')">{{ __('auditor::auditor.diff.inline') }}</button>
                <button type="button" class="btn btn-ghost min-h-7 px-2 text-xs" :aria-pressed="isSplit" @click="setMode('split')">{{ __('auditor::auditor.diff.split') }}</button>
            </div>
        @endif
        <div class="overflow-x-auto rounded-md border border-line">
            <table class="w-full text-sm">
                <thead class="sr-only"><tr><th scope="col">{{ __('auditor::auditor.diff.attribute') }}</th><th scope="col">{{ __('auditor::auditor.diff.old') }}</th><th scope="col">{{ __('auditor::auditor.diff.new') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @include('auditor::partials.diff-row', ['row' => $row, 'depth' => 0, 'showOld' => $showOld, 'showNew' => $showNew])
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
