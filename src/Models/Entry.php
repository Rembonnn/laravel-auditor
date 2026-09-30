<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Models\Concerns\UsesAuditorConnection;
use Rembon\LaravelAuditor\Support\Values;

/**
 * One request, job or command.
 *
 * @property int $id
 * @property string $ulid
 * @property string $correlation_id
 * @property EntryType $type
 * @property string|null $name
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string|null $guard
 * @property string|null $http_method
 * @property string|null $url
 * @property string|null $route_action
 * @property int|null $status_code
 * @property bool $failed
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $os_user
 * @property string|null $hostname
 * @property int|null $duration_ms
 * @property list<array{ability: string, result: bool|null, arguments: list<mixed>, count?: int}>|null $abilities
 * @property array<string, array{ids: list<string>, count: int}>|null $models_accessed
 * @property list<array<string, mixed>>|null $mails
 * @property list<array<string, mixed>>|null $notifications
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $properties
 * @property list<string>|null $tags
 * @property int $denied_abilities_count
 * @property int $model_changes_count
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $previous_hash
 * @property string|null $hash
 * @property CarbonImmutable $created_at
 */
class Entry extends Model
{
    use MassPrunable;
    use UsesAuditorConnection;

    protected const string TABLE_KEY = 'entries';

    /** Set by auditor:prune so every chunk uses the same boundary. */
    public static ?int $pruneBefore = null;

    public $timestamps = false;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'type' => EntryType::class,
            'status_code' => 'integer',
            'failed' => 'boolean',
            'duration_ms' => 'integer',
            'abilities' => 'array',
            'models_accessed' => 'array',
            'mails' => 'array',
            'notifications' => 'array',
            'input' => 'array',
            'properties' => 'array',
            'tags' => 'array',
            'denied_abilities_count' => 'integer',
            'model_changes_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<ModelChange, $this>
     */
    public function modelChanges(): HasMany
    {
        return $this->hasMany(ModelChange::class, 'entry_id')->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forCorrelation(Builder $query, string $correlationId): void
    {
        $query->where('correlation_id', $correlationId);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withDeniedAbilities(Builder $query): void
    {
        $query->where('denied_abilities_count', '>', 0);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function causedBy(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())->where('user_id', Values::toString($user->getKey()));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function since(Builder $query, DateTimeInterface $date): void
    {
        $query->where('created_at', '>=', $date);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, EntryType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('id', '<', static::$pruneBefore ?? static::pruneBoundary());
    }
}
