# Chess Coach v0.8.8 Update Notes

## Release type

Small review page visual refinement.

This version changes only the evaluation chart styling on the game review page. It does not change database schema, analysis logic, Stockfish integration, move parsing, evaluation calculation, or review data interpretation.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `assets/css/app.css`
- `assets/js/review.js`
- `config/version.php`
- `service-worker.js`

## User-facing change

The review page evaluation chart now uses a light chart area with a darker evaluation line, closer to the target visual style.

Critical move markers remain color-coded.

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

No SQL migration is required for v0.8.8.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.8.8
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Manual verification checklist

- Confirm `config/version.php` reports `0.8.8`.
- Confirm `service-worker.js` uses `chess-coach-v0.8.8`.
- Open an analyzed game review page.
- Confirm the "Gráfico de evaluación" chart area is light.
- Confirm the evaluation line and critical move markers remain visible.
- Confirm no real credentials were committed.
