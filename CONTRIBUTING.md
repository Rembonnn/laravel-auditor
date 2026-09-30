# Contributing

Thanks for helping! Pull requests are welcome.

## Setup

```bash
composer install
npm install
npx playwright install chromium   # only for the browser tests
```

## Everyday commands

| Command | What it does |
|---|---|
| `composer test` | Unit + feature tests (SQLite, parallel) |
| `composer test:browser` | Dashboard in a real browser: axe, CSP, theme, keyboard |
| `composer test:coverage` | Coverage, must stay ≥ 90% |
| `composer analyse` | Larastan |
| `composer format` | Pint |
| `composer serve` | Workbench app with realistic demo data at `/auditor` |
| `npm run build` | Rebuild `dist/` (commit the result) |
| `npm run size` | Check the asset size budget |

Run the suite against another database with the usual env vars, for example
`DB_CONNECTION=pgsql DB_DATABASE=auditor_test vendor/bin/pest`.

## Guidelines

- Every bug fix comes with a test that fails without it.
- Keep the hot path cheap: the `retrieved` observer runs for every loaded model.
- Never render audit data with `{!! !!}` (an arch test enforces it).
- Dashboard: logic lives in `Alpine.data()` components (CSP build), colours come from
  the tokens in `resources/css/tokens.css`, and every change is checked in both themes.
- Commit messages: imperative mood, short subject line.
