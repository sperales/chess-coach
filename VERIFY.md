# VERIFY.md

## Purpose

This file documents lightweight verification steps for Chess Coach releases.

The project is designed for shared hosting and intentionally avoids npm, Composer, Docker and build steps. Verification should stay simple and practical.

---

## Required before every release

1. Confirm the working tree only contains intended changes.
2. Run PHP syntax checks.
3. Confirm version files were updated.
4. Confirm the service worker cache name changed.
5. Confirm no real credentials are included.
6. Confirm whether a SQL migration is required.
7. Update `README_UPDATE.md`.
8. Update `CHANGELOG.md`.
9. After the approved version PR is merged to `main`, create the matching tag.
10. After the tag is pushed, create the matching GitHub Release.

---

## PHP Syntax Check

### Windows PowerShell

Run from the repository root:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected result:

```text
No syntax errors detected ...
```

### Nova Presentation Component

```powershell
php tests\nova_component_test.php
```

This verifies state fallback, compact rendering, HTML escaping and internal-only action links.

### Unix-Like Shells

Run from the repository root:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

---

## Version Checks

Confirm the application version:

```powershell
Get-Content config\version.php
```

Confirm the service worker cache name:

```powershell
Select-String -Path service-worker.js -Pattern "CACHE_NAME"
```

Both should match the release version.

---

## Mobile Login Checks

- Confirm the Nova background video plays muted and inline when motion is allowed.
- Confirm the poster remains usable when video playback is unavailable or reduced motion is enabled.
- Check the form at 360 px and 390 px widths without horizontal overflow.
- Confirm password autofill fills the complete rounded input area on iOS.
- Confirm the password visibility control updates its accessible state.
- Submit an expired or invalid CSRF token and confirm authentication is rejected without exposing technical details.

## Mobile Home Checks

- Check the Home at 360 px and 390 px without horizontal overflow.
- Confirm the greeting has no card/background and Nova uses the dedicated pointing image.
- Confirm the training CTA sits outside Nova's card and links to the first incomplete real goal.
- Confirm `7 días`, `30 días`, `Todo`, `Accuracy`, `Win rate` and `Rendimiento` redraw the graph with real data.
- Confirm the 7-day and 30-day charts contain one point per day and overlapping dates keep the same value.
- Confirm the vertical axis adapts to each metric without exceeding its valid 0-100 or 0-1000 bounds.
- Confirm the four headline metrics render as a 2x2 mobile grid and a single desktop row.
- Confirm recent and recommended games render as mobile cards with large actions, while desktop retains the table layout.
- Confirm each mobile game card shows whether the player used White or Black.
- Confirm long opponent names are truncated cleanly and `Ver todas las partidas` has no partial underline.
- Confirm Nova's header core is grey before completing today's goal, animates once on activation and then uses the active loop.
- Confirm Player DNA and Top 3 focuses are hidden only on mobile.
- Confirm the latest-game review block and duplicate quick action no longer appear.
- Run `php tests/dashboard_progress_test.php` and confirm the Home history periods and series pass.

## Mobile Review Checks

- Check Review at 360 px and 390 px without horizontal overflow or a distorted board.
- Confirm the first view prioritizes game context, current move, board, previous/next controls and the move explanation.
- Confirm rank and file coordinates are drawn inside the edge squares and do not reduce board width.
- Confirm the board reaches both viewport edges without horizontal scrolling and no outer panel frame remains around the solving area.
- Confirm the move number and side appear in the same row as SAN and quality.
- Confirm the review check beside the players is grey while pending and green once the required plies have been visited.
- Confirm board orientation and coordinates remain correct when the player used White or Black and after using the mobile flip control.
- Confirm `Abrir análisis` reveals a bottom sheet above the board with `Resumen`, `Análisis`, `Jugadas` and `Coach` tabs.
- Confirm the sheet scrolls internally, closes from its handle/backdrop and changing tabs preserves the selected move and board position.
- Confirm `Mostrar mejor jugada` and `Volver a la jugada` retain the existing UCI/SAN behavior.
- Confirm Nova appears in the mobile Coach view and the desktop Review layout remains unchanged.
- Run `php tests/review_mobile_layout_test.php`.

## Mobile Training Checks

- Check Training at 360 px and 390 px without horizontal overflow.
- Confirm the page follows the supplied mobile composition: compact heading, focus selector, Nova recommendation, categories, continuation cards and coach advice.
- Confirm Nova's primary action opens a real pending exercise.
- Confirm `Escenarios` is visible but inactive until multi-move training is implemented.
- Confirm Flash and Finales filter the current one-move exercise inventory.
- Confirm Aperturas opens `openings-lab.php` and no user-facing text mentions sessions.
- Confirm continuation cards use real exercises and open `training-exercise.php`.
- Confirm the desktop layout remains readable as an expanded adaptation of the mobile composition.
- Run `php tests/training_mobile_layout_test.php`.

## Mobile Training Exercise Checks

- Check `training-exercise.php` at 360 px and 390 px in initial, incorrect, hint, explanation, exhausted and solved states.
- Confirm the board remains the primary element, its coordinates stay inside the squares and the configured piece/color preferences still apply.
- Confirm the segmented header represents the configured daily objective rather than an artificial session.
- Confirm Nova's messages can be swiped and selected by their dots.
- Confirm `Explícame`, each progressive hint and every attempt append a new active slide without removing earlier messages.
- Confirm the slide counter and dots update as the carousel grows.
- Confirm the source-game action opens the corresponding Review page.
- Confirm automatic validation hides only the redundant `Comprobar` action.
- Confirm desktop retains the established two-column solver workspace.
- Run `php tests/training_exercise_mobile_layout_test.php`.

---

## Stockfish Analysis Pipeline Checks

Run the dependency-free UCI protocol fixtures:

```powershell
php tests\stockfish_protocol_test.php
```

For v1.4.16, after applying `sql/migrations/032_changes_1.4.16.sql`, confirm:

- A new analysis stores the detected Stockfish version and optional configured build label.
- `engine_search_mode`, `engine_search_value`, Threads and Hash match `config/engine.php`.
- Completed plies have score type, depth, nodes, time, PV and best-move telemetry.
- A timeout or killed process records an engine error and does not insert partial move analysis.
- A transient evaluation failure is retried with a fresh engine process.
- A stale running job is requeued or failed according to `analysis_max_attempts`.
- Simultaneous cron/manual requests do not run overlapping Stockfish processes when serialization is enabled.
- Existing historical analyses remain readable with NULL engine metadata until explicitly reanalyzed.

---

## Training SAN Notation Checks

Run the dependency-free notation fixtures:

```powershell
php tests\chess_notation_test.php
```

Expected result:

```text
OK: 12 casos de notacion SAN.
```

Manual checks:

- Confirm submitted attempts are displayed as SAN while the board and API validation retain UCI.
- Confirm captures, check, mate, castling, promotion and en passant render correctly.
- Confirm disambiguated moves include the required origin file or rank.
- Confirm the exhausted-attempt solution and `Movimiento correcto` use SAN.
- Confirm an accepted alternative solution displays the alternative that actually solved the exercise.
- Confirm unresolved exercise responses do not expose `solution_uci`, `solution_san` or `solution_display`.
- Confirm conversion failures use coordinate notation such as `b1 → c3` without breaking the solver.
- Confirm review comments and the `Mejor` action show Stockfish alternatives in SAN from `fen_before`.

---

## Training Progress Foundation Checks

Run the dependency-free scoring fixtures:

```powershell
php tests\training_progress_test.php
php tests\player_progress_test.php
php tests\training_hints_test.php
php tests\training_plan_test.php
php tests\player_perspective_test.php
```

Expected result:

```text
OK: 20 casos de progreso de entrenamiento.
OK: formulas de Progress Score, partidas y autonomia.
OK: pistas progresivas contextuales sin filtrar la solución.
OK: seguimiento de reviews y periodos del plan de entrenamiento.
OK: perspectiva del jugador en movimientos blancos y negros.
```

For v1.4.15, after applying `sql/migrations/031_changes_1.4.15.sql`, confirm:

- Review Summary B/E/I, ACPL and accuracy only use the authenticated player's plies.
- The review move list, board and graph still include both players.
- Stored analysis counters match the player's side for historical completed analyses.
- A weekly opening objective targets two related exercises and progresses after each completed solve run.
- The Training `Entrenar` button is blue and comfortably tappable on mobile.
- The Home `Partidas` sparkline matches cumulative imported-game totals for the last 10 days.
- The Home pending-analysis card shows the current queue count and daily completed analyses as its sparkline.

After applying `sql/migrations/030_changes_1.4.12.sql`, confirm:

- The solve-run, progressive-hint, progress-event, snapshot, plan-goal and review-progress tables exist.
- `training_attempts.solve_run_id` exists and accepts `NULL` for historical attempts.
- Re-running the migration does not duplicate the `app_migrations` entry.
- Skipped and abandoned runs do not create scoring evidence.
- Progressive hints expose the idea, origin piece and board region without returning the destination square or solution UCI.
- Confirm the first hint shows an idea without changing or highlighting a board square.
- Confirm the second hint identifies and selects the origin piece, including its legal-move markers.
- Confirm the third hint adds the action and board region while preserving the origin highlight.
- Reload an unfinished exercise and confirm its requested hints are restored in the same order.
- Confirm the hint button advances from `Pista 1/3` to `Pistas completadas` and remains usable on mobile.
- Navigate through 17 different plies in `review.php` and confirm `game_review_progress.completed_at` is populated.
- Confirm a deep link to a late ply does not complete a review without visiting the required number of distinct plies.
- Confirm a game shorter than 17 plies is completed after every ply has been viewed.
- POST to `api/training-plan.php` with a valid CSRF token and confirm it returns measurable `daily` and `weekly` goals.
- Confirm plan progress counts solved or failed exercises, but not skipped exercises.
- Change the configured daily goal and confirm obsolete pending plan goals are dismissed.
- Complete a daily review goal and confirm it remains visible as completed instead of reducing the daily goal count.
- Complete the weekly review target and confirm it remains visible as completed for the rest of the week.
- Confirm a goal hidden by the v1.4.12-v1.4.13 refresh-order bug reappears as completed on the next plan refresh.
- Confirm Home and Training show separate daily and weekly plan columns with working action links.
- Confirm the `Índice de rendimiento` and `Autonomía` values agree across Home, Training and Profile.
- Confirm the header shows streak and performance index compactly on desktop and mobile.
- Solve or fail a new exercise and confirm plan progress refreshes without a full page reload.
- Confirm attempts made before migration 030 are not presented as fully reconstructed solve runs.

---

## ECO Catalog Checks

After applying `sql/migrations/025_changes_1.4.3.sql`, verify the catalog:

```sql
SELECT COUNT(*) AS total_codes FROM eco_codes;

SELECT LEFT(eco_code, 1) AS volume, COUNT(*) AS total
FROM eco_codes
GROUP BY LEFT(eco_code, 1)
ORDER BY volume;

SELECT COUNT(*) AS invalid_codes
FROM eco_codes
WHERE eco_code NOT REGEXP '^[A-E][0-9]{2}$';

SELECT COUNT(*) AS incomplete_labels
FROM eco_codes ec
JOIN opening_families f ON f.family_key = ec.family_key
WHERE ec.opening_name = '' OR f.family_name = '';

SELECT eco_code, family_name, opening_name, variation_name
FROM eco_codes ec
JOIN opening_families f ON f.family_key = ec.family_key
WHERE eco_code IN ('B90', 'C65')
ORDER BY eco_code;
```

Expected results:

- `total_codes`: `500`.
- Each volume `A`, `B`, `C`, `D` and `E`: `100`.
- `invalid_codes`: `0`.
- `incomplete_labels`: `0`.
- `B90`: `Defensa Siciliana` / `Variante Najdorf`.
- `C65`: `Apertura Española` / `Defensa Berlinesa`.

After deploying the PR2 backend resolver, verify its precedence and API payloads:

- A game with an `Opening` PGN tag keeps that imported name.
- A game with ECO `B90` and no opening name resolves to `Defensa Siciliana`.
- The same game exposes `opening_variation_name` as `Variante Najdorf` in `api/games.php?action=list`.
- Openings Lab exposes `family_name`, `opening_name`, `variation_name` and `label_source` for catalog matches.
- Temporarily missing catalog tables do not prevent the games or Openings Lab endpoints from returning a response.
- Openings Lab renders `C65 · Apertura Española` with `Defensa Berlinesa` below it.
- Openings Lab renders `B90 · Defensa Siciliana` with `Variante Najdorf` below it.
- Long names wrap without overlapping KPIs on desktop and mobile.
- ECO links still open only safe HTTP(S) `ECOUrl` values in a new tab.

---

## Board Personalization Checks

After applying `sql/migrations/026_changes_1.4.4.sql`:

- Confirm `users.board_theme` defaults to `green`.
- Confirm profile settings list `Set 1`, `Set 2` and `Set 3`.
- Confirm the available themes are `Verde`, `Marrón`, `Azul` and `Gris`.
- Save every set/theme combination and reload the page.
- Confirm review and training boards preserve the selected combination.
- Confirm move, best-move, selection, legal-target, hint and solution highlights remain distinguishable.
- Confirm all 36 piece-set assets declared in `service-worker.js` exist.

---

## Mobile Summary Card Checks

At a viewport of 640 px or narrower:

- Confirm the four summary cards in `analysis-pending.php`, `training.php` and `openings-lab.php` render as compact rows.
- Confirm each icon appears above its title in the left column.
- Confirm each primary value appears above its supporting detail in the right column.
- Confirm labels and supporting text do not overlap or clip with large values.
- Confirm the home dashboard metric cards are unchanged.
- Confirm the three pages retain their four-column summary layout on desktop.

---

## Training Exercise Mobile Metadata Checks

At a viewport of 560 px or narrower:

- Confirm Intentos, Dificultad and Prioridad render in three equal columns.
- Confirm the labels, values and difficulty bars are centered and do not overlap.
- Confirm the source game remains below the metadata row.
- Confirm the desktop training solver sidebar is unchanged.

---

## Training Interaction Preference Checks

After applying `sql/migrations/029_changes_1.4.8.sql`:

- Confirm the `engine_*` columns exist in `training_exercises`.
- Confirm new exercises remain at `content_version = 2`.
- Run `Enriquecer ejercicios con Stockfish` and confirm no more than 50 unresolved exercises become version 3 through requests of at most 10 exercises each.
- Confirm `engine_pv_uci`, `engine_depth` and `engine_refreshed_at` are populated.
- For mismatches, confirm `solution_uci` is unchanged and `engine_solution_mismatch = 1`.
- Confirm mismatches also populate `engine_original_score`, `engine_original_score_type` and `engine_original_depth`.
- Confirm version 3 mismatches created before alternative validation reappear once and leave the pending set after validation.
- Confirm an equivalent mismatch populates `accepted_alternative_uci` and that both moves solve the exercise.
- Confirm a mismatch outside the accepted tolerance leaves `accepted_alternative_uci` empty and the refreshed bestmove is rejected by the solver.
- Confirm unresolved exercise API responses do not expose `accepted_alternative_uci` or either engine evaluation.
- Confirm resolved exercises are not modified by the enrichment process.

After applying `sql/migrations/028_changes_1.4.7.sql`:

- Confirm `training_exercises.title` exists.
- Confirm `training_exercises.content_version` exists and defaults to `1` for existing rows.
- Run `Actualizar contenido de ejercicios` from `profile.php` and confirm the pending count decreases.
- Confirm attempts, `resolved_at`, `next_due_at` and `last_training_result` are unchanged for updated exercises.
- Confirm updated rows have `content_version = 2`.

After applying `sql/migrations/027_changes_1.4.6.sql`:

- Confirm existing users default to legal-move hints enabled and automatic submission disabled.
- Save each preference combination in `profile.php` and reload the page.
- Confirm selecting an empty square or an opponent piece does not set the origin.
- Confirm selecting another own piece changes the origin selection.
- Confirm an illegal destination is rejected and does not consume an attempt.
- Confirm legal destinations remain enforced when visual hints are disabled.
- Confirm automatic submission registers exactly one attempt after a legal destination is selected.
- Confirm manual mode keeps the Comprobar button enabled after a complete legal move.
- Confirm solving an exercise leaves the piece on its destination and highlights both move squares in green.
- Confirm the solved destination displays a green check above the piece at mobile and desktop sizes.

---

## Tag And Release Checks

Create tags only after the version PR has been approved by the user and merged into `main`.

Use the version tag format:

```text
vX.Y.Z
```

Example for `v0.8.7`:

```powershell
git switch main
git pull --ff-only origin main
git tag -a v0.8.7 -m "Chess Coach v0.8.7"
git push origin v0.8.7
gh release create v0.8.7 --title "Chess Coach v0.8.7" --notes-file README_UPDATE.md
```

Confirm the tag points at the merged `main` commit:

```powershell
git log -1 --oneline "v0.8.7^{}"
```

Confirm the GitHub Release exists:

```powershell
gh release view v0.8.7
```

---

## Credential Check

Real environment files must not be committed:

```text
config/database.php
config/engine.php
config/cron.php
```

Only example files should be tracked:

```text
config/database.example.php
config/engine.example.php
config/cron.example.php
```

---

## SQL Migration Check

Add a SQL migration only when the database schema or stored baseline data changes.

If no database changes are needed, state this clearly in:

```text
README_UPDATE.md
CHANGELOG.md
```

---

## Manual Smoke Checklist

When a deployment is available, verify:

- Login page loads.
- App dashboard loads.
- Import page loads.
- Chess.com import page loads.
- Analysis queue page loads.
- Worker endpoint returns valid JSON when called with the configured token.
- Review page loads for an analyzed game.
- No stale PWA assets appear after hard refresh or PWA reinstall.

## Chess Logic Checks

```powershell
php tests\chess_evaluation_test.php
php tests\player_windows_test.php
```

## Training v2 Checks

```powershell
php tests\coach_foundation_test.php
php tests\training_scenarios_test.php
php tests\training_scenario_ui_test.php
php tests\training_mobile_layout_test.php
php tests\training_exercise_mobile_layout_test.php
```

Confirm Nova's prepared plan can open a Flash followed by a Scenario, that Scenario retries do not advance the board, and that completing or skipping an item continues with the next pending plan item.

Confirm an exact Stockfish best move is shown as `Mejor`, contributes effective CPL 0 and does not display a redundant alternative. After completing an analysis, confirm Player DNA refreshes automatically and labels dimensions with insufficient observations as `Muestra limitada`.
