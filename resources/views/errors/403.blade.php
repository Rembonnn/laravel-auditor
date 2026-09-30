@extends('auditor::errors.minimal')

@section('title', __('auditor::auditor.errors.forbidden_title'))

@section('body')
    <x-auditor::icon name="lock" class="size-8 text-danger" />
    <h1 class="text-xl font-semibold">{{ __('auditor::auditor.errors.forbidden_title') }}</h1>
    <p class="text-sm text-muted">{{ __('auditor::auditor.errors.forbidden_body') }}</p>
    @if ($showHint)
        <p class="text-sm">{{ __('auditor::auditor.errors.forbidden_hint') }}</p>
        <pre class="w-full overflow-x-auto rounded-md border border-line bg-sunken p-3 text-left font-mono text-xs">Gate::define('viewAuditor', fn ($user) => $user->is_admin);</pre>
    @endif
@endsection
