# Chess Coach v1.0.2 Update Notes

## Release type

Review board piece rendering release.

This release replaces browser-dependent Unicode chess pieces in `review.php` with transparent PNG piece assets.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/review.js`
- `assets/pieces/*.png`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- `review.php` now renders board pieces with local transparent PNG images instead of Unicode glyphs.
- Piece images scale with the square size so the board remains responsive on desktop and mobile.
- Move highlighting still works over the rendered pieces.
- `config/version.php` is bumped to `1.0.2`.
- The PWA service worker cache name is bumped to `chess-coach-v1.0.2`.
- The 12 PNG piece assets are cached by the service worker.

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
chess-coach-v1.0.2
```

The 12 files under `assets/pieces/` are included in the service worker asset list.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check passed locally with:

```powershell
node --check assets\js\review.js
```

## Manual verification checklist

- Confirm `review.php` loads an analyzed game.
- Confirm all white and black pieces render as images on light and dark squares.
- Confirm pieces scale correctly on desktop and mobile board sizes.
- Confirm previous/current move highlights remain visible.
- Confirm move navigation still updates the board position.
- Confirm the header/footer version displays `1.0.2`.
- Confirm the service worker cache name is `chess-coach-v1.0.2`.
- Confirm no real credentials were committed.
