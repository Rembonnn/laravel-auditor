<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Rembon\LaravelAuditor\Support\Dashboard\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves only files listed in dist/manifest.json. Their names carry a
 * content hash, so they can be cached forever.
 */
final class AssetController
{
    private const array TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        abort_unless(isset(self::TYPES[$extension]) && Assets::isServable($file), 404);

        $path = Assets::directory().'/'.$file;

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
