<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Rembon\LaravelAuditor\Auditor as AuditorService;
use Rembon\LaravelAuditor\Testing\AuditorFake;

/**
 * @method static AuditorService withProperty(string $key, mixed $value)
 * @method static AuditorService withProperties(array<string, mixed> $properties)
 * @method static AuditorService tag(string ...$tags)
 * @method static AuditorService ignore()
 * @method static mixed withoutAuditing(callable $callback)
 * @method static string correlationId()
 * @method static AuditorService auth(Closure $callback)
 * @method static AuditorService resolveUserUsing(Closure $callback)
 * @method static AuditorService filter(Closure $callback)
 * @method static AuditorService redactUsing(Closure $callback)
 * @method static AuditorService extend(string $driver, Closure $callback)
 * @method static AuditorService displayUserUsing(Closure $callback)
 * @method static AuditorService useNonce(string|Closure $nonce)
 * @method static string|null nonce()
 * @method static mixed guard(callable $callback, mixed $default = null)
 * @method static void assertEntryRecorded(Closure|null $callback = null)
 * @method static void assertEntryNotRecorded(Closure|null $callback = null)
 * @method static void assertChangeRecorded(string $auditableType, int|string|null $auditableId = null, \Rembon\LaravelAuditor\Enums\ChangeEvent|string|null $event = null, Closure|null $callback = null)
 * @method static void assertChangeNotRecorded(string $auditableType, int|string|null $auditableId = null, \Rembon\LaravelAuditor\Enums\ChangeEvent|string|null $event = null)
 * @method static void assertAbilityDenied(string $ability)
 * @method static void assertAbilityGranted(string $ability)
 * @method static void assertNothingRecorded()
 *
 * @see AuditorService
 * @see AuditorFake
 */
final class Auditor extends Facade
{
    /**
     * Swap the auditor for an in-memory fake that records everything
     * synchronously and exposes assertions.
     */
    public static function fake(): AuditorFake
    {
        $app = self::getFacadeApplication() ?? throw new \RuntimeException('A facade root has not been set.');

        $fake = new AuditorFake($app, $app->make(AuditorService::class));

        self::swap($fake);
        $app->instance(AuditorService::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AuditorService::class;
    }
}
