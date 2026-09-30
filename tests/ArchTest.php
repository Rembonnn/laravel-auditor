<?php

declare(strict_types=1);

// The workbench demo app is not part of the package.
arch()->preset()->php()->ignoring('Workbench');

arch()->preset()->security()->ignoring('Workbench');

arch('does not depend on the application namespace')
    ->expect('Rembon\LaravelAuditor')
    ->not->toUse(['App', 'App\Models\User', 'App\Http\Controllers\Controller']);

arch('no debug helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('DTOs are readonly')
    ->expect('Rembon\LaravelAuditor\Data')
    ->toBeReadonly();

arch('strict types everywhere')
    ->expect('Rembon\LaravelAuditor')
    ->toUseStrictTypes();

it('never prints audit data unescaped in views', function (): void {
    // Only static, package-owned markup (SVG icons) may use {!! !!}.
    $allowed = [
        'components/icon.blade.php',
    ];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../resources/views'));
    $offenders = [];

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace(realpath(__DIR__.'/../resources/views').'/', '', $file->getRealPath());

        if (! in_array($relative, $allowed, true) && str_contains((string) file_get_contents($file->getRealPath()), '{!!')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});

it('never mixes an inline @php() before a @php block in one view', function (): void {
    // Blade would then swallow everything in between as raw PHP.
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../resources/views'));
    $offenders = [];

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $inline = strpos($contents, '@php(');
        $block = preg_match('/@php\s*\n/', $contents, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;

        if ($inline !== false && $block !== false && $inline < $block) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});
