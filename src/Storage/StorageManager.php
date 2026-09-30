<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Storage;

use Illuminate\Support\Manager;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Support\Values;

/**
 * @method Storage driver(string|null $driver = null)
 */
final class StorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return Values::toString($this->config->get('auditor.storage.driver')) ?? 'database';
    }

    public function createDatabaseDriver(): Storage
    {
        return new DatabaseStorage($this->container->make('db'), $this->config);
    }

    public function createLogDriver(): Storage
    {
        return new LogStorage($this->container->make('log')->channel(Values::toString($this->config->get('auditor.storage.log.channel'))));
    }

    public function createNullDriver(): Storage
    {
        return new NullStorage;
    }
}
