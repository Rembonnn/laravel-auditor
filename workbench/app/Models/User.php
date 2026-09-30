<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Rembon\LaravelAuditor\Traits\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    use Auditable;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_admin' => 'boolean'];
    }
}
