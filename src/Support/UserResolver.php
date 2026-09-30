<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Contracts\UserResolver as UserResolverContract;
use Throwable;

/**
 * Default resolver: checks the configured guards (or the default guard) in
 * order and returns the first authenticated user.
 */
final class UserResolver implements UserResolverContract
{
    /** @var (Closure(): mixed)|null */
    private ?Closure $callback = null;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Config $config,
    ) {}

    /**
     * @param  (Closure(): mixed)|null  $callback
     */
    public function using(?Closure $callback): void
    {
        $this->callback = $callback;
    }

    public function resolve(): ?array
    {
        if ($this->callback !== null) {
            return self::describe(($this->callback)(), null);
        }

        $guards = $this->config->get('auditor.user.guards') ?? [null];

        foreach ((array) $guards as $guard) {
            try {
                $user = $this->auth->guard(Values::toString($guard))->user();
            } catch (Throwable) {
                // An unconfigured guard (or one that cannot work in the
                // current runtime) simply has no user.
                continue;
            }

            if ($user !== null) {
                return self::describe($user, Values::toString($guard ?? $this->config->get('auth.defaults.guard')));
            }
        }

        return null;
    }

    /**
     * @return array{type: string, id: string, guard: string|null}|null
     */
    public static function describe(mixed $user, ?string $guard): ?array
    {
        if (! $user instanceof Authenticatable) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        if ($id === null) {
            return null;
        }

        return [
            'type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'id' => Values::toString($id) ?? '',
            'guard' => $guard,
        ];
    }
}
