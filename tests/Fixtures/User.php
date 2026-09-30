<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Rembon\LaravelAuditor\Traits\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    use Auditable;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_admin' => 'boolean'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): self
    {
        return self::query()->create($attributes + [
            'name' => 'User '.($id = Str::lower(Str::random(12))),
            'email' => "user-{$id}@example.com",
            'password' => 'secret-password',
        ]);
    }
}
