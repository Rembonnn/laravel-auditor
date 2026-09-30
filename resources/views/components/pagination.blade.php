@props(['paginator'])
@if ($paginator->hasPages())
    <nav class="flex items-center justify-end gap-2 px-4 py-3" aria-label="Pagination">
        @if ($paginator->previousPageUrl())
            <a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-auditor::icon name="chevron-left" />{{ __('auditor::auditor.common.newer') }}</a>
        @endif
        @if ($paginator->nextPageUrl())
            <a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('auditor::auditor.common.older') }}<x-auditor::icon name="chevron-right" /></a>
        @endif
    </nav>
@endif
