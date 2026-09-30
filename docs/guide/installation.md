# Installation

Requires **PHP 8.3+** and **Laravel 12 or 13**.

```bash
composer require rembon/laravel-auditor
php artisan auditor:install
```

`auditor:install` publishes `config/auditor.php` and the migrations, offers to run them,
and prints the snippets below. Add `--integrity` to also generate an
`AUDITOR_INTEGRITY_KEY` and enable the [hash chain](./integrity). It is safe to run again;
use `--force` to overwrite the published config.

## 1. Record model changes

Add the `Auditable` trait to the models whose changes you want to keep:

```php
use Rembon\LaravelAuditor\Traits\Auditable;

class Post extends Model
{
    use Auditable;
}
```

Requests, jobs, commands, ability checks, mails and notifications are recorded without
any further setup. See [Model auditing](./models) for the options.

## 2. Open the dashboard to your team

The dashboard at `/auditor` is **closed outside the `local` environment** until you
define the `viewAuditor` gate:

```php
// AppServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::define('viewAuditor', fn (User $user) => $user->is_admin);
```

## 3. Schedule maintenance

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('auditor:prune')->daily();

// Only when integrity is enabled:
Schedule::command('auditor:seal')->everyMinute()->withoutOverlapping();
Schedule::command('auditor:verify')->daily();
```

That's it — open `/auditor`.

## Try it without an app

The repository ships a workbench with realistic demo data:

```bash
git clone https://github.com/Rembonnn/laravel-auditor && cd laravel-auditor
composer install
composer serve   # http://localhost:8000/auditor
```
