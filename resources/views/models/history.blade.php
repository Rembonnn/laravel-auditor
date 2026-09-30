@extends('auditor::layout')

@section('title', class_basename($type).' #'.$id)

@section('breadcrumb')
    <li><a href="{{ route('auditor.changes.index') }}" class="link">{{ __('auditor::auditor.changes.title') }}</a></li>
    <li aria-hidden="true" class="text-subtle">›</li>
    <li class="truncate" aria-current="page">{{ class_basename($type) }} #{{ $id }}</li>
@endsection

@section('content')
    <header class="card mb-4 p-4">
        <p class="text-xs text-muted" title="{{ $type }}">{{ $type }}</p>
        <h1 class="mt-1 text-lg font-semibold">{{ __('auditor::auditor.history.title', ['model' => class_basename($type), 'id' => $id]) }}</h1>
        @if ($title)<p class="mt-1 text-sm">{{ $title }}</p>@endif
        @if ($record === null)
            <p class="mt-2 inline-flex items-center gap-1 text-sm text-muted"><x-auditor::icon name="info" class="size-4" />{{ __('auditor::auditor.history.record_missing') }}</p>
        @elseif (method_exists($record, 'trashed') && $record->trashed())
            <p class="mt-2 inline-flex items-center gap-1 text-sm text-warning"><x-auditor::icon name="trash" class="size-4" />{{ __('auditor::auditor.history.record_trashed') }}</p>
        @endif
    </header>

    <nav class="mb-4 flex flex-wrap gap-1" aria-label="{{ __('auditor::auditor.history.filter_event') }}">
        <a href="{{ route('auditor.models.history', ['type' => $encodedType, 'id' => $id]) }}" class="btn {{ $event === null ? 'border-accent text-accent' : '' }}">{{ __('auditor::auditor.changes.all_events') }}</a>
        @foreach (\Rembon\LaravelAuditor\Enums\ChangeEvent::cases() as $option)
            <a href="{{ route('auditor.models.history', ['type' => $encodedType, 'id' => $id, 'event' => $option->value]) }}" class="btn {{ $event === $option ? 'border-accent text-accent' : '' }}">{{ __('auditor::auditor.events.'.$option->value) }}</a>
        @endforeach
    </nav>

    @if ($changes->isEmpty())
        <div class="card"><x-auditor::empty-state icon="history" :title="__('auditor::auditor.changes.empty_filtered')" /></div>
    @else
        <ol class="relative space-y-4 border-l border-line pl-6">
            @foreach ($changes as $change)
                <li class="relative">
                    <span class="absolute -left-[33px] top-3 inline-flex size-4 items-center justify-center rounded-full border border-line bg-surface">
                        <x-auditor::icon :name="\Rembon\LaravelAuditor\Support\Dashboard\Present::eventIcon($change->event)" class="size-2.5" />
                    </span>
                    <article class="card p-4">
                        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <x-auditor::event-badge :event="$change->event" />
                            <x-auditor::user-chip :user="$users->get($change->user_type, $change->user_id)" :os-user="$change->entry?->os_user" />
                            <x-auditor::relative-time :date="$change->created_at" class="text-muted" />
                            @if ($change->entry_id)
                                <button type="button" data-peek-url="{{ route('auditor.changes.peek', $change->ulid) }}" class="link ml-auto text-xs">{{ __('auditor::auditor.entry.summary') }}</button>
                            @endif
                        </div>
                        <x-auditor::diff :change="$change" />
                    </article>
                </li>
            @endforeach
        </ol>
        <x-auditor::pagination :paginator="$changes" />
    @endif
@endsection
