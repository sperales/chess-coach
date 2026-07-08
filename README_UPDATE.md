# Chess Coach v1.1.3 Update Notes

## Release type

Review board coordinate polish release.

This release adds rank and file coordinates around the review board while preserving the current board rendering and orientation behavior.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/review.js`
- `config/version.php`
- `review.php`
- `service-worker.js`

## User-facing changes

- `review.php` now shows board coordinates next to the board.
- Files are shown below the board and ranks are shown on the left side.
- Coordinates update when the board orientation changes.
- The app version is bumped to `1.1.3`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.3-review-board-coordinates`.

## Technical changes

- Wraps the review board in a coordinate frame without changing the 8x8 board grid.
- Adds coordinate rendering to `assets/js/review.js` based on `boardOrientation`.
- Adds responsive coordinate styles in `assets/css/app.css`.
- Keeps move rendering, highlights, FEN reconstruction and review data unchanged.

## SQL migration

No SQL migration is required.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.3-review-board-coordinates
```

## Local verification commands

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\review.js
node --check service-worker.js
git diff --check
```

## Manual verification checklist

- Open `review.php` for an analyzed game.
- Confirm file letters appear below the board.
- Confirm rank numbers appear on the left side of the board.
- Confirm coordinates match white orientation when the board is shown from White's side.
- Click `Girar tablero` and confirm file/rank coordinates reverse correctly.
- Navigate between moves and confirm coordinates remain visible and stable.
- Confirm board highlights and piece rendering still work.
- Confirm `config/version.php` displays `1.1.3`.
- Confirm no real credentials were committed.
