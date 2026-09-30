<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\View\View;
use Rembon\LaravelAuditor\Console\SealCommand;
use Rembon\LaravelAuditor\Console\VerifyCommand;
use Rembon\LaravelAuditor\Models\Checkpoint;
use Rembon\LaravelAuditor\Models\Entry;
use Rembon\LaravelAuditor\Models\ModelChange;
use Rembon\LaravelAuditor\Support\Settings;

final class IntegrityController
{
    public function __invoke(Cache $cache): View
    {
        $enabled = (bool) config('auditor.integrity.enabled');

        return view('auditor::integrity', [
            'enabled' => $enabled,
            'hasKey' => Settings::string('auditor.integrity.key') !== '',
            'verify' => $cache->get(VerifyCommand::CACHE_KEY),
            'seal' => $cache->get(SealCommand::CACHE_KEY),
            'totals' => [
                'entries' => Entry::query()->count(),
                'model_changes' => ModelChange::query()->count(),
            ],
            'unsealed' => $enabled ? [
                'entries' => Entry::query()->whereNull('hash')->count(),
                'model_changes' => ModelChange::query()->whereNull('hash')->count(),
            ] : null,
            'checkpoint' => Checkpoint::query()->orderByDesc('id')->first(),
        ]);
    }
}
