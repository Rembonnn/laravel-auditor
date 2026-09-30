<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rembon\LaravelAuditor\Traits\Auditable;

/**
 * @property int $id
 * @property string $title
 */
class Post extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'published_at' => 'datetime'];
    }
}
