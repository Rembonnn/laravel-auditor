# Facade

`Rembon\LaravelAuditor\Facades\Auditor`

## Inside a request, job or command

| Method | Description |
|---|---|
| `withProperty(string $key, mixed $value)` | Add a (redacted) property to the current entry. |
| `withProperties(array $properties)` | Add several properties. |
| `tag(string ...$tags)` | Tag the current entry (filterable in the dashboard). |
| `ignore()` | Drop the current entry. Model changes are still kept. |
| `withoutAuditing(callable $callback)` | Nothing inside the callback is recorded. |
| `correlationId(): string` | The current correlation ID. |

## Configuration (in a service provider)

| Method | Description |
|---|---|
| `auth(Closure $callback)` | Who may open the dashboard; overrides the `viewAuditor` gate. |
| `resolveUserUsing(Closure $callback)` | Resolve the causing user yourself. |
| `filter(Closure $callback)` | Return `false` to drop an `EntryData` before it is stored. |
| `redactUsing(Closure $callback)` | `fn (string $key, mixed $value): bool` — extra redaction rule. |
| `extend(string $driver, Closure $callback)` | Register a [storage driver](../guide/storage). |
| `displayUserUsing(Closure $callback)` | Name/avatar shown for users in the dashboard. |
| `useNonce(string\|Closure $nonce)` | CSP nonce for the dashboard's tags. |
| `guard(callable $callback, mixed $default = null)` | Run code the way the package does: report failures instead of throwing. |

## Testing

`fake()` and the assertions listed in [Testing](../guide/testing).

## Models

| Model | Useful API |
|---|---|
| `Models\Entry` | scopes `causedBy($user)`, `since($date)`, `forCorrelation($id)`, `withDeniedAbilities()`, `ofType(EntryType::Job)`; relation `modelChanges()` |
| `Models\ModelChange` | scopes `causedBy`, `since`, `forCorrelation`, `forModel($type, $id)`; relations `entry()`, `auditable()`, `user()`; `changedAttributes()` |
| `Traits\Auditable` | relation `audits()` |
