@props(['environment', 'tone' => 'neutral'])
<span {{ $attributes->class(['badge font-semibold uppercase tracking-wide tone-'.$tone]) }} title="{{ __('auditor::auditor.topbar.environment') }}">
    @if ($tone === 'danger')<x-auditor::icon name="triangle-alert" class="size-3.5" />@endif
    {{ $environment }}
</span>
