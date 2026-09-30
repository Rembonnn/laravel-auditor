<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/database', __DIR__.'/routes'])
    ->withPhpSets(php83: true)
    ->withSets([LaravelSetList::LARAVEL_120])
    ->withPreparedSets(deadCode: true, typeDeclarations: true)
    ->withSkip([
        __DIR__.'/tests/Fixtures',
    ]);
