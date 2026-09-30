@php
    use Rembon\LaravelAuditor\Support\Dashboard\Assets;
    $nav = [
        ['overview', 'auditor.overview', 'layout-dashboard', 'auditor.overview'],
        ['entries', 'auditor.entries.index', 'list', 'auditor.entries.*'],
        ['changes', 'auditor.changes.index', 'arrow-left-right', 'auditor.changes.*'],
        ['integrity', 'auditor.integrity', 'shield-check', 'auditor.integrity'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme-default="{{ $themeDefault }}"
      data-timezone="{{ $timezone }}"
      data-copied-label="{{ __('auditor::auditor.common.copied') }}"
      data-copy-failed-label="{{ __('auditor::auditor.common.copy_failed') }}"
      data-theme-label-light="{{ __('auditor::auditor.topbar.toggle_theme', ['mode' => __('auditor::auditor.topbar.theme_light')]) }}"
      data-theme-label-dark="{{ __('auditor::auditor.topbar.toggle_theme', ['mode' => __('auditor::auditor.topbar.theme_dark')]) }}"
      data-theme-label-system="{{ __('auditor::auditor.topbar.toggle_theme', ['mode' => __('auditor::auditor.topbar.theme_system')]) }}"
      data-live-on-label="{{ __('auditor::auditor.topbar.live_on') }}"
      data-live-off-label="{{ __('auditor::auditor.topbar.live_off') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>@hasSection('title')@yield('title') · @endif{{ __('auditor::auditor.app') }} · {{ $brandName }}</title>
    {{ Assets::themeInit($nonce) }}
    {{ Assets::accentStyle($nonce) }}
    {{ Assets::css() }}
    {{ Assets::js() }}
</head>
<body x-data="shell" class="min-h-screen bg-bg text-fg">
    <div x-data="shortcuts" @keydown.window="onKey($event)" hidden></div>

    <a href="#main" class="sr-only z-50 rounded bg-surface px-3 py-2 focus:not-sr-only focus:fixed focus:left-2 focus:top-2">{{ __('auditor::auditor.nav.skip') }}</a>

    <div class="flex min-h-screen">
        {{-- Sidebar: full on desktop, icons on tablet, drawer on mobile. --}}
        <aside class="sidebar sticky top-0 hidden h-screen w-60 shrink-0 flex-col border-r border-line bg-surface md:flex" aria-label="{{ __('auditor::auditor.nav.main') }}">
            @include('auditor::partials.sidebar', ['nav' => $nav])
        </aside>

        <div x-show="mobileNavOpen" x-cloak class="fixed inset-0 z-40 bg-[var(--overlay)] md:hidden" @click="closeMobileNav()"></div>
        <aside x-show="mobileNavOpen" x-cloak x-trap="mobileNavOpen" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-line bg-surface md:hidden animate-drawer"
               aria-label="{{ __('auditor::auditor.nav.main') }}" @keydown.escape="closeMobileNav()">
            <div class="flex justify-end p-2"><button type="button" class="btn btn-ghost btn-icon" @click="closeMobileNav()" aria-label="{{ __('auditor::auditor.nav.close_menu') }}"><x-auditor::icon name="x" /></button></div>
            @include('auditor::partials.sidebar', ['nav' => $nav, 'mobile' => true])
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-line bg-bg/90 px-4 backdrop-blur md:px-6">
                <button type="button" class="btn btn-ghost btn-icon md:hidden" @click="openMobileNav()" aria-label="{{ __('auditor::auditor.nav.open_menu') }}"><x-auditor::icon name="menu" /></button>

                <nav aria-label="Breadcrumb" class="min-w-0 flex-1 truncate text-sm">
                    <ol class="flex items-center gap-1.5">
                        @yield('breadcrumb')
                    </ol>
                </nav>

                <button type="button" class="btn hidden w-56 justify-between text-muted sm:inline-flex" @click="$dispatch('auditor:palette')">
                    <span class="inline-flex items-center gap-2"><x-auditor::icon name="search" />{{ __('auditor::auditor.topbar.search') }}</span>
                    <span class="flex gap-0.5"><x-auditor::kbd>⌘</x-auditor::kbd><x-auditor::kbd>K</x-auditor::kbd></span>
                </button>
                <button type="button" class="btn btn-ghost btn-icon sm:hidden" @click="$dispatch('auditor:palette')" aria-label="{{ __('auditor::auditor.topbar.search') }}"><x-auditor::icon name="search" /></button>

                @if ($pollInterval > 0)
                    <button type="button" class="btn btn-ghost" @click="$store.live.toggle()" :aria-pressed="$store.live.enabled" aria-label="{{ __('auditor::auditor.topbar.toggle_live') }}">
                        <span class="size-2 rounded-full" :class="$store.live.enabled ? 'bg-success' : 'bg-line-strong'" aria-hidden="true"></span>
                        <span class="hidden lg:inline">{{ __('auditor::auditor.topbar.live') }}</span>
                    </button>
                @endif

                <button type="button" class="btn btn-ghost btn-icon" @click="$store.theme.cycle()"
                        :aria-label="$store.theme.label" title="{{ __('auditor::auditor.topbar.theme') }}">
                    <span x-show="$store.theme.isLight"><x-auditor::icon name="sun" /></span>
                    <span x-show="$store.theme.isDark" x-cloak><x-auditor::icon name="moon" /></span>
                    <span x-show="$store.theme.isSystem" x-cloak><x-auditor::icon name="monitor" /></span>
                </button>

                <x-auditor::env-badge :environment="$environment" :tone="$environmentTone" />
            </header>

            <main id="main" tabindex="-1" class="mx-auto w-full max-w-[1400px] flex-1 px-4 py-6 focus:outline-none md:px-6">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Quick peek drawer --}}
    <div x-data="drawer">
        <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-[var(--overlay)]" @click="hide()"></div>
        <aside x-show="open" x-cloak x-trap="open" role="dialog" aria-modal="true" aria-label="{{ __('auditor::auditor.entry.summary') }}"
               class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l border-line bg-surface animate-drawer">
            <div class="flex items-center justify-between border-b border-line px-4 py-2">
                <span class="text-sm text-muted"><x-auditor::kbd>j</x-auditor::kbd> <x-auditor::kbd>k</x-auditor::kbd></span>
                <button type="button" class="btn btn-ghost btn-icon" @click="hide()" aria-label="{{ __('auditor::auditor.common.close') }}"><x-auditor::icon name="x" /></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div x-show="loading"><x-auditor::skeleton :lines="6" /></div>
                <div x-show="failed" x-cloak class="flex flex-col items-start gap-3" role="alert">
                    <p class="text-sm text-danger">{{ __('auditor::auditor.common.error') }}</p>
                    <button type="button" class="btn" @click="retry()"><x-auditor::icon name="refresh" />{{ __('auditor::auditor.common.retry') }}</button>
                </div>
                <div x-ref="body" x-show="!loading && !failed" aria-live="polite"></div>
            </div>
        </aside>
    </div>

    @include('auditor::partials.command-palette')
    @include('auditor::partials.shortcuts-help')

    {{-- Toasts --}}
    <div class="pointer-events-none fixed bottom-4 right-4 z-[60] flex flex-col gap-2" role="status" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="raised pointer-events-auto flex items-center gap-2 px-3 py-2 text-sm animate-drawer">
                <span class="size-2 rounded-full" :class="'bg-' + toast.tone" aria-hidden="true"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</body>
</html>
