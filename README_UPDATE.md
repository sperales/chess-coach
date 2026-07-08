# Chess Coach v1.1.5 Update Notes

## Release type

Training Center solving UX polish release.

This release improves the exercise-solving flow in `training.php` with a hint action and clearer post-exercise actions.
It also lets the player choose the board piece set from the profile page.

## Changed files

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/review.js`
- `assets/js/training.js`
- `assets/pieces/Set 1/*`
- `assets/pieces/Set 2/*`
- `config/version.php`
- `includes/auth.php`
- `includes/pieces.php`
- `includes/training.php`
- `profile.php`
- `review.php`
- `service-worker.js`
- `sql/install.sql`
- `sql/migrations/021_changes_1.1.5.sql`
- `training.php`

## User-facing changes

- Adds a `Pista` button in Training Center exercises.
- The hint highlights/selects the origin square of the correct move without revealing the destination.
- Removes the `Reintentar selección` button from the exercise controls.
- After solving an exercise or exhausting all attempts, hides `Comprobar`, `Pista` and `Saltar`.
- After finishing an exercise, shows `Siguiente`, `Cerrar` and `Ver partida`.
- Adds piece set selection in `Ajustes / Mi Perfil`.
- Moves the current pieces to `assets/pieces/Set 1`.
- Adds the new pieces as `assets/pieces/Set 2`.
- Applies the selected piece set in `review.php` and `training.php`.
- The app version is bumped to `1.1.5`.
- The PWA service worker cache name is bumped to `chess-coach-v1.1.5-piece-sets`.

## Technical changes

- Exposes only `hint_from` for pending Training Center exercises instead of exposing the full solution.
- Reuses the existing `used_hint` attempt field so hinted attempts remain tracked.
- Adds Training Center control state rendering for active and completed exercise states.
- Adds a next-exercise action that reloads the current training list and opens the next pending item.
- Adds `users.piece_set` to store each user's selected piece set.
- Discovers available piece sets from valid folders inside `assets/pieces`.

## SQL migration

Run this migration after uploading the release:

```text
sql/migrations/021_changes_1.1.5.sql
```

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.1.5-piece-sets
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
- Run `sql/migrations/021_changes_1.1.5.sql`.
- Open `profile.php` and select `Set 2`.
- Open `review.php` and confirm the board uses `Set 2`.
- Open `training.php` and confirm the board uses `Set 2`.
- Switch back to `Set 1` and confirm both boards update.
- Confirm `config/version.php` displays `1.1.5`.
- Confirm no real credentials were committed.
