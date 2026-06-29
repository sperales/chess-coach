# Chess Coach v0.8.6 Update Notes

## Release type

Repository hygiene and documentation release.

This version does not change application behavior, database schema, analysis logic, Stockfish integration, or review-board behavior.

## Changed files

- `.gitignore`
- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `config/.htaccess`
- `config/version.php`
- `service-worker.js`
- `storage/logs/.gitkeep`
- `worker/.htaccess`

## Deployment notes

Upload the changed files to the server.

Do not overwrite real environment config files:

```text
config/database.php
config/engine.php
config/cron.php
```

The repository now includes:

- `config/.htaccess`
- `worker/.htaccess`
- `storage/logs/.gitkeep`

Real log files under `storage/logs/` should remain uncommitted.

## SQL migration

No SQL migration is required for v0.8.6.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.8.6
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Manual verification checklist

- Confirm `config/version.php` reports `0.8.6`.
- Confirm `service-worker.js` uses `chess-coach-v0.8.6`.
- Confirm `/config` remains protected by `.htaccess`.
- Confirm `/worker` remains protected by `.htaccess`.
- Confirm `storage/logs/` exists on the server.
- Confirm no real credentials were committed.
