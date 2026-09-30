<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Models\Concerns\UsesAuditorConnection;
use Rembon\LaravelAuditor\Support\Values;

/**
 * One created / updated / deleted / restored / force deleted model.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $entry_id
 * @property string $correlation_id
 * @property string $auditable_type
 * @property string $auditable_id
 * @property ChangeEvent $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $user_type
 * @property string|null $user_id
 * @property CarbonImmutable $created_at
 * @property string|null $previous_hash
 * @property string|null $hash
 */
class ModelChange extends Model
{
    use MassPrunable;
    use UsesAuditorConnection;

    protected const string TABLE_KEY = 'model_changes';

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
            'event' => ChangeEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo('auditable');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    /**
     * Attribute names touched by this change.
     *
     * @return list<string>
     */
    public function changedAttributes(): array
    {
        return array_values(array_unique(array_map(strval(...), [
            ...array_keys($this->new_values ?? []),
            ...array_keys($this->old_values ?? []),
        ])));
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
    protected function forCorrelation(Builder $query, string $correlationId): void
    {
        $query->where('correlation_id', $correlationId);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forModel(Builder $query, string $type, int|string $id): void
    {
        $query->where('auditable_type', $type)->where('auditable_id', (string) $id);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('id', '<', static::$pruneBefore ?? static::pruneBoundary());
    }
}
