# Chess Coach v0.8.7 Update Notes

## Release type

Developer verification and documentation release.

This version does not change application behavior, database schema, analysis logic, Stockfish integration, or review-board behavior.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `VERIFY.md`
- `config/version.php`
- `service-worker.js`

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

No SQL migration is required for v0.8.7.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.8.7
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Manual verification checklist

- Confirm `config/version.php` reports `0.8.7`.
- Confirm `service-worker.js` uses `chess-coach-v0.8.7`.
- Confirm `VERIFY.md` is included in the release source archive.
- Confirm no real credentials were committed.
