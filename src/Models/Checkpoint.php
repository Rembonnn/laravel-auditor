<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Models\Concerns\UsesAuditorConnection;

/**
 * Last row removed by a prune: verification of the hash chain restarts here.
 *
 * @property int $id
 * @property string $table
 * @property int $last_id
 * @property string|null $last_hash
 * @property CarbonImmutable|null $created_at
 */
class Checkpoint extends Model
{
    use UsesAuditorConnection;

    protected const string TABLE_KEY = 'checkpoints';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    protected function casts(): array
    {
        return [
            'last_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public static function latestFor(string $table): ?self
    {
        return static::query()->where('table', $table)->orderByDesc('id')->first();
    }
}
