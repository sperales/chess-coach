# Chess Coach v1.0.1 Update Notes

## Release type

Small UI polish release.

This release improves Smart Tag navigation in the dedicated games list.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/chesscom.php`
- `api/games.php`
- `games.php`
- `assets/css/app.css`
- `assets/js/dashboard.js`
- `assets/js/games.js`
- `config/version.php`
- `includes/pgn.php`
- `service-worker.js`
- `sql/install.sql`
- `sql/migrations/019_changes_1.0.1.sql`

## User-facing changes

- Smart Tag chips shown inside `games.php` are now clickable.
- Clicking a tag opens the games list filtered by that tag through `games.php?tag=...`.
- The home "Pendientes de análisis" KPI now shows `0` correctly and links "Ver cola" to `analysis-pending.php`.
- The home greeting highlights the current main focus in bold.
- The "Resumen de últimas partidas" error KPI now labels counts as `B`, `M` and `I`.
- The "Resumen de últimas partidas" block now includes an `Accuracy` KPI between `Win rate` and `ACPL`.
- The home games panel now shows only the alternate toggle button: `Recomendadas` while viewing latest games, and `Últimas` while viewing recommended games.
- `games.php` now includes an `Apertura` column with the opening name and ECO code when available.
- New manual and Chess.com imports store optional ECO/opening metadata from PGN tags.
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

Run this migration on the server:

```text
sql/migrations/019_changes_1.0.1.sql
```

It adds optional `eco_code` and `opening_name` columns to `games`.

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
- Confirm the "Resumen de últimas partidas" block shows five KPI blocks, including `Accuracy`.
- Confirm the home games panel shows only the alternate toggle button for the current list mode.
- Confirm `games.php` shows the `Apertura` column.
- Confirm games with PGN `ECO`/`Opening` tags show opening name plus ECO, or only ECO when the name is missing.
- Confirm manual and Chess.com imports still work after running the SQL migration.
- Confirm the header/footer version displays `1.0.1`.
- Confirm the service worker cache name is `chess-coach-v1.0.1`.
- Confirm no real credentials were committed.
