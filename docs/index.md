---
layout: home

hero:
  name: Laravel Auditor
  text: Who did what, what changed, and what they were allowed to do.
  tagline: A request-level audit trail for Laravel 12 & 13 — production-safe, privacy-aware and tamper-evident.
  actions:
    - theme: brand
      text: Get started
      link: /guide/installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/Rembonnn/laravel-auditor

features:
  - title: One entry per request, job and command
    details: User, route, status, duration, abilities checked, records read, mails and notifications — plus a diff of every model it changed.
  - title: Follows work across the queue
    details: A correlation ID links a request to the jobs it dispatches, so you can see the whole story in one timeline.
  - title: Safe by default
    details: Passwords, tokens, hidden attributes and encrypted casts are redacted. Mail bodies are never stored. Recording never breaks your app.
  - title: Tamper-evident
    details: An HMAC-SHA256 hash chain lets auditor:verify detect modified or removed rows — and it survives pruning.
  - title: A dashboard people like to use
    details: Dark mode, command palette, keyboard shortcuts, quick peek, saved views, live mode and CSV export. Runs under a strict CSP.
  - title: Fits your stack
    details: Database, log or custom storage, queued persistence, Auditor::fake() for tests, and plugins for Filament and Pulse.
---

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="./screenshots/dark.png">
  <img alt="Laravel Auditor dashboard" src="./screenshots/light.png" style="border-radius: 12px; margin-top: 48px; border: 1px solid var(--vp-c-divider)">
</picture>
