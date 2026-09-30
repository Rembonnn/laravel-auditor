<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rembon\LaravelAuditor\Traits\Auditable;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 */
class Post extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];

    /** @var list<string> */
    protected array $auditExclude = ['view_count'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'published_at' => 'datetime'];
    }
}
