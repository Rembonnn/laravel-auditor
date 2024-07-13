<?php

namespace Rembon\LaravelAuditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Performance extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'performances';

    /**
     * @var array
     */
    protected $guarded = ['id'];
}
