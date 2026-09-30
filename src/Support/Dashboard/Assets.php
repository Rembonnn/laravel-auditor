<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use Illuminate\Support\HtmlString;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Pre-built dashboard assets shipped in dist/ and served by the package
 * (content-hashed names, long-lived cache). Nothing is published.
 */
final class Assets
{
    /** @var array<string, array{file: string}>|null */
    private static ?array $manifest = null;

    public static function directory(): string
    {
        return dirname(__DIR__, 3).'/dist';
    }

    /**
     * @return array<string, array{file: string}>
     */
    public static function manifest(): array
    {
        if (self::$manifest === null) {
            $path = self::directory().'/manifest.json';
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$manifest = [];

            foreach (is_array($decoded) ? $decoded : [] as $entry => $chunk) {
                $file = is_array($chunk) ? Values::nullableString($chunk, 'file') : null;

                if ($file !== null) {
                    self::$manifest[(string) $entry] = ['file' => $file];
                }
            }
        }

        return self::$manifest;
    }

    public static function url(string $entry): ?string
    {
        $file = self::manifest()[$entry]['file'] ?? null;

        return $file === null ? null : route('auditor.assets', ['file' => $file]);
    }

    /**
     * Files that may be served: the manifest entries only.
     */
    public static function isServable(string $file): bool
    {
        foreach (self::manifest() as $chunk) {
            if (($chunk['file'] ?? null) === $file) {
                return true;
            }
        }

        return false;
    }

    public static function css(): HtmlString
    {
        $url = self::url('resources/css/auditor.css');

        return new HtmlString($url === null ? '' : '<link rel="stylesheet" href="'.e($url).'">');
    }

    public static function js(): HtmlString
    {
        $url = self::url('resources/js/auditor.js');

        return new HtmlString($url === null ? '' : '<script type="module" src="'.e($url).'" defer></script>');
    }

    /**
     * The theme bootstrap, inlined (with the CSP nonce) before the CSS.
     */
    public static function themeInit(?string $nonce): HtmlString
    {
        $path = self::directory().'/theme-init.js';
        $script = is_file($path) ? trim((string) file_get_contents($path)) : '';

        return new HtmlString('<script'.($nonce ? ' nonce="'.e($nonce).'"' : '').'>'.$script.'</script>');
    }

    /**
     * Accent override from `auditor.dashboard.accent` (validated hex only).
     */
    public static function accentStyle(?string $nonce): HtmlString
    {
        $accent = config('auditor.dashboard.accent');

        if (! is_string($accent) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $accent) !== 1) {
            return new HtmlString('');
        }

        return new HtmlString(
            '<style'.($nonce ? ' nonce="'.e($nonce).'"' : '').'>:root,.dark{--accent:'.$accent.';--accent-contrast:#fff}</style>'
        );
    }

    public static function flush(): void
    {
        self::$manifest = null;
    }
}
