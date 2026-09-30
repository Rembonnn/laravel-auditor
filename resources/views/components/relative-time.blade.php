@props(['date'])
@if ($date)
    <time datetime="{{ \Rembon\LaravelAuditor\Support\Dashboard\Present::iso($date) }}" data-relative
          title="{{ \Rembon\LaravelAuditor\Support\Dashboard\Present::absolute($date) }}"
          {{ $attributes->class(['tabular whitespace-nowrap']) }}>{{ \Rembon\LaravelAuditor\Support\Dashboard\Present::absolute($date) }}</time>
@else
    <span class="text-subtle">—</span>
@endif
