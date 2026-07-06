# Chess Coach v1.0.1 Update Notes

## Release type

Small UI polish release.

This release improves Smart Tag navigation in the dedicated games list.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/js/dashboard.js`
- `assets/js/games.js`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- Smart Tag chips shown inside `games.php` are now clickable.
- Clicking a tag opens the games list filtered by that tag through `games.php?tag=...`.
- The home "Pendientes de análisis" KPI now shows `0` correctly and links "Ver cola" to `analysis-pending.php`.
- The home greeting highlights the current main focus in bold.
- The "Resumen de últimas partidas" error KPI now labels counts as `B`, `M` and `I`.
- `config/version.php` is bumped to `1.0.1`.
- The PWA service worker cache name is bumped to `chess-coach-v1.0.1`.

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

No SQL migration is required for this release.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.0.1
```

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check passed locally with:

```powershell
node --check assets\js\games.js
node --check assets\js\dashboard.js
```

## Manual verification checklist

- Confirm `games.php` loads the list of games.
- Confirm clicking a Smart Tag chip in the games list filters the list by that tag.
- Confirm the tag filter select reflects the clicked tag after the filtered page loads.
- Confirm the home "Pendientes de análisis" KPI shows `0` when there are no pending games.
- Confirm clicking "Ver cola" in that KPI opens `analysis-pending.php`.
- Confirm the home greeting bolds only the main focus after the colon.
- Confirm the "Errores" KPI uses the `B:x/M:y/I:z` format.
- Confirm the header/footer version displays `1.0.1`.
- Confirm the service worker cache name is `chess-coach-v1.0.1`.
- Confirm no real credentials were committed.
