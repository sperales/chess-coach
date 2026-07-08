# Chess Coach v1.1.5 Update Notes

## Release type

Training Center solving UX polish release.

This release improves the exercise-solving flow in `training.php` with a hint action and clearer post-exercise actions.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/training.js`
- `config/version.php`
- `includes/training.php`
- `service-worker.js`
- `training.php`

## User-facing changes

- Adds a `Pista` button in Training Center exercises.
- The hint highlights/selects the origin square of the correct move without revealing the destination.
- Removes the `Reintentar selección` button from the exercise controls.
- After solving an exercise or exhausting all attempts, hides `Comprobar`, `Pista` and `Saltar`.
- After finishing an exercise, shows `Siguiente`, `Cerrar` and `Ver partida`.
- The app version is bumped to `1.1.5`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.5-training-hints`.

## Technical changes

- Exposes only `hint_from` for pending Training Center exercises instead of exposing the full solution.
- Reuses the existing `used_hint` attempt field so hinted attempts remain tracked.
- Adds Training Center control state rendering for active and completed exercise states.
- Adds a next-exercise action that reloads the current training list and opens the next pending item.

## SQL migration

No SQL migration is required.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.5-training-hints
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
- Open a pending exercise.
- Click `Pista` and confirm the origin square of the correct move is highlighted/selected.
- Confirm `Pista` does not reveal the destination square.
- Confirm the attempt stores `used_hint` when a move is submitted after using a hint.
- Confirm `Reintentar selección` is no longer shown.
- Solve an exercise and confirm `Comprobar`, `Pista` and `Saltar` are hidden.
- Exhaust 5 attempts and confirm `Comprobar`, `Pista` and `Saltar` are hidden.
- Confirm `Siguiente`, `Cerrar` and `Ver partida` are shown after solving or exhausting attempts.
- Click `Siguiente` and confirm the next pending exercise opens.
- Confirm `Cerrar` closes the solver panel.
- Confirm `Ver partida` opens the related review page.
- Confirm `config/version.php` displays `1.1.5`.
- Confirm no real credentials were committed.
