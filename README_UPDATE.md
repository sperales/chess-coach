# Chess Coach v1.1.2 Update Notes

## Release type

Board highlight and Training Center selection polish release.

This release improves board readability in review and training by highlighting moves directly through square colors instead of overlay markers.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/training.js`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- `review.php` now highlights the played move by changing the color of the origin and destination squares.
- `training.php` uses the same square-color highlight for the previous move.
- `training.php` highlights the user's selected origin and destination squares in light blue.
- `training.php` previews the selected move by rendering the piece on the destination square before submitting.
- The app version is bumped to `1.1.2`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.2-board-highlights`.

## Technical changes

- Replaces board highlight pseudo-element overlays with square background colors in `assets/css/app.css`.
- Adds a Training Center board preview grid in `assets/js/training.js` without mutating the stored FEN or server-side move validation.
- Keeps review analysis, exercise solving and server-side validation unchanged.

## SQL migration

No SQL migration is required.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.2-board-highlights
```

## Local verification commands

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\review.js
node --check assets\js\training.js
node --check service-worker.js
git diff --check
```

## Manual verification checklist

- Open `review.php` for an analyzed game.
- Confirm the played move is highlighted by full square color.
- Open `training.php` and start an exercise.
- Confirm the previous move is highlighted by full square color.
- Select an origin and destination square.
- Confirm both selected squares are highlighted in light blue.
- Confirm the selected piece is previewed on the destination square before submitting.
- Submit a move and confirm solving/feedback still works.
- Confirm `config/version.php` displays `1.1.2`.
- Confirm no real credentials were committed.
