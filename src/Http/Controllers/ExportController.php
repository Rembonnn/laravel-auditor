<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\Request;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Dashboard\Filters;
use Rembon\LaravelAuditor\Support\Values;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered list as CSV. Cells that a spreadsheet would treat as
 * a formula are prefixed with a quote (CSV / formula injection).
 */
final class ExportController
{
    public function __invoke(Request $request, string $scope): StreamedResponse
    {
        $filename = 'auditor-'.$scope.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request, $scope): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            if ($scope === 'entries') {
                $this->entries($out, Filters::entries($request));
            } else {
                $this->changes($out, Filters::changes($request));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * @param  resource  $out
     */
    private function entries($out, Filters $filters): void
    {
        $this->row($out, ['ulid', 'correlation_id', 'type', 'name', 'http_method', 'url', 'status_code', 'failed', 'user_type', 'user_id', 'ip', 'duration_ms', 'denied_abilities', 'model_changes', 'started_at']);

        foreach ($filters->applyToEntries(Entry::query())->lazyByIdDesc(500) as $entry) {
            $this->row($out, [
                $entry->ulid, $entry->correlation_id, $entry->type->value, $entry->name, $entry->http_method, $entry->url,
                $entry->status_code, $entry->failed ? '1' : '0', $entry->user_type, $entry->user_id, $entry->ip,
                $entry->duration_ms, $entry->denied_abilities_count, $entry->model_changes_count, $entry->started_at->toIso8601String(),
            ]);
        }
    }

    /**
     * @param  resource  $out
     */
    private function changes($out, Filters $filters): void
    {
        $this->row($out, ['ulid', 'correlation_id', 'event', 'auditable_type', 'auditable_id', 'attributes', 'old_values', 'new_values', 'user_type', 'user_id', 'created_at']);

        foreach ($filters->applyToChanges(ModelChange::query())->lazyByIdDesc(500) as $change) {
            $this->row($out, [
                $change->ulid, $change->correlation_id, $change->event->value, $change->auditable_type, $change->auditable_id,
                implode(' ', $change->changedAttributes()),
                $change->old_values === null ? null : json_encode($change->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $change->new_values === null ? null : json_encode($change->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $change->user_type, $change->user_id, $change->created_at->toIso8601String(),
            ]);
        }
    }

    /**
     * @param  resource  $out
     * @param  list<scalar|null>  $cells
     */
    private function row($out, array $cells): void
    {
        fputcsv($out, array_map(self::safe(...), $cells), escape: '');
    }

    public static function safe(mixed $value): string
    {
        $value = Values::toString($value) ?? '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
