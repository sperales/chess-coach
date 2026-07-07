# Chess Coach v1.0.4 Update Notes

## Release type

Review player perspective and UX release.

This PR starts v1.0.4 by orienting the review board from the player's perspective.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/review.php`
- `assets/css/app.css`
- `assets/js/review.js`
- `config/version.php`
- `includes/move_notation.php`
- `review.php`
- `service-worker.js`

## User-facing changes

- `review.php` now opens the board from White's perspective when the player was White.
- `review.php` now opens the board from Black's perspective when the player was Black.
- A `Girar tablero` button allows manually flipping the board during review.
- Move origin/destination highlights remain tied to the real board squares.
- Best-move hints no longer show raw UCI as the main visible notation.
- UCI moves are displayed in a human-readable minimal format, including castling and promotions.
- `config/version.php` is bumped to `1.0.4`.
- The PWA service worker cache name is bumped to `chess-coach-v1.0.4`.

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
chess-coach-v1.0.4
```

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check passed locally with:

```powershell
node --check assets\js\review.js
node --check service-worker.js
```

## Manual verification checklist

- Confirm `review.php` opens a game played as White with White at the bottom.
- Confirm `review.php` opens a game played as Black with Black at the bottom.
- Confirm `Girar tablero` flips the current board without changing the selected move.
- Confirm previous/current move highlights remain on the correct squares.
- Confirm previous/next/reset navigation keeps the selected orientation.
- Confirm best-move hints show `e5 -> f6`, `e7 -> e8=Q`, `O-O` or `O-O-O` instead of raw UCI.
- Confirm the header/footer version displays `1.0.4`.
- Confirm the service worker cache name is `chess-coach-v1.0.4`.
- Confirm no real credentials were committed.
