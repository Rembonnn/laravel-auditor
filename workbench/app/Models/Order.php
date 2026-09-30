<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Traits\Auditable;

/**
 * @property int $id
 * @property string $number
 */
class Order extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'card_token' => 'encrypted'];
    }
}
