@extends('auditor::layout')

@php
    $present = \Rembon\LaravelAuditor\Support\Dashboard\Present::class;
@endphp

@section('title', __('auditor::auditor.integrity.title'))

@section('breadcrumb')
    <li class="font-medium">{{ __('auditor::auditor.integrity.title') }}</li>
@endsection

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ __('auditor::auditor.integrity.title') }}</h1>

    @if (! $enabled)
        <div class="card p-6">
            <div class="flex items-start gap-3">
                <x-auditor::icon name="shield" class="mt-0.5 size-6 text-muted" />
                <div class="space-y-3 text-sm">
                    <h2 class="text-base font-semibold">{{ __('auditor::auditor.integrity.disabled_title') }}</h2>
                    <p class="max-w-2xl text-muted">{{ __('auditor::auditor.integrity.disabled_body') }}</p>
                    <p>{{ __('auditor::auditor.integrity.enable') }}</p>
                    <div class="flex items-center gap-2"><code class="rounded bg-sunken px-2 py-1 font-mono text-xs">php artisan auditor:install --integrity</code><x-auditor::copy value="php artisan auditor:install --integrity" /></div>
                </div>
            </div>
        </div>
    @else
        @if (! $hasKey)
            <div class="card mb-4 flex items-center gap-2 border-danger p-4 text-sm text-danger" role="alert"><x-auditor::icon name="triangle-alert" />{{ __('auditor::auditor.integrity.missing_key') }}</div>
        @endif

        @php
            $valid = is_array($verify) ? (bool) ($verify['valid'] ?? false) : null;
            $first = null;
            foreach (($verify['tables'] ?? []) as $table => $result) {
                if (! empty($result['violations'])) { $first = ['table' => $table] + $result['violations'][0]; break; }
            }
        @endphp

        <section class="card mb-4 p-5 {{ $valid === false ? 'border-danger' : '' }}" @if ($valid === false) role="alert" @endif>
            <div class="flex items-start gap-3">
                <x-auditor::icon :name="$valid === null ? 'shield' : ($valid ? 'shield-check' : 'shield-alert')" class="size-7 {{ $valid === null ? 'text-muted' : ($valid ? 'text-success' : 'text-danger') }}" />
                <div class="min-w-0 space-y-1">
                    <h2 class="text-lg font-semibold">{{ $valid === null ? __('auditor::auditor.integrity.never_verified') : ($valid ? __('auditor::auditor.integrity.valid') : __('auditor::auditor.integrity.invalid')) }}</h2>
                    @if (is_array($verify))
                        <p class="text-sm text-muted">
                            {{ __('auditor::auditor.integrity.verified_at', ['date' => $present::absolute(\Illuminate\Support\Carbon::parse($verify['at']))]) }} ·
                            {{ __('auditor::auditor.integrity.checked', ['entries' => $present::number($verify['tables']['entries']['checked'] ?? 0), 'changes' => $present::number($verify['tables']['model_changes']['checked'] ?? 0)]) }}
                        </p>
                    @endif
                    @if ($first)
                        <p class="text-sm font-medium text-danger">{{ __('auditor::auditor.integrity.first_violation', ['table' => $first['table'], 'id' => $first['id'], 'reason' => __('auditor::auditor.integrity.reasons.'.$first['reason'])]) }}</p>
                    @endif
                </div>
            </div>

            @if ($valid === false)
                <div class="mt-4 border-t border-line pt-4">
                    <h3 class="mb-2 text-sm font-semibold">{{ __('auditor::auditor.integrity.investigate') }}</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-muted">
                        @foreach (__('auditor::auditor.integrity.steps') as $step)<li>{{ $step }}</li>@endforeach
                    </ol>
                </div>
            @endif
        </section>

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="card p-4">
                <dt class="text-sm text-muted">{{ __('auditor::auditor.overview.entries') }} / {{ __('auditor::auditor.overview.model_changes') }}</dt>
                <dd class="mt-1 text-sm tabular">{{ __('auditor::auditor.integrity.totals', ['entries' => $present::number($totals['entries']), 'changes' => $present::number($totals['model_changes'])]) }}</dd>
                <dd class="mt-1 text-sm text-muted tabular">{{ __('auditor::auditor.integrity.unsealed', ['entries' => $present::number($unsealed['entries'] ?? 0), 'changes' => $present::number($unsealed['model_changes'] ?? 0)]) }}</dd>
            </div>
            <div class="card p-4">
                <dt class="text-sm text-muted">{{ __('auditor::auditor.integrity.last_seal') }}</dt>
                <dd class="mt-1 text-sm">
                    @if (is_array($seal))<x-auditor::relative-time :date="\Illuminate\Support\Carbon::parse($seal['at'])" />@else<span class="text-warning">{{ __('auditor::auditor.integrity.never_sealed') }}</span>@endif
                </dd>
            </div>
            <div class="card p-4">
                <dt class="text-sm text-muted">Checkpoint</dt>
                <dd class="mt-1 text-sm">
                    @if ($checkpoint)
                        {{ __('auditor::auditor.integrity.checkpoint', ['id' => $present::number($checkpoint->last_id), 'date' => $present::absolute($checkpoint->created_at)]) }}
                    @else
                        <span class="text-muted">{{ __('auditor::auditor.integrity.no_checkpoint') }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    @endif
@endsection
