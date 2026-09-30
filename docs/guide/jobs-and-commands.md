# Jobs & commands

## Queued jobs

Each processed job becomes its own entry (`type = job`) with:

- the **correlation ID and user of whoever dispatched it** (carried by Laravel's `Context`);
- `failed = true` when the job failed, and its duration.

With `QUEUE_CONNECTION=sync` a job dispatched inside a request still gets its own entry,
so the result is the same as on a real queue.

Exclude noisy jobs with `jobs.except` (class names). Laravel Auditor's own
`PersistEntry` job is never audited.

## Artisan commands

Each command becomes an entry (`type = command`) with the OS user, hostname, exit code
and — with `console.capture_input` — its redacted arguments and options. That makes a
change made in `php artisan tinker` traceable to a person on a server.

`console.except` takes glob patterns. Workers, schedulers, cache/route/view commands
and `auditor:*` are excluded by default.
