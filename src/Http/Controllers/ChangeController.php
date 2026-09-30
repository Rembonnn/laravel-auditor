<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Dashboard\Filters;
use Rembon\LaravelAuditor\Support\Dashboard\UserDirectory;
use Rembon\LaravelAuditor\Support\Settings;

final class ChangeController
{
    public function index(Request $request, UserDirectory $users): View
    {
        $filters = Filters::changes($request);

        $changes = $filters->applyToChanges(ModelChange::query()->with('entry:id,ulid,os_user'))
            ->orderByDesc('id')
            ->cursorPaginate(Settings::int('auditor.dashboard.per_page', 25))
            ->withQueryString();

        $users->load($changes->items());

        return view('auditor::changes.index', [
            'changes' => $changes,
            'filters' => $filters,
            'users' => $users,
            'models' => ModelChange::query()->distinct()->orderBy('auditable_type')->limit(100)->pluck('auditable_type'),
        ]);
    }

    public function peek(string $ulid, UserDirectory $users): View
    {
        $change = ModelChange::query()->with('entry')->where('ulid', $ulid)->firstOrFail();
        $users->load([$change]);

        return view('auditor::changes.peek', ['change' => $change, 'users' => $users]);
    }
}
