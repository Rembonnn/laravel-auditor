# Upgrade guide

## From 2.x to 3.0

v3 is a rewrite. Plan for roughly 30 minutes.

### 1. Requirements

PHP **8.3+** and Laravel **12 or 13**.

### 2. Update the package

```bash
composer require rembon/laravel-auditor:^3.0
```

### 3. Remove the v2 wiring from your application

- The provider in `config/app.php` (package auto-discovery registers it now).
- The `AuthorizeMail` / `AuthorizeNotification` listeners in your `EventServiceProvider`
  (v3 registers its own listeners).
- `config/laravel-auditor.php`
- `public/vendor/laravel-auditor`
- `resources/views/vendor/auditor`

### 4. Install

```bash
php artisan auditor:install
```

This publishes `config/auditor.php` and the migrations, asks to migrate, and prints the
next steps. The new tables are prefixed (`auditor_entries`, `auditor_model_changes`,
`auditor_checkpoints`), so they never clash with the v2 `audits` table.

### 5. Define who may open the dashboard

**Without this gate the dashboard is closed outside `local`.**

```php
// AppServiceProvider::boot()
Gate::define('viewAuditor', fn (User $user) => $user->is_admin);
```

### 6. (Optional) import v2 data

```bash
php artisan auditor:import-v2 --user-model="App\Models\User"
php artisan auditor:import-v2 --drop-old   # after checking the result
```

Raw emails stored by v2 are **not** imported. The command is safe to run more than once.

### 7. The trait

`Rembon\LaravelAuditor\Traits\Auditable` keeps its namespace. It now also records
created/updated/deleted/restored/force-deleted changes with old and new values.
To stop recording which records were read for one model:

```php
protected bool $auditRetrieved = false;
```

### 8. Configuration

| v2 | v3 |
|---|---|
| `AUDITOR_ENABLE_PERFORMANCE` | Removed — use Laravel Pulse |
| `AUDITOR_ENABLE_VIEWS` | `AUDITOR_DASHBOARD` |
| `user_model`, `user_owner_key` | Removed — the user is resolved from the auth guards and stored polymorphically |

The config key changed from `laravel-auditor` to `auditor`.

### 9. API

The `Contracts\Auditor` interface is replaced by the `Auditor` facade.

| v2 | v3 |
|---|---|
| `addProperty()` | `Auditor::withProperty()` |
| `addUser()`, `onRoute()`, `onUrl()`, `finish()` | Internal, no longer public |

### Removed features

Performance metrics (use Pulse), the route viewer (`php artisan route:list`), the
migration viewer and the model list.
