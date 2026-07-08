# Chess Coach v1.1.4 Update Notes

## Release type

Training Center prompt clarity release.

This release makes the side to move easier to identify while solving Training Center exercises, improves the review move list layout and adds legal move hints while solving exercises.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/review.js`
- `assets/js/training.js`
- `config/version.php`
- `service-worker.js`

## User-facing changes

- `training.php` now emphasizes the side to move inside the exercise prompt.
- The side-to-move text is shown in bold.
- A white or black pawn icon is displayed next to `Juegan blancas` or `Juegan negras`.
- The Training Center solver now shows a clear per-exercise timer above the move selection text.
- When an exercise reaches 5 failed attempts and reveals the solution, the solution origin and destination squares are highlighted in red.
- When selecting a piece in `training.php`, legal destination squares are shown with subtle grey dots.
- Training exercises are closed after 5 failed attempts so they stop appearing as pending recommendations.
- `review.php` now shows the move list as one row per full move, with White on the left and Black on the right.
- Exercise prompts that do not include a side-to-move phrase continue rendering normally.
- The app version is bumped to `1.1.4`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.4-training-legal-targets`.

## Technical changes

- Adds prompt formatting in `assets/js/training.js` without changing stored exercise prompts.
- Adds client-side exercise timer state in `assets/js/training.js`.
- Stores the revealed solution move client-side only when the API exposes it after failed attempts.
- Adds client-side legal move generation for Training Center board hints.
- Marks exhausted Training Center exercises as no longer pending while keeping the attempt result as failed.
- Groups review moves by full move number in `assets/js/review.js`.
- Reuses local piece images from `assets/pieces/`.
- Adds compact side-to-move prompt styles in `assets/css/app.css`.
- Keeps exercise generation, validation and attempt tracking unchanged.

## SQL migration

No SQL migration is required.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.4-training-legal-targets
```

## Local verification commands

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\training.js
node --check assets\js\review.js
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
- Select a movable piece in `training.php` and confirm legal destinations are shown with grey dots.
- Select an empty square or an opponent piece and confirm no legal destinations are shown.
- Fail an exercise 5 times, return to the training list and confirm it no longer appears as pending/recommended.
- Confirm the exercise title, tags, board and move submission still work.
- Open `review.php` for an analyzed game.
- Confirm the move list shows each full move in one row, with White and Black side by side.
- Confirm `config/version.php` displays `1.1.4`.
- Confirm no real credentials were committed.
