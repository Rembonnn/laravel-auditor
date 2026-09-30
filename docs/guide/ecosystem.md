# Filament & Pulse

## Filament

[`rembon/laravel-auditor-filament`](https://github.com/Rembonnn/laravel-auditor-filament)
adds read-only **Audit entries** and **Model changes** resources, an **Audit history**
relation manager for any resource and a stats widget to a Filament 5 panel.

```bash
composer require rembon/laravel-auditor-filament
```

```php
use Rembon\LaravelAuditorFilament\AuditorPlugin;

$panel->plugin(AuditorPlugin::make()->navigationGroup('Audit'));
```

```php
use Rembon\LaravelAuditorFilament\RelationManagers\AuditsRelationManager;

public static function getRelations(): array
{
    return [AuditsRelationManager::class];
}
```

Access follows the dashboard rules (`Auditor::auth()`, then `viewAuditor`, then local).

## Pulse

[`rembon/laravel-auditor-pulse`](https://github.com/Rembonnn/laravel-auditor-pulse)
adds three Pulse cards: **Denied Abilities**, **Top Model Changes** and **Most Active
Users**.

```bash
composer require rembon/laravel-auditor-pulse
```

```php
// config/pulse.php
'recorders' => [
    \Rembon\LaravelAuditorPulse\Recorders\AuditorRecorder::class => [
        'enabled' => true, 'sample_rate' => 1, 'ignore' => [],
    ],
],
```

```blade
<livewire:auditor.denied-abilities cols="4" />
<livewire:auditor.model-changes cols="4" />
<livewire:auditor.active-users cols="4" />
```
