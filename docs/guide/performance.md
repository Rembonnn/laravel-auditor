# Performance & high traffic

Measured overhead is **0.4–0.6 ms per request** (median, 100 models loaded, without
persisting), and memory stays flat for a command that updates 10,000 records.

## How it stays cheap

- Nothing is written in the middle of a request. Entries are persisted in the
  terminating phase, after the response was sent.
- Model changes are buffered and flushed every `buffer_size` changes, so long imports
  don't grow in memory.
- Retrieved models are stored as `class => ids` with at most `max_ids_per_model` ids.
- State is scoped per lifecycle, so it does not leak between requests on Octane or
  between jobs on a worker.

## Tuning for high traffic

1. **Queue persistence** — `AUDITOR_QUEUE=true` (and a dedicated queue with
   `AUDITOR_QUEUE_NAME`). The request only dispatches a small job.
2. **Sampling** — `http.sample_rate = 0.1` keeps 10% of plain requests; entries with
   changes or denied abilities are always kept.
3. **A separate database connection** — `AUDITOR_DB_CONNECTION`.
4. **Retention** — schedule `auditor:prune` with a sensible `AUDITOR_KEEP_DAYS`.
5. **Turn off what you don't need** — `models.track_retrieved`, `listeners.*`,
   `jobs.enabled`, `console.enabled`.
6. **Partitioning** — on very large tables, partition `auditor_entries` and
   `auditor_model_changes` by `created_at` range in your own migration.

The entries list uses cursor pagination, so it stays fast on millions of rows.
