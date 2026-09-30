# Artisan commands

| Command | Options | Description |
|---|---|---|
| `auditor:install` | `--integrity`, `--migrate`, `--force` | Publish config & migrations, optionally generate the integrity key. |
| `auditor:prune` | `--days=` | Delete data older than the retention window (writes a checkpoint first). |
| `auditor:seal` | `--limit=` | Seal unsealed rows into the hash chain. |
| `auditor:verify` | `--table=entries\|model_changes`, `--from=` | Verify the hash chain; non-zero exit on failure. |
| `auditor:import-v2` | `--chunk=500`, `--user-model=`, `--connection=`, `--drop-old` | Import the v2 `audits` table. Raw emails are not imported. |

Publish tags: `auditor-config`, `auditor-migrations`, `auditor-views`, `auditor-lang`.
