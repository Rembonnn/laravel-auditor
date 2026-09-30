@props(['user', 'guard' => null, 'osUser' => null, 'link' => true])
@php($present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class)
@if (($user['id'] ?? null) !== null)
    @php($label = $user['name'] ?? class_basename((string) $user['type']))
    @php($tag = $link ? 'a' : 'span')
    <{{ $tag }} @if ($link) href="{{ route('auditor.entries.index', ['user' => $user['type'].':'.$user['id']]) }}" @endif
       {{ $attributes->class(['inline-flex max-w-full items-center gap-1.5 text-sm', 'hover:underline' => $link]) }}>
        @if (! empty($user['avatar']))
            <img src="{{ $user['avatar'] }}" alt="" class="size-5 rounded-full object-cover">
        @else
            <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-sunken text-[10px] font-semibold text-muted" aria-hidden="true">{{ $present::initials($label) }}</span>
        @endif
        <span class="truncate">{{ $label }}</span>
        <span class="tabular text-subtle">#{{ $user['id'] }}</span>
        @if ($guard)<span class="text-subtle">({{ $guard }})</span>@endif
    </{{ $tag }}>
@elseif ($osUser)
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 text-sm']) }}>
        <x-auditor::icon name="server" class="size-4 text-muted" />
        <span class="font-mono">{{ $osUser }}</span>
    </span>
@else
    <span {{ $attributes->class(['text-sm text-subtle']) }}>{{ __('auditor::auditor.common.guest') }}</span>
@endif
