<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\ViewComposers;

use Composer\InstalledVersions;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Console\VerifyCommand;
use Rembon\LaravelAuditor\Support\Settings;

/**
 * Shared data for the dashboard shell.
 */
final readonly class LayoutComposer
{
    public function __construct(
        private Auditor $auditor,
        private Cache $cache,
    ) {}

    public function compose(View $view): void
    {
        $environment = (string) app()->environment();
        $verify = rescue(fn (): mixed => $this->cache->get(VerifyCommand::CACHE_KEY), null, report: false);

        $view->with([
            'nonce' => $this->auditor->nonce(),
            'brandName' => Settings::nullableString('auditor.dashboard.brand.name') ?? Settings::string('app.name', 'Laravel'),
            'brandLogo' => config('auditor.dashboard.brand.logo'),
            'environment' => $environment,
            'environmentTone' => match ($environment) {
                'production' => 'danger',
                'staging' => 'warning',
                default => 'neutral',
            },
            'themeDefault' => in_array(config('auditor.dashboard.theme'), ['light', 'dark', 'system'], true) ? config('auditor.dashboard.theme') : 'system',
            'pollInterval' => max(0, Settings::int('auditor.dashboard.poll_interval', 5)),
            'timezone' => config('auditor.dashboard.timezone'),
            'integrityFailed' => is_array($verify) && ($verify['valid'] ?? true) === false,
            'version' => rescue(fn () => InstalledVersions::getPrettyVersion('rembon/laravel-auditor'), null, report: false),
        ]);
    }
}
