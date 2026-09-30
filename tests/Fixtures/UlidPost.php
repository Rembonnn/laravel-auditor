<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Traits\Auditable;

class UlidPost extends Model
{
    use Auditable;
    use HasUlids;

    protected $table = 'ulid_posts';

    protected $guarded = [];
}
