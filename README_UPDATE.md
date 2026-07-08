# Chess Coach v1.1.4 Update Notes

## Release type

Training Center prompt clarity release.

This release makes the side to move easier to identify while solving Training Center exercises.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/training.js`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- `training.php` now emphasizes the side to move inside the exercise prompt.
- The side-to-move text is shown in bold.
- A white or black pawn icon is displayed next to `Juegan blancas` or `Juegan negras`.
- The Training Center solver now shows a clear per-exercise timer above the move selection text.
- When an exercise reaches 5 failed attempts and reveals the solution, the solution origin and destination squares are highlighted in red.
- Exercise prompts that do not include a side-to-move phrase continue rendering normally.
- The app version is bumped to `1.1.4`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.4-training-center-polish`.

## Technical changes

- Adds prompt formatting in `assets/js/training.js` without changing stored exercise prompts.
- Adds client-side exercise timer state in `assets/js/training.js`.
- Stores the revealed solution move client-side only when the API exposes it after failed attempts.
- Reuses local piece images from `assets/pieces/`.
- Adds compact side-to-move prompt styles in `assets/css/app.css`.
- Keeps exercise generation, validation and attempt tracking unchanged.

## SQL migration

No SQL migration is required.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.4-training-center-polish
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
- Open an exercise where White is to move.
- Confirm `Juegan blancas` is bold and shows a white pawn.
- Open an exercise where Black is to move.
- Confirm `Juegan negras` is bold and shows a black pawn.
- Confirm the timer starts when the exercise opens and stops when the exercise is solved or exhausted.
- Fail 5 attempts and confirm the revealed solution squares are highlighted in red.
- Confirm the exercise title, tags, board and move submission still work.
- Confirm `config/version.php` displays `1.1.4`.
- Confirm no real credentials were committed.
