# Chess Coach v1.1.0 Update Notes

## Release type

Training Center foundation release.

This PR starts v1.1.0 by adding the database model and backend helper layer needed to generate and track exercises from analyzed games.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/analyze.php`
- `api/training.php`
- `app.php`
- `assets/css/app.css`
- `assets/js/training.js`
- `config/version.php`
- `includes/helpers.php`
- `includes/analysis_queue.php`
- `includes/training.php`
- `profile.php`
- `service-worker.js`
- `sql/install.sql`
- `sql/migrations/020_changes_1.1.0.sql`
- `training.php`

## User-facing changes

- `profile.php` adds a manual Training Center exercise backfill action under `Procesos batch`.
- `training.php` adds the first Training Center screen with stats, filters and a paginated exercise list.
- The hamburger menu and home quick action now link to `Entrenamiento`.
- The app version is bumped to `1.1.0`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.0-training-page`.

## Technical changes

- Adds Training Center tables for:
  - generated exercises
  - exercise Smart Tag links
  - training sessions
  - exercise attempts
  - exercise generation runs
- Adds `includes/training.php` with shared definitions for exercise types, source side, difficulty, priority, prompts, feedback and basic stats.
- The model supports exercises from both the player's moves and opponent moves.
- Opponent-derived exercises are designed as low-priority `other` exercises.
- Resolved exercises can be kept out of the recommended queue while unresolved exercises remain available.
- Completed Stockfish analyses now try to generate Training Center exercises automatically.
- Training exercise generation is derived metadata: if it fails, the analysis remains valid and generation can be retried from the profile backfill.
- Adds authenticated `api/analyze.php` actions for Training Center backfill and backfill status.
- Adds authenticated `api/training.php` read endpoints for Training Center stats and exercise listing.

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

Run this migration after uploading the changed files:

```text
sql/migrations/020_changes_1.1.0.sql
```

The migration creates the Training Center foundation tables and registers version `1.1.0` in `app_migrations`.

No additional SQL migration is added by the exercise generation PR.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.0-training-page
```

## Local verification commands

Before merging the final v1.1.0 release, run:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check service-worker.js
```

## Manual verification checklist

- Run `sql/migrations/020_changes_1.1.0.sql` on the server.
- Confirm the migration creates the `training_exercises`, `training_exercise_tags`, `training_sessions`, `training_attempts` and `training_generation_runs` tables.
- Run the Training Center backfill from `profile.php` and confirm it creates exercises from already analyzed games.
- Analyze or reanalyze one game and confirm exercises are generated automatically.
- Open `training.php` from the hamburger menu and confirm stats, filters and exercise list load.
- Confirm `Recomendado para mí`, exercise type filters and pending/resolved/all filters work.
- Confirm exercise cards link back to the original review page.
- Confirm `config/version.php` displays `1.1.0`.
- Confirm the service worker cache name is `chess-coach-v1.1.0-training-page`.
- Confirm no real credentials were committed.
