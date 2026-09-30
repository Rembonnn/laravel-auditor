# Storage drivers

| Driver | Use it for |
|---|---|
| `database` (default) | Everything, including the dashboard, integrity and pruning. |
| `log` | Shipping entries to a log channel (`storage.log.channel`) — e.g. a SIEM. |
| `null` | Disabling storage while keeping the rest of the app unchanged. |

## A separate connection

```dotenv
AUDITOR_DB_CONNECTION=audit
```

Table names are configurable in `storage.database.tables`.

## A custom driver

Implement `Rembon\LaravelAuditor\Contracts\Storage`:

```php
use Rembon\LaravelAuditor\Contracts\Storage;
use Rembon\LaravelAuditor\Data\EntryData;

final class S3Storage implements Storage
{
    public function store(?EntryData $entry, array $changes = []): void
    {
        // $entry is null when only detached model changes are stored.
        // The same entry (same ulid) can arrive twice: first partial (buffered
        // changes flushed early), then complete. Treat it as an upsert.
    }
}
```

Register and select it:

```php
Auditor::extend('s3', fn (Application $app) => new S3Storage(/* ... */));
```

```dotenv
AUDITOR_DRIVER=s3
```

`EntryData` and `ModelChangeData` only hold scalars and arrays, so they are safe to
serialise.
