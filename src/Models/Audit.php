<?php

namespace Rembon\LaravelAuditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Audit extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'audits';

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $casts = [
        'abilities' => 'collection',
        'emails' => 'collection',
        'models' => 'collection',
        'notifications' => 'collection',
        'properties' => 'collection',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: config('laravel-auditor.user_model'),
            ownerKey: config('laravel-auditor.user_owner_key'),
        );
    }
}
