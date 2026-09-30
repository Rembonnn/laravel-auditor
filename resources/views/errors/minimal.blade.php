@php($nonce = app(\Rembon\LaravelAuditor\Auditor::class)->nonce())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ config('auditor.dashboard.theme', 'system') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ __('auditor::auditor.app') }}</title>
    {{ \Rembon\LaravelAuditor\Support\Dashboard\Assets::themeInit($nonce) }}
    {{ \Rembon\LaravelAuditor\Support\Dashboard\Assets::css() }}
</head>
<body class="flex min-h-screen items-center justify-center bg-bg p-4 text-fg">
    <main class="card flex w-full max-w-lg flex-col items-center gap-3 p-8 text-center">
        @yield('body')
    </main>
</body>
</html>
