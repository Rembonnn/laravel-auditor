<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Dashboard\ModelRef;
use Rembon\LaravelAuditor\Support\Dashboard\UserDirectory;

final class ModelHistoryController
{
    public function __invoke(Request $request, string $type, string $id, UserDirectory $users): View
    {
        $auditableType = ModelRef::decode($type);
        $event = ChangeEvent::tryFrom((string) $request->query('event'));

        $query = ModelChange::query()->with('entry:id,ulid,os_user')->forModel($auditableType, $id);

        if ($event !== null) {
            $query->where('event', $event->value);
        }

        $changes = $query
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString();

        abort_if($changes->isEmpty() && $event === null && $request->query('cursor') === null, 404);

        $users->load($changes->items());
        $record = ModelRef::find($auditableType, $id);

        return view('auditor::models.history', [
            'type' => $auditableType,
            'encodedType' => ModelRef::encode($auditableType),
            'id' => $id,
            'record' => $record,
            'title' => ModelRef::title($record),
            'changes' => $changes,
            'event' => $event,
            'users' => $users,
        ]);
    }
}
