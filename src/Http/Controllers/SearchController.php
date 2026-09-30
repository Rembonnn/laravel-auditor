<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\CorrelationId;
use Rembon\LaravelAuditor\Support\Dashboard\ModelRef;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Command palette search. Understands:
 *   01J9Z…            an entry ULID or a correlation id
 *   Post#12           model history (class basename or morph alias)
 *   App\Models\Post:12
 *   user:5            entries of user 5
 *   route:orders.store
 * Anything else searches entry names and URLs.
 *
 * Returns plain strings only; the palette renders them as text.
 */
final class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 200));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = [];

        if (preg_match('/\Auser:(.+)\z/i', $q, $m)) {
            $results[] = $this->item('users', __('auditor::auditor.search.user_entries', ['id' => $m[1]]), route('auditor.entries.index', ['user' => $m[1]]));
        } elseif (preg_match('/\Aroute:(.+)\z/i', $q, $m)) {
            $results[] = $this->item('entries', __('auditor::auditor.search.route_entries', ['route' => $m[1]]), route('auditor.entries.index', ['name' => $m[1]]));
        } elseif (preg_match('/\A(.+?)(?:#|:)([A-Za-z0-9_-]+)\z/', $q, $m) && ($type = $this->auditableType($m[1])) !== null) {
            $results[] = $this->item('models', class_basename($type).' #'.$m[2], ModelRef::url($type, $m[2]), __('auditor::auditor.search.model_history'));
        }

        if (CorrelationId::isValid($q)) {
            if ($entry = Entry::query()->where('ulid', $q)->first()) {
                $results[] = $this->item('entries', $this->entryLabel($entry), route('auditor.entries.show', $entry->ulid), $entry->ulid);
            }

            $correlated = Entry::query()->where('correlation_id', $q)->orderBy('id')->first();

            if ($correlated) {
                $results[] = $this->item('entries', __('auditor::auditor.search.correlation'), route('auditor.entries.show', $correlated->ulid).'#timeline', $q);
            }
        }

        if ($results === []) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            Entry::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('url', 'like', $like))
                ->latest('id')
                ->limit(6)
                ->get()
                ->each(function (Entry $entry) use (&$results): void {
                    $results[] = $this->item('entries', $this->entryLabel($entry), route('auditor.entries.show', $entry->ulid), (string) $entry->url);
                });
        }

        return response()->json($results);
    }

    private function auditableType(string $name): ?string
    {
        $name = trim($name);

        if (ModelChange::query()->where('auditable_type', $name)->exists()) {
            return $name;
        }

        return Values::toString(ModelChange::query()
            ->where('auditable_type', 'like', '%\\'.addcslashes($name, '%_\\'))
            ->value('auditable_type'));
    }

    private function entryLabel(Entry $entry): string
    {
        return trim(strtoupper($entry->type->value).' '.($entry->http_method ?? '').' '.($entry->name ?? $entry->url ?? ''));
    }

    /**
     * @return array{group: string, label: string, url: string, description: string|null}
     */
    private function item(string $group, string $label, string $url, ?string $description = null): array
    {
        return [
            'group' => __('auditor::auditor.search.groups.'.$group),
            'label' => $label,
            'url' => $url,
            'description' => $description,
        ];
    }
}
