{{-- One attribute of a diff; nested JSON rows recurse with more indent. --}}
@php
    $pad = 'padding-left: '.(12 + $depth * 16).'px';
    $tone = match ($row['status']) {
        'added' => 'diff-add',
        'removed' => 'diff-del',
        default => '',
    };
@endphp
@if ($row['children'] !== [])
    <tr class="border-t border-line first:border-t-0">
        <th scope="row" class="py-2 pr-3 text-left align-top font-mono text-xs font-medium" style="{{ $pad }}" colspan="3">{{ $row['key'] }}</th>
    </tr>
    @foreach ($row['children'] as $child)
        @include('auditor::partials.diff-row', ['row' => $child, 'depth' => $depth + 1, 'showOld' => $showOld, 'showNew' => $showNew])
    @endforeach
    @if ($row['unchanged'] > 0)
        <tr><td colspan="3" class="py-1 text-xs text-subtle" style="padding-left: {{ 28 + $depth * 16 }}px">{{ __('auditor::auditor.common.n_unchanged', ['count' => $row['unchanged']]) }}</td></tr>
    @endif
@else
    <tr class="border-t border-line align-top first:border-t-0 {{ $tone }}">
        <th scope="row" class="w-1/4 py-2 pr-3 text-left font-mono text-xs font-medium break-all" style="{{ $pad }}">{{ $row['key'] }}</th>
        @if ($row['status'] === 'redacted')
            <td class="px-3 py-2" colspan="2"><x-auditor::value :value="config('auditor.redaction.replacement', '[REDACTED]')" /></td>
        @else
            {{-- Side by side: old | new. Inline: old → new in one cell. --}}
            @if ($showOld)
                <td class="px-3 py-2 diff-del" x-show="isSplit" @if ($showNew) x-cloak @endif>
                    <span class="sr-only">{{ __('auditor::auditor.diff.old') }}:</span>
                    @if ($row['status'] === 'added')<span class="value-null">—</span>@else<x-auditor::value :value="$row['old']" />@endif
                </td>
            @endif
            @if ($showNew)
                <td class="px-3 py-2 diff-add" x-show="isSplit" @if ($showOld) x-cloak @endif>
                    <span class="sr-only">{{ __('auditor::auditor.diff.new') }}:</span>
                    @if ($row['status'] === 'removed')<span class="value-null">—</span>@else<x-auditor::value :value="$row['new']" />@endif
                </td>
            @endif
            <td class="px-3 py-2" x-show="isInline">
                <div class="flex flex-wrap items-start gap-2">
                    @if ($showOld && $row['status'] !== 'added')
                        <span class="rounded px-1 diff-del"><span class="sr-only">{{ __('auditor::auditor.diff.old') }}:</span><x-auditor::value :value="$row['old']" /></span>
                    @endif
                    @if ($showOld && $showNew)<span class="text-subtle" aria-hidden="true">→</span>@endif
                    @if ($showNew && $row['status'] !== 'removed')
                        <span class="rounded px-1 diff-add"><span class="sr-only">{{ __('auditor::auditor.diff.new') }}:</span><x-auditor::value :value="$row['new']" /></span>
                    @endif
                </div>
            </td>
        @endif
    </tr>
@endif
