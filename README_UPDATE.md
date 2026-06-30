# Chess Coach v0.9.3 Update Notes

## Release type

UX/UI polish release.

This version adds a dedicated games page with filters and refines analysis queue KPI colors.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/games.php`
- `app.php`
- `assets/css/app.css`
- `assets/js/analysis_queue.js`
- `assets/js/games.js`
- `config/version.php`
- `games.php`
- `includes/helpers.php`
- `service-worker.js`

## User-facing changes

- The hamburger menu "Partidas" item now opens a dedicated `games.php` page.
- The home "Ver todas" link now opens the dedicated games page.
- The dedicated games page shows the same table fields as the home list.
- The games page includes filters for:
  - Color: blancas / negras
  - Result: ganadas / perdidas / tablas
  - Smart Tag
- The analysis queue "Analizadas" KPI icon is green.
- The analysis queue "Errores" KPI icon is red.

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

No SQL migration is required for v0.9.3.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.3
```

The service worker asset list now includes:

- `games.php`
- `assets/js/games.js`

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax checks passed locally with:

```powershell
node --check assets\js\analysis_queue.js
node --check assets\js\games.js
```

## Manual verification checklist

- Confirm `config/version.php` reports `0.9.3`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.3`.
- Open the hamburger menu and confirm "Partidas" opens `games.php`.
- Confirm the games page paginates and displays the same table fields as the home list.
- Confirm filters work individually and combined.
- Confirm queue KPI colors: "Analizadas" green, "Errores" red.
- Confirm no real credentials were committed.
