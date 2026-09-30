<div class="flex items-center gap-2 px-4 py-4">
    @if ($brandLogo)
        <img src="{{ $brandLogo }}" alt="" class="size-7 rounded">
    @else
        <span class="inline-flex size-7 items-center justify-center rounded-md bg-accent text-accent-contrast" aria-hidden="true"><x-auditor::icon name="shield-check" class="size-4" /></span>
    @endif
    <span class="sidebar-label min-w-0 leading-tight">
        <span class="block truncate text-sm font-semibold">{{ $brandName }}</span>
        <span class="block text-xs text-muted">{{ __('auditor::auditor.app') }}</span>
    </span>
</div>

<nav class="flex-1 space-y-1 px-2" aria-label="{{ __('auditor::auditor.nav.main') }}">
    @foreach ($nav as [$key, $route, $icon, $pattern])
        <a href="{{ route($route) }}" @unless ($mobile ?? false) data-nav="{{ $key }}" @endunless class="nav-item"
           @if (request()->routeIs($pattern)) aria-current="page" @endif title="{{ __('auditor::auditor.nav.'.$key) }}">
            <x-auditor::icon :name="$icon" class="size-4" />
            <span class="sidebar-label">{{ __('auditor::auditor.nav.'.$key) }}</span>
            @if ($key === 'integrity' && $integrityFailed)
                <span class="ml-auto size-2 rounded-full bg-danger" role="img" aria-label="{{ __('auditor::auditor.nav.integrity_failed') }}"></span>
            @endif
        </a>
    @endforeach
</nav>

<div class="space-y-1 border-t border-line px-2 py-3">
    <button type="button" class="nav-item w-full" @click="openHelp()" title="{{ __('auditor::auditor.nav.shortcuts') }}">
        <x-auditor::icon name="keyboard" /><span class="sidebar-label">{{ __('auditor::auditor.nav.shortcuts') }}</span>
    </button>
    <a href="https://github.com/Rembonnn/laravel-auditor#readme" class="nav-item" target="_blank" rel="noopener noreferrer" title="{{ __('auditor::auditor.nav.docs') }}">
        <x-auditor::icon name="book-open" /><span class="sidebar-label">{{ __('auditor::auditor.nav.docs') }}</span>
    </a>
    <button type="button" class="nav-item w-full" @click="toggleDensity()" :aria-pressed="isCompact" title="{{ __('auditor::auditor.topbar.density') }}">
        <x-auditor::icon name="rows" /><span class="sidebar-label">{{ __('auditor::auditor.topbar.density') }}</span>
    </button>
    <button type="button" class="nav-item w-full" @click="$dispatch('auditor:cycle-timezone')" title="{{ __('auditor::auditor.topbar.timezone') }}">
        <x-auditor::icon name="clock" /><span class="sidebar-label truncate">{{ __('auditor::auditor.topbar.timezone') }}: <span data-timezone-label></span></span>
    </button>
    @unless ($mobile ?? false)
        <button type="button" class="nav-item hidden w-full xl:flex" @click="toggleSidebar()" title="{{ __('auditor::auditor.nav.collapse') }}">
            <x-auditor::icon name="panel-left" /><span class="sidebar-label">{{ __('auditor::auditor.nav.collapse') }}</span>
        </button>
    @endunless
    @if ($version)
        <p class="sidebar-label px-2.5 pt-1 text-xs text-subtle">{{ $version }}</p>
    @endif
</div>
