# Configuration

All options live in `config/auditor.php` (published by `auditor:install`). The most
common ones can be set from `.env`:

| Variable | Default | Purpose |
|---|---|---|
| `AUDITOR_ENABLED` | `true` | Master switch. The dashboard stays reachable when off. |
| `AUDITOR_DRIVER` | `database` | `database`, `log`, `null` or a [custom driver](./storage). |
| `AUDITOR_DB_CONNECTION` | default | Keep the audit trail on its own connection. |
| `AUDITOR_QUEUE` | `false` | Persist entries through a queued job. |
| `AUDITOR_KEEP_DAYS` | `90` | Retention used by `auditor:prune`. |
| `AUDITOR_INTEGRITY` / `AUDITOR_INTEGRITY_KEY` | off | [Hash chain](./integrity). |
| `AUDITOR_PATH` / `AUDITOR_DOMAIN` | `auditor` | Where the dashboard lives. |
| `AUDITOR_THEME` | `system` | Initial dashboard theme. |
| `AUDITOR_THROW` | `false` | Throw recording errors (use `true` in tests). |

The full file with every option and its default is in the
[configuration reference](../reference/config).

::: warning Change `morph_key_type` before migrating
`storage.database.morph_key_type` (`string`, `int`, `uuid` or `ulid`) is read by the
migration to create the `user_id` / `auditable_id` columns. The default `string` works
with every key type.
:::
