# Chess Coach v0.9.4 Update Notes

## Release type

Technical maintenance release.

This version reformats the main stylesheet to make future CSS changes easier to review and maintain.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- No visual or functional changes are intended.
- `assets/css/app.css` is now formatted across multiple lines instead of compacted long rules.

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

No SQL migration is required for v0.9.4.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.4
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

No JavaScript files changed in this release.

## Manual verification checklist

- Confirm `config/version.php` reports `0.9.4`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.4`.
- Open the main app pages and confirm there are no visual regressions from the CSS formatting.
- Confirm no real credentials were committed.
