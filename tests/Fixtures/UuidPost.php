<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Traits\Auditable;

class UuidPost extends Model
{
    use Auditable;
    use HasUuids;

    protected $table = 'uuid_posts';

    protected $guarded = [];
}
