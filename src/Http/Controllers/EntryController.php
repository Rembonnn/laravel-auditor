<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Support\Dashboard\Filters;
use Rembon\LaravelAuditor\Support\Dashboard\UserDirectory;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\Values;

final class EntryController
{
    public function index(Request $request, UserDirectory $users): View
    {
        $filters = Filters::entries($request);

        $entries = $filters->applyToEntries(Entry::query())
            ->orderByDesc('id')
            ->cursorPaginate(Settings::int('auditor.dashboard.per_page', 25))
            ->withQueryString();

        $users->load($entries->items());

        return view('auditor::entries.index', [
            'entries' => $entries,
            'filters' => $filters,
            'users' => $users,
            'latestId' => Values::toInt(Entry::query()->max('id')) ?? 0,
            'currentUser' => $request->user(),
        ]);
    }

    public function show(string $ulid, UserDirectory $users): View
    {
        $entry = Entry::query()->where('ulid', $ulid)->firstOrFail();
        $changes = $entry->modelChanges()->get();

        $timeline = Entry::query()
            ->where('correlation_id', $entry->correlation_id)
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $users->load($timeline->concat($changes));

        return view('auditor::entries.show', [
            'entry' => $entry,
            'changes' => $changes,
            'timeline' => $timeline,
            'users' => $users,
        ]);
    }

    public function peek(string $ulid, UserDirectory $users): View
    {
        $entry = Entry::query()->where('ulid', $ulid)->firstOrFail();
        $users->load([$entry]);

        return view('auditor::entries.peek', [
            'entry' => $entry,
            'changes' => $entry->modelChanges()->limit(5)->get(),
            'users' => $users,
        ]);
    }
}
