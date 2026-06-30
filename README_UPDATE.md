# Chess Coach v0.9.1 Update Notes

## Release type

Small polish release.

This version improves the review board piece rendering and replaces the dashboard placeholder for "Accuracy media" with a value calculated from analyzed games.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/games.php`
- `assets/js/app.js`
- `assets/js/review.js`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- White pieces in the review board now use the same filled Unicode glyphs as black pieces, with the white color applied through CSS.
- The home dashboard now shows "Accuracy media" based on completed game analyses.
- If no games have completed analysis yet, the metric keeps showing `--`.

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

No SQL migration is required for v0.9.1.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.1
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check could not be run because Node.js is not installed locally.

## Manual verification checklist

- Confirm `config/version.php` reports `0.9.1`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.1`.
- Open the home page and confirm "Accuracy media" shows a percentage when analyzed games exist.
- Open `review.php` and confirm white pieces are clearly visible on light squares.
- Confirm no real credentials were committed.
