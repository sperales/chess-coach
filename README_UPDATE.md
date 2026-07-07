# Chess Coach v1.1.1 Update Notes

## Release type

Training Center sessions and polish release.

This release finishes the Training Center v1.1.x workflow by adding measurable training sessions, session-level metrics and final UI/documentation polish.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `api/training.php`
- `assets/css/app.css`
- `assets/js/training.js`
- `config/version.php`
- `includes/training.php`
- `service-worker.js`
- `training.php`

## User-facing changes

- `training.php` now includes a `Sesion de entrenamiento` block.
- Training sessions can be started and closed from the Training Center.
- Opening an exercise starts a session automatically if none is active.
- Session metrics show exercises trained, solved, failed/skipped, attempts and average time.
- The `Saltar` action is now persisted when there is an active session.
- Empty states explain whether there are no exercises at all or only no results for the current filters.
- Exercise feedback now shows remaining attempts before revealing the solution.
- The app version is bumped to `1.1.1`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.1-training-sessions-polish`.

## Technical changes

- Adds Training Center session helpers in `includes/training.php`.
- Adds authenticated `api/training.php` actions for:
  - `session_start`
  - `session_end`
  - `skip`
- Associates exercise attempts with the active `training_sessions` row when a session is active.
- Recalculates session metrics from `training_attempts` after attempts and skips.
- Keeps compatibility with shared hosting and the existing HTTP/PHP architecture.

## SQL migration

No new SQL migration is required.

This release uses the `training_sessions` and `training_attempts` tables added by:

```text
sql/migrations/020_changes_1.1.0.sql
```

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.1-training-sessions-polish
```

## Local verification commands

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\training.js
node --check service-worker.js
git diff --check
```

## Manual verification checklist

- Open `training.php`.
- Confirm the `Sesion de entrenamiento` block appears.
- Start a session manually.
- Open a pending exercise and submit a wrong move.
- Confirm feedback shows remaining attempts.
- Submit the correct move and confirm session metrics update.
- Open another exercise and click `Saltar`; confirm skipped count updates.
- Close the session and confirm the active-session block resets.
- Reload `training.php` and confirm no stale UI appears after the service worker update.
- Confirm `config/version.php` displays `1.1.1`.
- Confirm no real credentials were committed.
