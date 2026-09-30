# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased] — 3.0.0

Complete rewrite. See [UPGRADE.md](UPGRADE.md).

### Added
- One audit entry per HTTP request, queued job and artisan command, with the user,
  route, status, duration, IP, abilities checked, models read, mails and notifications.
- Model change diffs (created / updated / deleted / restored / force deleted) through the
  `Auditable` trait, with `$post->audits` and query scopes.
- Correlation id shared by a request and the jobs it dispatches (`X-Request-Id`),
  including the user who dispatched them.
- Redaction of sensitive keys, `$hidden` attributes and encrypted casts; mail bodies are
  never stored.
- Tamper-evident HMAC hash chain: `auditor:seal`, `auditor:verify`, checkpoints on prune.
- Storage drivers `database`, `log`, `null` and `Auditor::extend()`; optional queueing.
- `auditor:install`, `auditor:prune`, `auditor:import-v2`.
- `Auditor::fake()` with assertions for application tests.
- New dashboard: dark mode, command palette, keyboard shortcuts, quick peek, filters in
  the URL, saved views, live mode, CSV export, model history, integrity status; works
  under a strict CSP and passes axe in both themes.
- Support for PHP 8.3–8.5 and Laravel 12–13; SQLite, MySQL, MariaDB and PostgreSQL.

### Fixed
- Dashboard open to everyone (S1), raw emails and password hashes stored (S2, S3),
  XSS in the dashboard (S4), path traversal in the migration viewer (S5).
- `sleep(1)` on every request (P1), synchronous inserts during the request (P2),
  quadratic model tracking (P3).
- Every functional bug listed in the v3 plan (B1–B11).

### Removed
- Performance metrics, route viewer, migration viewer, model list, the
  yajra/laravel-datatables and jQuery dependencies.
