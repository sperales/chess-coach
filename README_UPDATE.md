# Chess Coach v0.9.0 Update Notes

## Release type

Smart Tags feature release.

This version detects, stores and displays Smart Tags for analyzed games and moves. It does not add training recommendations, exercises or a full coaching engine yet.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `api/analyze.php`
- `api/games.php`
- `api/review.php`
- `app.php`
- `assets/css/app.css`
- `assets/js/app.js`
- `assets/js/review.js`
- `config/version.php`
- `includes/analysis_queue.php`
- `includes/smart_tags.php`
- `profile.php`
- `review.php`
- `service-worker.js`
- `sql/install.sql`
- `sql/migrations/017_changes_0.9.0.sql`

## User-facing changes

- Smart Tags are generated automatically after Stockfish analysis completes.
- Game-level tags are shown in the home game list.
- Frequent tags are summarized on the dashboard.
- Game-level tags are shown in review.
- Move-level tags are shown in review for tagged moves.
- A new "Procesos batch" block in profile allows backfilling Smart Tags for previously analyzed games.

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

Run this migration before using Smart Tags:

```text
sql/migrations/017_changes_0.9.0.sql
```

The migration creates:

- `smart_tag_definitions`
- `game_tags`
- `move_tags`
- `smart_tag_runs`

It also inserts the initial Spanish Smart Tags catalog.

## Backfill

Newly analyzed games are tagged automatically.

For games that were already analyzed before v0.9.0, open:

```text
Ajustes / Mi Perfil -> Procesos batch -> Backfill de Smart Tags
```

Each execution processes up to 20 already analyzed games without rerunning Stockfish.

## Service worker

The service worker cache name was updated to:

```text
chess-coach-v0.9.0
```

After deployment, hard refresh the browser or reinstall the PWA if stale cached assets appear.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Manual verification checklist

- Confirm `config/version.php` reports `0.9.0`.
- Confirm `service-worker.js` uses `chess-coach-v0.9.0`.
- Run migration `017_changes_0.9.0.sql`.
- Analyze or reanalyze a game and confirm Smart Tags are inserted.
- Confirm game tags appear on the home page.
- Confirm game and move tags appear on review.
- Open profile and run the Smart Tags backfill batch.
- Confirm the backfill pending count decreases or reports no pending games.
- Confirm no real credentials were committed.
