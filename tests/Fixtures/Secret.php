<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Traits\Auditable;

class Secret extends Model
{
    use Auditable;

    protected $table = 'secrets';

    protected $guarded = [];

    protected $hidden = ['internal_note'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'payload' => AsEncryptedArrayObject::class];
    }
}
