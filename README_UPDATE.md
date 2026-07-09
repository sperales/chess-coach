# Chess Coach v1.2.0 Update Notes

## Release type

Openings Lab feature release.

This release introduces the foundation for practical opening analysis based on the user's own games. The goal is to identify what openings the user plays, how those openings perform, and where early mistakes appear, without turning Chess Coach into a memorization-heavy opening database.

## Planned scope

- Store per-game opening profiles.
- Group openings by `eco_code + opening_name` when available.
- Use the first 16 plies as the opening window.
- Use opening signatures as a fallback when PGN opening metadata is missing.
- Add a backend/API layer for Openings Lab metrics.
- Add a Lab de Aperturas page and navigation entry.
- Connect opening diagnostics with Smart Tags, Review and existing Training Center exercises.
- Keep Training Center exercise generation unchanged in v1.2.0.
- Bump the application version and service worker cache at release readiness.

## PR1 - Openings data foundation

The first v1.2.0 PR adds the database and service foundation only. It does not add user-facing UI.

Changed files:

- `CHANGELOG.md`
- `README_UPDATE.md`
- `includes/analysis_queue.php`
- `includes/openings.php`
- `sql/install.sql`
- `sql/migrations/022_changes_1.2.0.sql`

### Opening profiles

The new `game_opening_profiles` table stores one opening profile per user game:

- user/game/analysis ownership references
- user color
- opening key
- display name
- ECO code
- opening name
- ECO URL
- opening source
- first-move signature
- first 16 SAN/UCI plies

Profiles are grouped by `eco_code + opening_name` when that data exists. If not, Chess Coach falls back to the first 16 plies as an opening signature. If neither metadata nor moves are available, the profile is marked as unknown.

### Profile generation

`includes/openings.php` adds helpers to:

- derive a profile for a single game
- refresh/upsert a game profile
- count pending opening profiles
- run a batch backfill
- log backfill runs in `opening_profile_runs`

New completed analyses now try to refresh the opening profile as derived metadata. A failure in opening profiling does not invalidate the Stockfish analysis.

## PR2 - Openings metrics API

The second v1.2.0 PR adds the backend/API layer for Openings Lab metrics. It does not add the Openings Lab page, menu entry or profile UI.

Changed files:

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/openings.php`
- `includes/openings.php`

### API endpoints

`api/openings.php` exposes authenticated JSON endpoints:

- `GET api/openings.php?action=dashboard`
- `GET api/openings.php?action=list`
- `GET api/openings.php?action=detail&opening_key=...`
- `GET api/openings.php?action=pending_count`
- `POST api/openings.php?action=backfill`

The `backfill` action requires a valid CSRF token. Read-only endpoints are GET-only.

### Metrics available

The Openings Lab backend now calculates:

- games by opening
- wins/draws/losses and score rate
- color split
- opening accuracy from the user's own moves in the first 16 plies
- average centipawn loss in the opening window
- average evaluation after move 10 when centipawn data is available
- blunders, mistakes and inaccuracies by the user in the first 10 full moves
- best/worst example games by opening accuracy
- a simple practical recommendation for each opening

The recommended minimum sample size is 3 games per opening.

## SQL migration

Run:

```text
sql/migrations/022_changes_1.2.0.sql
```

This migration creates:

- `game_opening_profiles`
- `opening_profile_runs`

It also registers version `1.2.0` in `app_migrations`.

## Service worker

No service worker cache change is required in PR1 or PR2 because no browser assets or pages are added yet.

The final v1.2.0 release readiness PR must update `service-worker.js`.

## Local verification commands

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

When JavaScript is added in later PRs, run `node --check` on changed JS files.

## Manual verification checklist

- Apply the SQL migration on the server.
- Analyze a new game and confirm a row appears in `game_opening_profiles`.
- Confirm existing analysis still completes normally.
- Confirm Smart Tags and Training Center generation still run after analysis.
- Confirm no UI change is expected from PR1.
- Call `api/openings.php?action=dashboard` after login and confirm it returns opening metrics JSON.
- Call `api/openings.php?action=detail&opening_key=...` with a returned opening key and confirm it returns the related games.
- Confirm no UI change is expected from PR2.
