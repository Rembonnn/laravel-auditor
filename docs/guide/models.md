# Model auditing

```php
use Rembon\LaravelAuditor\Traits\Auditable;

class Post extends Model
{
    use Auditable;

    // All optional:
    protected array $auditExclude = ['view_count'];          // never recorded
    protected array $auditEvents = ['updated', 'deleted'];   // default: config('auditor.models.events')
    protected bool $auditRetrieved = false;                  // don't track reads of this model
}
```

## What is stored

| Event | `old_values` | `new_values` |
|---|---|---|
| `created` | — | all attributes |
| `updated` | original values of the changed attributes | the changed attributes |
| `deleted` | all attributes | soft deletes: `deleted_at` |
| `restored`, `force_deleted` | as above | as above |

- An update that only touches excluded attributes (e.g. `touch()` with `updated_at`
  excluded) is **not** recorded.
- JSON columns are stored decoded; dates as they are stored.
- `$hidden` attributes and encrypted casts are redacted — see [Privacy](./privacy).

## Reading the history

```php
$post->audits;   // ModelChange collection, newest first

use Rembon\LaravelAuditor\Models\ModelChange;

ModelChange::query()
    ->causedBy($user)
    ->since(now()->subWeek())
    ->forModel('post', $post->id)
    ->get();

$change->changedAttributes();   // ['title', 'body']
$change->entry;                 // the request / job / command
```

## Pausing auditing

```php
Auditor::withoutAuditing(fn () => $post->update(['views' => $post->views + 1]));
```

Nested calls are safe and the state is restored even when the callback throws.

## Limitations

Mass updates and deletes through the query builder, `insert()`, `upsert()` and pivot
`attach()` / `detach()` without a custom pivot model do not fire Eloquent events, so
they are not recorded as model changes. The entry itself (the request) is still recorded.
