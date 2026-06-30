# Chess Coach v0.9.2 Update Notes

## Release type

UX/UI polish release.

This version makes the motivational quote on the home dashboard dynamic and paginates the analysis queue.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `analysis-pending.php`
- `api/analyze.php`
- `assets/js/analysis_queue.js`
- `app.php`
- `config/app.php`
- `config/version.php`
- `includes/analysis_queue.php`
- `includes/motivational_quotes.php`
- `service-worker.js`
- `sql/install.sql`
- `sql/migrations/018_changes_0.9.2.sql`

## User-facing changes

- The fixed home quote was replaced with a random quote from the database.
- The quote panel keeps a safe fallback phrase if the SQL migration has not run yet.
- The analysis queue is now paginated.
- The default analysis queue page size is configurable with `analysis_per_page` in `config/app.php`.

## Deployment notes

Upload the changed files to the server.

Do not overwrite real environment config files:

```text
config/database.php
config/engine.php
config/cron.php
```

No real config files changed in this release.

## SQL migration

Run this migration:

```text
sql/migrations/018_changes_0.9.2.sql
```

The migration creates:

- `motivational_quotes`

It also inserts the initial quote catalog and registers version `0.9.2` in `app_migrations`.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.2
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check could not be run because Node.js is not installed locally.

## Manual verification checklist

- Run migration `018_changes_0.9.2.sql`.
- Confirm `config/version.php` reports `0.9.2`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.2`.
- Load the home page several times and confirm the quote can change.
- Confirm the home page still loads if the quote table is unavailable during deployment.
- Open `analysis-pending.php` and confirm the queue shows 50 items per page by default.
- Change `analysis_per_page` in `config/app.php` and confirm the queue page size follows the setting.
- Confirm no real credentials were committed.
