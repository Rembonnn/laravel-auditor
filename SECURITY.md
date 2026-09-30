# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 3.x | ✅ |
| 2.x | Security fixes only, until 6 months after the 3.0.0 release |
| < 2 | ❌ |

## Reporting a vulnerability

Please **do not** open a public issue. Report it privately through
[GitHub Security Advisories](https://github.com/Rembonnn/laravel-auditor/security/advisories/new).

Include the affected version, a description, and steps to reproduce. You will get an
answer within 7 days. Once a fix is released, the advisory is published and you are
credited unless you prefer otherwise.

## Scope notes

- The dashboard is closed outside the `local` environment unless the `viewAuditor`
  gate (or `Auditor::auth()`) allows access.
- Integrity (hash chain) protects against changes made by someone with database access
  but **not** the `AUDITOR_INTEGRITY_KEY`. An attacker holding both the key and write
  access to the database can rebuild the chain.
