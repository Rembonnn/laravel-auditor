# Integrity (hash chain)

With integrity enabled, every sealed row carries

```
hash = HMAC-SHA256(key, previous_hash . canonical_json(row))
```

so changing or removing a row breaks the chain from that point on.

## Enable

```bash
php artisan auditor:install --integrity
```

This writes a random 32-byte `AUDITOR_INTEGRITY_KEY` to `.env` and sets
`AUDITOR_INTEGRITY=true`. **Store the key outside the database** (secret manager,
environment) — whoever holds both can rewrite history.

```php
Schedule::command('auditor:seal')->everyMinute()->withoutOverlapping();
Schedule::command('auditor:verify')->daily();
```

## How sealing works

Recording never takes a global lock. `auditor:seal` runs separately and seals unsealed
rows in id order:

- rows younger than `integrity.seal_delay` seconds wait, so rows committed slightly out
  of id order never fork the chain;
- entries that are still running wait, unless they are older than
  `integrity.stale_after` minutes (the process died);
- two sealers never run on the same table at once (cache lock).

## Verifying

```bash
php artisan auditor:verify                 # both tables
php artisan auditor:verify --table=entries --from=120000
```

It reports the first broken row and why:

| Reason | Meaning |
|---|---|
| `tampered` | the row's content no longer matches its hash |
| `missing_previous_rows` | the row is intact but rows before it were removed |
| `sealed_after_unsealed` | a sealed row follows an unsealed one |

Each violation dispatches `IntegrityViolationDetected` — hook your alerting to it. The
command exits non-zero, so a scheduled run can notify you on failure.

## Pruning

`auditor:prune` writes a checkpoint (last id and hash) before deleting, and with
integrity enabled only deletes sealed rows. Verification continues from the checkpoint.

## What it does not guarantee

- Removing rows **from the end** of the chain cannot be detected by a hash chain alone.
  Ship `auditor:verify` output or the latest hash somewhere else if you need that.
- It does not protect against someone who has **both the key and write access** to the
  database.
- `entries.model_changes_count` and `model_changes.entry_id` are not covered: they
  legitimately change after sealing.
