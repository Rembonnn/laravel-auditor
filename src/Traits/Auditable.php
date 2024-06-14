<?php

namespace Rembon\LaravelAuditor\Traits;

use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Contracts\Auditor;

trait Auditable
{
    /**
     * @return void
     */
    public static function bootAuditable(): void
    {
        static::retrieved(function (Model $model) {
            $auditor = app(Auditor::class);
            $auditor->addModel($model);
        });
    }
}
