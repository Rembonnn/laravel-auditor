<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Replaces sensitive values before anything is stored.
 *
 * A key is sensitive when its lowercase form matches one of the
 * `auditor.redaction.keys` patterns (Str::is) or when the custom
 * callback registered through Auditor::redactUsing() returns true.
 */
final class Redactor
{
    /** @var (Closure(string, mixed): bool)|null */
    private ?Closure $callback = null;

    public function __construct(private readonly Config $config) {}

    /**
     * @param  (Closure(string, mixed): bool)|null  $callback
     */
    public function using(?Closure $callback): void
    {
        $this->callback = $callback;
    }

    public function replacement(): string
    {
        return Values::toString($this->config->get('auditor.redaction.replacement')) ?? '[REDACTED]';
    }

    public function isSensitive(string|int $key, mixed $value = null): bool
    {
        $key = Str::lower((string) $key);

        foreach ((array) $this->config->get('auditor.redaction.keys', []) as $pattern) {
            if (is_string($pattern) && Str::is(Str::lower($pattern), $key)) {
                return true;
            }
        }

        return $this->callback !== null && ($this->callback)($key, $value) === true;
    }

    /**
     * Redact an array recursively.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitive($key, $value)) {
                $data[$key] = $this->replacement();
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * Redact the values of sensitive query string parameters, keeping the
     * rest of the URL byte-for-byte intact.
     */
    public function redactUrl(string $url): string
    {
        $queryStart = strpos($url, '?');

        if ($queryStart === false) {
            return $url;
        }

        $fragment = '';
        $query = substr($url, $queryStart + 1);

        if (($hash = strpos($query, '#')) !== false) {
            $fragment = substr($query, $hash);
            $query = substr($query, 0, $hash);
        }

        $pairs = array_map(function (string $pair): string {
            if (! str_contains($pair, '=')) {
                return $pair;
            }

            [$name, $value] = explode('=', $pair, 2);

            // "user[password]" is checked as "password", "tokens[]" as "tokens".
            $decoded = urldecode($name);
            $leaf = preg_match_all('/\[([^\]]*)\]/', $decoded, $matches) && ($last = end($matches[1])) !== ''
                ? $last
                : Str::before($decoded, '[');

            return $this->isSensitive($leaf, urldecode($value)) || $this->isSensitive(Str::before($decoded, '['))
                ? $name.'='.rawurlencode($this->replacement())
                : $pair;
        }, explode('&', $query));

        return substr($url, 0, $queryStart + 1).implode('&', $pairs).$fragment;
    }

    /**
     * Redact model attributes: hidden attributes, encrypted casts and keys
     * matching the configured patterns. Values are expected to be raw.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function redactModelAttributes(Model $model, array $attributes): array
    {
        $hidden = $this->config->get('auditor.redaction.redact_hidden_attributes', true)
            ? array_flip($model->getHidden())
            : [];

        $encrypted = $this->config->get('auditor.redaction.redact_encrypted_casts', true)
            ? $this->encryptedAttributes($model)
            : [];

        foreach ($attributes as $key => $value) {
            if (isset($hidden[$key]) || isset($encrypted[$key]) || $this->isSensitive($key, $value)) {
                $attributes[$key] = $this->replacement();
            } elseif (is_array($value)) {
                $attributes[$key] = $this->redact($value);
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, true>
     */
    private function encryptedAttributes(Model $model): array
    {
        $encrypted = [];

        foreach ($model->getCasts() as $key => $cast) {
            $class = Str::before((string) $cast, ':');

            if (Str::startsWith(Str::lower($class), 'encrypted')
                || in_array($class, [AsEncryptedArrayObject::class, AsEncryptedCollection::class], true)) {
                $encrypted[$key] = true;
            }
        }

        return $encrypted;
    }
}
