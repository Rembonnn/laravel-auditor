<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Support\Dashboard\Filters;

/**
 * Live mode: how many rows arrived after the one the page started with.
 */
final class PollController
{
    public function __invoke(Request $request, string $scope): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $query = Entry::query()->where('id', '>', $after);

        if ($scope === 'entries') {
            Filters::entries($request)->applyToEntries($query);
        }

        return response()->json(['count' => $query->count()]);
    }
}
