<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Events\EntryRecorded;
use Rembon\LaravelAuditor\Events\ModelChangeRecorded;
use Rembon\LaravelAuditor\Jobs\PersistEntry;
use Rembon\LaravelAuditor\Storage\StorageManager;
use Rembon\LaravelAuditor\Support\Redactor;
use Rembon\LaravelAuditor\Support\Settings;
use Rembon\LaravelAuditor\Support\UserResolver;
use Throwable;

/**
 * Public entry point (behind the Auditor facade): application wide
 * customisation plus delegation to the per-lifecycle Recorder.
 */
class Auditor
{
    /** @var (Closure(Request): bool)|null */
    protected ?Closure $authUsing = null;

    /** @var (Closure(EntryData): bool)|null */
    protected ?Closure $filterUsing = null;

    /** @var (Closure(mixed): array{name?: string|null, avatar?: string|null})|null */
    protected ?Closure $displayUserUsing = null;

    /** @var string|(Closure(): ?string)|null */
    protected string|Closure|null $nonce = null;

    public function __construct(protected Container $app) {}

    public function recorder(): Recorder
    {
        return $this->app->make(Recorder::class);
    }

    // ---------------------------------------------------------------------
    // Enriching / controlling the current entry
    // ---------------------------------------------------------------------

    public function withProperty(string $key, mixed $value): static
    {
        $this->recorder()->withProperty($key, $value);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function withProperties(array $properties): static
    {
        foreach ($properties as $key => $value) {
            $this->recorder()->withProperty((string) $key, $value);
        }

        return $this;
    }

    public function tag(string ...$tags): static
    {
        $this->recorder()->tag(...$tags);

        return $this;
    }

    public function ignore(): static
    {
        $this->recorder()->ignore();

        return $this;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutAuditing(callable $callback): mixed
    {
        return $this->recorder()->withoutAuditing($callback);
    }

    public function correlationId(): string
    {
        return $this->recorder()->correlationId();
    }

    // ---------------------------------------------------------------------
    // Customisation (call from a service provider)
    // ---------------------------------------------------------------------

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function auth(Closure $callback): static
    {
        $this->authUsing = $callback;

        return $this;
    }

    public function authCallback(): ?Closure
    {
        return $this->authUsing;
    }

    /**
     * @param  Closure(): mixed  $callback  Returns the Authenticatable (or null).
     */
    public function resolveUserUsing(Closure $callback): static
    {
        $resolver = $this->app->make(Contracts\UserResolver::class);

        if ($resolver instanceof UserResolver) {
            $resolver->using($callback);
        }

        return $this;
    }

    /**
     * @param  Closure(EntryData): bool  $callback  Return false to drop the entry.
     */
    public function filter(Closure $callback): static
    {
        $this->filterUsing = $callback;

        return $this;
    }

    public function passesFilter(EntryData $entry): bool
    {
        return $this->filterUsing === null
            || (bool) $this->guard(fn () => ($this->filterUsing)($entry), true);
    }

    /**
     * @param  Closure(string, mixed): bool  $callback  Return true to redact.
     */
    public function redactUsing(Closure $callback): static
    {
        $this->app->make(Redactor::class)->using($callback);

        return $this;
    }

    /**
     * @param  Closure(Container): Storage  $callback
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->app->make(StorageManager::class)->extend($driver, $callback);

        return $this;
    }

    /**
     * @param  Closure(mixed): array{name?: string|null, avatar?: string|null}  $callback
     */
    public function displayUserUsing(Closure $callback): static
    {
        $this->displayUserUsing = $callback;

        return $this;
    }

    /**
     * @return array{name?: string|null, avatar?: string|null}
     */
    public function displayUser(mixed $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($this->displayUserUsing !== null) {
            return (array) $this->guard(fn () => ($this->displayUserUsing)($user), []);
        }

        $name = data_get($user, 'name') ?? data_get($user, 'email');

        return ['name' => is_scalar($name) ? (string) $name : null];
    }

    /**
     * Set the CSP nonce used for the dashboard's inline script and style.
     *
     * @param  string|(Closure(): ?string)  $nonce
     */
    public function useNonce(string|Closure $nonce): static
    {
        $this->nonce = $nonce;

        return $this;
    }

    public function nonce(): ?string
    {
        $nonce = $this->nonce instanceof Closure ? ($this->nonce)() : $this->nonce;

        return $nonce ?? (class_exists(\Illuminate\Foundation\Vite::class) ? Vite::cspNonce() : null);
    }

    // ---------------------------------------------------------------------
    // Persistence
    // ---------------------------------------------------------------------

    public function storage(): Storage
    {
        return $this->app->make(StorageManager::class)->driver();
    }

    /**
     * Persist an entry (null when it was dropped) and model changes, either
     * right away or through the queue.
     *
     * @param  list<ModelChangeData>  $changes
     */
    public function persist(?EntryData $entry, array $changes = []): void
    {
        if ($entry === null && $changes === []) {
            return;
        }

        $this->guard(function () use ($entry, $changes): void {
            if (config('auditor.queue.enabled')) {
                $job = new PersistEntry($entry?->toArray(), array_map(fn (ModelChangeData $c): array => $c->toArray(), $changes));

                dispatch($job
                    ->onConnection(Settings::nullableString('auditor.queue.connection'))
                    ->onQueue(Settings::nullableString('auditor.queue.queue')));

                return;
            }

            $this->storeNow($entry, $changes);
        });
    }

    /**
     * @param  list<ModelChangeData>  $changes
     */
    public function storeNow(?EntryData $entry, array $changes = []): void
    {
        $this->recorder()->withoutAuditing(fn () => $this->storage()->store($entry, $changes));

        $events = $this->app->make(Dispatcher::class);

        foreach ($changes as $change) {
            $events->dispatch(new ModelChangeRecorded($change));
        }

        if ($entry?->isComplete()) {
            $events->dispatch(new EntryRecorded($entry));
        }
    }

    /**
     * Run a callback so that a failure inside the auditor never breaks the
     * application: the exception is reported and $default is returned,
     * unless `auditor.throw_exceptions` is enabled.
     *
     * @template TReturn
     * @template TDefault
     *
     * @param  callable(): TReturn  $callback
     * @param  TDefault  $default
     * @return TReturn|TDefault
     */
    public function guard(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (config('auditor.throw_exceptions')) {
                throw $e;
            }

            try {
                report($e);
            } catch (Throwable) {
                // Reporting must never be the thing that breaks the app.
            }

            return $default;
        }
    }
}
