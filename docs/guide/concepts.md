# Entries, changes & correlation

## Entry

An **entry** is one lifecycle of your application: an HTTP request, a queued job, an
artisan command, or (rarely) `other` — a model change made outside of any of these.

An entry records:

- **who** — the user (`user_type`, `user_id`, `guard`) and, for commands, the OS user and host;
- **what** — route name/action, method, URL (query string redacted), status code or exit code, duration;
- **what they were allowed to do** — every Gate ability checked, granted or denied, with a counter;
- **what they read** — the records retrieved per model class (up to `max_ids_per_model` ids, plus a count);
- **what went out** — mails (mailable, subject, recipients, never the body) and notifications;
- **context you add** — properties and tags via the [facade](../reference/facade).

## Model change

A **model change** is one created / updated / deleted / restored / force-deleted event
of an `Auditable` model with its `old_values` and `new_values`. It belongs to the entry
that caused it, so you always know which request, job or command changed a record, and
who was behind it.

## Correlation

Every entry has a `correlation_id`. A queued job inherits the correlation ID (and the
causing user) of the request or command that dispatched it, so the dashboard can show a
**timeline** of everything that happened because of one click.

The ID is returned in the `X-Request-Id` response header. An incoming `X-Request-Id` is
only reused when `http.trust_incoming_correlation_id` is `true` (for example behind a
gateway you control); it must match `[A-Za-z0-9-_]{1,64}`.

```php
Auditor::correlationId();                         // the current one
Entry::query()->forCorrelation($id)->get();       // request + its jobs
```

## Sampling

`http.sample_rate` records only a share of requests (`0.0`–`1.0`). Entries that changed
a model or had a denied ability are **always** recorded, so sampling never hides what
matters for an audit.
