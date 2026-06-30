# Chess Coach v0.9.5 Update Notes

## Release type

Cron worker access fix.

This version removes the blocking `worker/.htaccess` file so shared-hosting HTTP GET cron jobs can execute `worker/analyze_queue.php` with the configured token.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `README.md`
- `config/version.php`
- `service-worker.js`
- `worker/.htaccess`

## User-facing changes

- HTTP GET cron execution should work when called as `worker/analyze_queue.php?token=...`.
- The worker remains protected by the token configured in `config/cron.php`.

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

No SQL migration is required for v0.9.5.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.5
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

No JavaScript files changed in this release.

## Manual verification checklist

- Confirm `config/version.php` reports `0.9.5`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.5`.
- Confirm `worker/.htaccess` is not deployed.
- Confirm the hosting cron URL returns JSON when called with the correct token.
- Confirm the hosting cron URL returns `Forbidden` with an invalid or missing token.
- Confirm no real credentials were committed.
