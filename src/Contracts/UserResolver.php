<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Contracts;

interface UserResolver
{
    /**
     * Resolve the user responsible for the current action.
     *
     * @return array{type: string, id: string, guard: string|null}|null
     */
    public function resolve(): ?array;
}
