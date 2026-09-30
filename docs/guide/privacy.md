# Privacy & redaction

Laravel Auditor is built to be kept in production, so it stores as little personal data
as it needs.

## Redaction

Values whose **key** matches `redaction.keys` are replaced with `[REDACTED]` — in model
diffs, request input, properties, ability arguments and URL query strings, recursively
and case-insensitively:

```php
'keys' => [
    '*password*', '*token*', '*secret*', 'api_key', 'apikey', 'authorization',
    'two_factor_*', 'credit_card*', 'card_number', 'cvv', 'cvc', 'pin', 'ssn',
],
```

Model attributes in `$hidden` and encrypted casts are always redacted as well
(`redact_hidden_attributes`, `redact_encrypted_casts`). For anything else:

```php
Auditor::redactUsing(fn (string $key, mixed $value) => $key === 'nik');
```

## What is never stored

- **Mail bodies.** Only the mailable class, subject and recipients — or a SHA-256 hash
  of each recipient with `mail.hash_recipients`, or no recipients with
  `mail.record_recipients = false`.
- **Request input**, unless you enable `http.capture.input`.

## GDPR helpers

| Option | Effect |
|---|---|
| `http.capture.anonymize_ip` | IPv4 → `/24`, IPv6 → `/48` |
| `http.capture.ip`, `http.capture.user_agent` | turn off entirely |
| `prune.keep_days` + `auditor:prune` | retention window |

When integrity is enabled, pruning writes a checkpoint first so the remaining chain
still verifies.
