<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\CorrelationId;

/**
 * Filter state for the Entries and Changes lists. The state lives entirely
 * in the query string, so a filtered view is a shareable link and the back
 * button works.
 */
final readonly class Filters
{
    public const array RANGES = ['15m' => '-15 minutes', '1h' => '-1 hour', '24h' => '-24 hours', '7d' => '-7 days', '30d' => '-30 days'];

    private const array ENTRY_KEYS = ['q', 'type', 'user', 'status', 'method', 'failed', 'denied', 'changes', 'correlation', 'tag', 'name', 'range', 'from', 'to'];

    private const array CHANGE_KEYS = ['q', 'event', 'model', 'id', 'user', 'correlation', 'range', 'from', 'to'];

    /**
     * @param  array<string, string>  $values
     */
    private function __construct(
        public string $scope,
        public array $values,
    ) {}

    public static function entries(Request $request): self
    {
        return new self('entries', self::read($request, self::ENTRY_KEYS));
    }

    public static function changes(Request $request): self
    {
        return new self('changes', self::read($request, self::CHANGE_KEYS));
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function active(): bool
    {
        return $this->values !== [];
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public function applyToEntries(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['q'])) {
            $term = $v['q'];
            $query->where(function (Builder $query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where('name', 'like', $like)->orWhere('url', 'like', $like);

                if (CorrelationId::isValid($term)) {
                    $query->orWhere('ulid', $term)->orWhere('correlation_id', $term);
                }
            });
        }

        if (isset($v['type']) && EntryType::tryFrom($v['type'])) {
            $query->where('type', $v['type']);
        }

        $this->applyUser($query, $v['user'] ?? null);

        if (isset($v['status'])) {
            if (preg_match('/\A([1-5])xx\z/', $v['status'], $m)) {
                $query->whereBetween('status_code', [(int) $m[1] * 100, (int) $m[1] * 100 + 99]);
            } elseif (ctype_digit($v['status'])) {
                $query->where('status_code', (int) $v['status']);
            }
        }

        if (isset($v['method'])) {
            $query->where('http_method', strtoupper($v['method']));
        }

        if (($v['failed'] ?? null) === '1') {
            $query->where('failed', true);
        }

        if (($v['denied'] ?? null) === '1') {
            $query->where('denied_abilities_count', '>', 0);
        }

        if (($v['changes'] ?? null) === '1') {
            $query->where('model_changes_count', '>', 0);
        }

        if (isset($v['correlation'])) {
            $query->where('correlation_id', $v['correlation']);
        }

        if (isset($v['tag'])) {
            $query->whereJsonContains('tags', $v['tag']);
        }

        if (isset($v['name'])) {
            $query->where('name', $v['name']);
        }

        return $this->applyDates($query);
    }

    /**
     * @param  Builder<ModelChange>  $query
     * @return Builder<ModelChange>
     */
    public function applyToChanges(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['event']) && ChangeEvent::tryFrom($v['event'])) {
            $query->where('event', $v['event']);
        }

        if (isset($v['model'])) {
            $query->where('auditable_type', ModelRef::decode($v['model']));
        }

        if (isset($v['id'])) {
            $query->where('auditable_id', $v['id']);
        }

        if (isset($v['q'])) {
            $like = '%'.addcslashes($v['q'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('auditable_type', 'like', $like)->orWhere('auditable_id', $v['q']));
        }

        if (isset($v['correlation'])) {
            $query->where('correlation_id', $v['correlation']);
        }

        $this->applyUser($query, $v['user'] ?? null);

        return $this->applyDates($query);
    }

    /**
     * Removable chips for the active filters.
     *
     * @return list<array{key: string, label: string, value: string, url: string}>
     */
    public function chips(string $route): array
    {
        $chips = [];

        foreach ($this->values as $key => $value) {
            $chips[] = [
                'key' => $key,
                'label' => __('auditor::auditor.filters.'.$key),
                'value' => $this->chipValue($key, $value),
                'url' => $this->url($route, except: [$key]),
            ];
        }

        return $chips;
    }

    /**
     * @param  array<string, string|null>  $with
     * @param  list<string>  $except
     */
    public function url(string $route, array $with = [], array $except = []): string
    {
        $query = array_filter(
            array_merge(array_diff_key($this->values, array_flip($except)), $with),
            fn (?string $value): bool => $value !== null && $value !== '',
        );

        return route($route, $query);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public function dateRange(): array
    {
        $v = $this->values;
        $now = CarbonImmutable::instance(Date::now());

        if (($v['range'] ?? null) === 'today') {
            return [$now->startOfDay(), null];
        }

        if (isset($v['range'], self::RANGES[$v['range']])) {
            return [$now->modify(self::RANGES[$v['range']]), null];
        }

        return [self::parseDate($v['from'] ?? null), self::parseDate($v['to'] ?? null)];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyDates(Builder $query): Builder
    {
        [$from, $to] = $this->dateRange();

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * "5" (any user type) or "App\Models\User:5" / "user:5".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyUser(Builder $query, ?string $user): void
    {
        if ($user === null) {
            return;
        }

        if (str_contains($user, ':')) {
            $type = substr($user, 0, (int) strrpos($user, ':'));
            $id = substr($user, (int) strrpos($user, ':') + 1);
            $query->where('user_type', ModelRef::decode($type))->where('user_id', $id);

            return;
        }

        $query->where('user_id', $user);
    }

    private function chipValue(string $key, string $value): string
    {
        return match ($key) {
            'failed', 'denied', 'changes' => __('auditor::auditor.common.yes'),
            'range' => __('auditor::auditor.ranges.'.$value),
            'model' => class_basename(ModelRef::decode($value)),
            'user' => str_contains($value, ':') ? '#'.substr($value, (int) strrpos($value, ':') + 1) : '#'.$value,
            default => $value,
        };
    }

    private static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function read(Request $request, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (is_string($value) && trim($value) !== '') {
                $values[$key] = mb_substr(trim($value), 0, 255);
            }
        }

        return $values;
    }
}
