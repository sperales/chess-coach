# Chess Coach v1.4.12 Update Notes

## Release type

Personal training plan and performance tracking release.

## Changes

- Records each exercise solve run with difficulty, attempts, progressive hints and final quality.
- Replaces the single direct hint with three progressive levels that preserve the reasoning process.
- Adds an `Índice de rendimiento` from recent exercise and analyzed-game evidence; the value can rise or fall with performance.
- Adds an `Autonomía` metric focused on solving without help and calibrated after six useful exercise samples.
- Generates measurable daily and weekly objectives from configured goals, reviews, current Smart Tag focus and opening work.
- Counts a game as reviewed after 17 distinct plies have been visited, or every ply for shorter games.
- Shows the personal plan in Home and Training with direct links to the next useful action.
- Shows the performance index and autonomy in Home, Training and Profile, plus compact streak/index feedback in the header.
- Shows review progress in `review.php` without changing move navigation or analysis data.
- Keeps sessions as an internal implementation detail rather than a user-facing training concept.
- Bumps `config/version.php` and the PWA cache to `1.4.12`.

## SQL migration

Run before deploying the PHP and JavaScript changes:

```text
sql/migrations/030_changes_1.4.12.sql
```

The migration creates solve-run, progressive-hint, progress-event, snapshot, training-plan and review-progress storage. It also links new training attempts to their solve run.

Historical attempts are intentionally not backfilled: old records do not contain enough reliable information to reconstruct hint usage or autonomy. Newly completed exercises are counted from deployment onward, while recent analyzed games can be synchronized into the performance index.

## Verification

- Run `php tests/training_progress_test.php`.
- Run `php tests/player_progress_test.php`.
- Run `php tests/training_hints_test.php`.
- Run `php tests/training_plan_test.php`.
- Request all three hints and confirm none exposes the solution destination.
- Confirm hint level two selects the correct origin piece on the board.
- Solve and fail exercises and confirm daily/weekly plan progress updates while skipped exercises do not count.
- Confirm the `Índice de rendimiento` and `Autonomía` appear consistently in Home, Training and Profile.
- Confirm the header shows the streak and performance index without the word `racha` on desktop.
- Visit 17 distinct plies in a review and confirm its progress changes to completed.
- Confirm a deep link to a late ply does not complete a review by itself.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.12`.

---

# Chess Coach v1.4.11 Update Notes

## Release type

Training exercise SAN notation release.

## Changes

- Adds a centralized PHP helper that converts legal UCI moves to SAN from the position immediately before the move.
- Keeps UCI as the internal format for Stockfish, board interaction, accepted alternatives and exercise validation.
- Shows SAN in the current attempt list, attempt history, revealed solution and correct-move summary.
- Supports captures, legal disambiguation, check, mate, castling, promotion and en passant.
- Falls back to readable coordinate notation such as `b1 → c3` when SAN conversion cannot be completed safely.
- Avoids exposing either UCI or SAN solutions before the existing exercise resolution rules allow it.
- Adds a dependency-free PHP notation test suite.
- Bumps `config/version.php` and the PWA cache to `1.4.11`.

## SQL migration

No SQL migration or exercise backfill is required. SAN is derived only in the presentation layer.

## Verification

- Run `php tests/chess_notation_test.php` and confirm all notation fixtures pass.
- Submit normal moves, captures and special moves in training exercises and confirm attempt history uses SAN.
- Solve an exercise through its original move and through an accepted alternative, when available.
- Exhaust all five attempts and confirm the revealed solution and `Movimiento correcto` use SAN.
- Confirm an invalid or inconsistent historical position falls back to coordinate notation without breaking the page.
- Confirm unresolved exercise payloads still hide all representations of the solution.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.11`.

---

# Chess Coach v1.4.10 Update Notes

## Release type

Mobile-first Training Center solver release.

## Changes

- Prioritizes the board, instructions, attempt feedback and solving actions on mobile.
- Expands the responsive board while retaining coordinates, orientation and selected piece set.
- Places progress, difficulty, priority, Smart Tags and source-game information after the active solving area.
- Uses a compact exercise hero on mobile and preserves the current desktop layout.
- Shows `Comprobar` as a full-width action when manual submission is configured.
- Hides `Comprobar` on mobile when the profile preference automatically submits a legal move after its destination is selected.
- Keeps `Pista` and `Saltar` available as two balanced touch actions.
- Adds a two-column mobile landscape layout for the board and solving controls.
- Keeps the green solved-move check marker circular and centered across mobile browsers.
- Bumps `config/version.php` and the PWA cache to `1.4.10`.

## SQL migration

No SQL migration is required.

## Verification

- Open an unresolved exercise on a 320-430 px wide viewport and confirm the board remains square with visible coordinates.
- Confirm the objective, side to move, status, timer, draft, feedback and controls follow directly after the board.
- With automatic submission enabled, confirm `Comprobar` is hidden on mobile and selecting a legal destination submits the move.
- With automatic submission disabled, confirm `Comprobar` spans the full control width and remains disabled until a complete legal move is selected.
- Confirm `Pista` and `Saltar` remain available in both preference modes.
- Confirm finished exercises replace the active controls with `Siguiente`, `Cerrar` and `Ver partida`.
- Confirm progress, metadata and source-game information remain available below the solving area.
- Confirm mobile landscape uses side-by-side board and controls without horizontal overflow.
- Confirm the desktop page retains the existing two-column workspace and visible `Comprobar` button.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.10`.

---

# Chess Coach v1.4.9 Update Notes

## Release type

Training solver visual feedback fix.

## Changes

- Keeps the latest incorrect destination marked in red after the fifth failed attempt.
- Shows the revealed solution origin and destination in blue instead of red.
- Prioritizes the blue solution highlight if a square belongs to both the failed attempt and the revealed solution.
- Bumps `config/version.php` and the PWA cache to `1.4.9`.

## SQL migration

No SQL migration is required.

## Verification

- Exhaust all five attempts with an incorrect final move.
- Confirm the final incorrect destination remains red.
- Confirm the revealed solution squares are blue and visually distinct from the failed attempt.
- Confirm a square shared by both states uses the blue solution highlight.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.9`.

---

# Chess Coach v1.4.8 Update Notes

## Release type

Training feedback and Stockfish enrichment release.

## Changes

- Keeps the destination square of the latest incorrect attempt marked in red after returning the piece.
- Clears the incorrect marker after solving the exercise or opening another exercise.
- Keeps newly generated exercises at `content_version = 2`.
- Adds an optional Stockfish process for unresolved version 2 exercises, processing up to 50 per click through HTTP sub-batches of 10.
- Stores refreshed bestmove, principal variation, evaluation, score type, depth and timestamp.
- Marks enriched exercises as `content_version = 3` only after receiving a valid bestmove and PV.
- Preserves the original `solution_uci` when the refreshed bestmove differs and records the mismatch.
- Re-evaluates the original solution with Stockfish `searchmoves` when a bestmove mismatch is found.
- Accepts the refreshed bestmove as a second valid solution only when it is equivalent within 30 centipawns or a conservative mate-distance tolerance.
- Stores the original constrained evaluation so accepted and rejected alternatives remain auditable.
- Shows the first safe per-exercise errors when an enrichment batch completes partially.
- Improves deterministic mate descriptions only when the refreshed solution agrees with the accepted solution.
- Hides refreshed engine solutions and PVs while the exercise is unresolved.
- Bumps `config/version.php` and the PWA cache to `1.4.8`.

## SQL migration

Run before deploying the PHP changes:

```text
sql/migrations/029_changes_1.4.8.sql
```

## Stockfish enrichment

1. Open `Ajustes / Mi perfil`.
2. Find `Enriquecer ejercicios con Stockfish` under `Procesos batch`.
3. Run the process when desired; each click analyzes up to 50 unresolved exercises through five sequential requests of at most 10.
4. Repeat later until `Pendientes` reaches zero.

The process uses the current `config/engine.php` settings. It never processes resolved exercises and never overwrites the original accepted solution. When Stockfish returns a different bestmove, it performs one additional constrained evaluation and only stores that bestmove as an accepted alternative when both moves are objectively equivalent.

If migration `029_changes_1.4.8.sql` was already run before this validation was added, run it again. Its `ADD COLUMN IF NOT EXISTS` statements safely add the new alternative-solution fields.
Previously enriched version 3 mismatches without a constrained original evaluation are automatically included once in the pending count, so early test batches are not left behind.

## Verification

- Make an incorrect legal move and confirm the piece returns while its destination remains red.
- Confirm a later incorrect move replaces the previous red destination marker.
- Solve the exercise and confirm the red marker disappears in favor of the green solved state.
- Run one Stockfish enrichment batch and confirm no more than 50 exercises move to content version 3.
- Confirm the browser splits that operation into requests of at most 10 exercises and reports an intelligible message if the hosting returns HTML instead of JSON.
- Confirm resolved exercises are not selected.
- Confirm mismatched bestmoves leave `solution_uci` unchanged and set `engine_solution_mismatch = 1`.
- Confirm equivalent mismatches populate `accepted_alternative_uci` and materially inferior alternatives leave it empty.
- Confirm either accepted move solves the exercise, while rejected bestmoves do not.
- Confirm unresolved exercise API responses do not expose engine bestmove or PV fields.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.8`.

---

# Chess Coach v1.4.7 Update Notes

## Release type

Training exercise content and controlled backfill release.

## Changes

- Uses move-level Smart Tags as the primary evidence for exercise classification.
- Applies game-level tags only to the plies that generated them.
- Separates concise exercise titles from contextual instructions.
- Improves deterministic success and failure feedback without external AI.
- Distinguishes finding a mate from avoiding an adverse mate evaluation.
- Recalculates exercise priority with position-relevant evidence.
- Adds a resumable content version and a profile process that updates 200 exercises at a time.
- Preserves exercise IDs, attempts, resolved state, repetition dates and training history.
- Bumps `config/version.php` and the PWA cache to `1.4.7`.

## SQL migration

Run before deploying the PHP changes:

```text
sql/migrations/028_changes_1.4.7.sql
```

The migration adds `title`, `content_version` and an index for controlled content updates.

## Existing exercise update

1. Open `Ajustes / Mi perfil`.
2. Find `Actualizar contenido de ejercicios` under `Procesos batch`.
3. Run the process repeatedly until `Pendientes` reaches zero.
4. Each execution updates at most 200 exercises and can safely be resumed later.

The process never deletes exercises and does not run Stockfish.

## Verification

- Confirm new exercises use contextual titles and non-duplicated instructions.
- Confirm only Smart Tags relevant to the exercise ply are displayed.
- Confirm mate exercises distinguish attacking and defensive situations.
- Run one content batch and verify pending decreases by at most 200.
- Confirm attempts, resolved exercises and repetition dates remain unchanged.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.7`.

---

# Chess Coach v1.4.6 Update Notes

## Release type

Training mobile UI polish release.

## Changes

- Kept exercise attempts, difficulty and priority in a compact three-column row on mobile.
- Centered the three exercise indicators for faster scanning below the board.
- Added profile settings for legal-move hints and automatic move submission.
- Enforced side-to-move selection and legal destination selection in training exercises.
- Removed the non-interactive legal-move checkbox from the exercise page.
- Kept the solved position visible with the correct move highlighted in green.
- Reinforced solved exercises with a green check over the destination square.
- Preserved the existing desktop training layout.
- Bumped `config/version.php` and the PWA cache to `1.4.6`.

## SQL migration

Run:

```text
sql/migrations/027_changes_1.4.6.sql
```

The migration adds the training interaction preferences with backward-compatible defaults.

## Verification

- Open `training-exercise.php` at a viewport of 560 px or narrower.
- Confirm Intentos, Dificultad and Prioridad remain on one row without overlap.
- Confirm all difficulty bars remain visible and centered.
- Save both training preferences from `profile.php` and confirm they persist after reloading.
- Confirm an exercise only accepts a piece belonging to the side to move.
- Confirm an illegal destination cannot be selected or submitted.
- Confirm legal target markers follow the saved preference.
- Confirm automatic submission follows the saved preference without duplicate attempts.
- Confirm a correct move remains played on the board with both squares highlighted in green.
- Confirm the destination square displays a legible green check without obscuring the piece.
- Confirm the desktop sidebar layout remains unchanged.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.6`.

---

# Chess Coach v1.4.5 Update Notes

## Release type

Mobile UI polish release.

## Changes

- Compacted the four summary cards on mobile in Analysis Pending, Training Center and Openings Lab.
- Reorganized each card into two balanced columns: icon above title on the left, value above detail on the right.
- Preserved the four-column desktop grid and the dashboard cards outside these three pages.
- Bumped `config/version.php` and the PWA cache to `1.4.5`.

## SQL migration

No SQL migration is required.

## Verification

- Check the four summary cards at mobile and desktop widths in `analysis-pending.php`, `training.php` and `openings-lab.php`.
- Confirm each card shows the icon above its title and the value above its supporting detail.
- Confirm labels and details remain readable with large values and long opening names.
- Confirm the four-card desktop layout remains unchanged.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.5`.

---

# Chess Coach v1.4.4 Update Notes

## Release type

Board personalization release.

## Changes

- Added `Set 3` with 12 local transparent PNG chess pieces.
- Expanded the profile board settings with six-piece previews for every available set.
- Added four board color themes: `Verde`, `Marrón`, `Azul` and `Gris`.
- Applied the selected theme to review, Training Center previews and the dedicated exercise solver.
- Stored the preference per user using a constrained server-side allowlist.
- Kept `Verde` as the default for existing users and invalid/missing values.
- Bumped `config/version.php` and the PWA cache to `1.4.4`.

## SQL migration

Run:

```text
sql/migrations/026_changes_1.4.4.sql
```

The migration adds `users.board_theme` and defaults existing users to `green`.

## Verification

- Confirm `Set 3` appears in `profile.php` and shows all six white piece types.
- Save each piece set and verify review and training use it.
- Save each board color and verify review, training previews and the exercise solver use it.
- Confirm highlights for previous move, selection, hints and solutions remain visible in every theme.
- Confirm the profile selector stacks cleanly on mobile.
- Confirm `config/version.php` and `service-worker.js` both use `1.4.4`.

---

# Chess Coach v1.4.3 Update Notes

## Release type

Openings Lab catalog release.

## PR1 - Autonomous ECO catalog

- Added `opening_families` as the stable grouping layer for future family statistics.
- Added `eco_codes` with every code from `A00` to `E99`.
- Added canonical Spanish opening names and representative variation names.
- Kept the catalog independent from runtime APIs and external services.
- Documented the pinned CC0 source revision and the distinction between an ECO category and an exact opening line.
- Updated `sql/install.sql` so new installations receive the complete catalog.

This PR contains database and reference data only. It does not change Openings Lab backend responses or UI.

## PR2 - Backend ECO resolver

- Added a central ECO resolver in `includes/eco_catalog.php`.
- Kept imported PGN opening names as the highest-priority source.
- Added catalog fallback for games without an imported opening name.
- Enriched Openings Lab data with Spanish opening, variation and family labels.
- Enriched the games API with the same normalized opening metadata.
- Kept existing opening keys and stored profiles unchanged to avoid fragmenting historical statistics.
- Made catalog loading fail gracefully if code is uploaded before the SQL migration is applied.

This PR changes backend payloads only. The new hierarchy is intentionally not rendered in Openings Lab until the dedicated UI PR.

## PR3 - Friendly Openings Lab labels

- Replaced code-only opening identities with `ECO · opening name` labels.
- Added the representative variation on a separate secondary line when available.
- Added the family as quiet context only when it differs from the opening name.
- Applied the same compact naming to the best-opening and attention metrics.
- Preserved safe external `ECOUrl` links in list and detail views.
- Added responsive wrapping for long Spanish opening and variation names.

This PR does not change opening grouping, metrics or recommendation logic.

## PR4 - Release readiness

- Bumped `config/version.php` to `1.4.3`.
- Updated the service worker cache to `chess-coach-v1.4.3`.
- Updated README, roadmap, changelog, verification and agent baseline documentation.
- Completed release checks for PHP, JavaScript, catalog integrity and PWA asset references.

## SQL migration

Run:

```text
sql/migrations/025_changes_1.4.3.sql
```

Expected result:

- 500 rows in `eco_codes`.
- 100 codes for each prefix from `A` to `E`.
- No existing games or opening profiles are modified.

## Verification

- Run the ECO catalog checks documented in `VERIFY.md`.
- Confirm `B90` resolves to `Defensa Siciliana` / `Variante Najdorf` in the database.
- Confirm `C65` resolves to `Apertura Española` / `Defensa Berlinesa` in the database.
- Confirm applying the migration twice does not create duplicate rows.
- Confirm `config/version.php` contains `1.4.3`.
- Confirm `service-worker.js` uses `chess-coach-v1.4.3`.
- Reload the PWA after deployment so the new service worker cache becomes active.

---

# Chess Coach v1.4.2 Update Notes

## Release type

Small Training Experience UI polish release.

## Changes

- Made the home milestone summary expandable to show every available milestone.
- Added clear `Conseguido` and `Pendiente` states to the milestone detail list.
- Reused the Openings Lab toggle arrow and aligned it vertically in the milestone control.
- Fixed vertical text alignment inside the highlighted training exercise metadata chips.
- Bumped `config/version.php` to `1.4.2`.
- Updated the service worker cache to `chess-coach-v1.4.2`.

## SQL migration

No SQL migration required.

## Verification

- Confirm clicking the `Hitos` summary expands and collapses the complete milestone list.
- Confirm achieved and pending milestones are visually distinct.
- Confirm the milestone panel remains readable on mobile.
- Confirm metadata text is vertically centered in the highlighted exercise chips.
- Confirm `config/version.php` contains `1.4.2`.
- Confirm `service-worker.js` uses `chess-coach-v1.4.2`.

---

# Chess Coach v1.4.1 Update Notes

## Release type

Small Training Experience UI polish release.

## Changes

- Changed the header streak indicator to remain gray until the user trains that day.
- Changed the header streak indicator to green as soon as valid daily training activity is recorded.
- Removed the redundant `racha` label from the compact header indicator.
- Added a `Fallados` filter to the Training Center exercise list.
- Highlighted the first listed exercise with a position preview and visible difficulty.
- Added a dedicated difficulty column to the remaining exercise rows.
- Bumped `config/version.php` to `1.4.1`.
- Updated the service worker cache to `chess-coach-v1.4.1`.

## SQL migration

No SQL migration required.

## Verification

- Confirm the header streak indicator is gray before training that day.
- Complete or fail one exercise and confirm the indicator turns green.
- Confirm skipping an exercise does not turn the indicator green.
- Confirm the compact indicator only shows the icon and streak day count.
- Confirm the `Fallados` filter lists exercises whose latest training result is failed.
- Confirm the first exercise includes a correctly oriented board preview and difficulty.
- Confirm the remaining exercises show a dedicated difficulty column on desktop and stack cleanly on mobile.
- Confirm `config/version.php` contains `1.4.1`.
- Confirm `service-worker.js` uses `chess-coach-v1.4.1`.

---

# Chess Coach v1.4.0 Update Notes

## Release type

Training Experience release.

## Changes

- Added configurable training goals from profile for daily exercises, daily minutes and weekly targets.
- Replaced visible manual session management with automatic training progress focused on today, the week, streaks and goals.
- Added smart repetition state so failed, skipped and older solved exercises can return at controlled intervals.
- Added clearer exercise feedback explaining whether the attempt counts for today's goal and when the exercise may return.
- Added a compact Training Experience block to the home dashboard after `Resumen de rendimiento`.
- Added a compact streak indicator to the header.
- Added lightweight professional milestones based on consistency and training progress.
- Bumped `config/version.php` to `1.4.0`.
- Updated the service worker cache to `chess-coach-v1.4.0`.

## SQL migration

Run the v1.4.0 migration before using the Training Experience features:

```text
sql/migrations/024_changes_1.4.0.sql
```

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for `assets/js/dashboard.js` and `assets/js/training.js`.
- Confirm `profile.php` saves the training objective.
- Confirm `training.php` and `training-exercise.php` show progress without manual session controls.
- Confirm skipped exercises do not count as daily training activity.
- Confirm failed exercises become eligible for repetition later.
- Confirm the home dashboard shows the Training Experience block after `Resumen de rendimiento`.
- Confirm the header shows the compact streak indicator.
- Confirm `config/version.php` contains `1.4.0`.
- Confirm `service-worker.js` uses `chess-coach-v1.4.0`.

---

# Chess Coach v1.3.1 Update Notes

## Release type

Small UI polish release.

## Changes

- Removed the disabled `Etiquetas` option from the hamburger menu.
- Changed the Openings Lab `Vista` panel so its filters are collapsed by default.
- Added a toggle button to expand or collapse the Openings Lab `Vista` filters.
- Added spacing between the Openings Lab `Vista` panel and the lower `Aperturas detectadas` / `Detalle` layout.
- Bumped `config/version.php` to `1.3.1`.
- Updated the service worker cache to `chess-coach-v1.3.1`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for `assets/js/openings_lab.js`.
- Confirm the hamburger menu no longer shows `Etiquetas`.
- Confirm `openings-lab.php` shows the `Vista` panel collapsed by default.
- Confirm the `Mostrar filtros` / `Ocultar filtros` toggle expands and collapses the filters.
- Confirm there is visible spacing between `Vista` and the lower Openings Lab blocks.
- Confirm `config/version.php` contains `1.3.1`.
- Confirm `service-worker.js` uses `chess-coach-v1.3.1`.

---

# Chess Coach v1.3.0 Update Notes

## Release type

Player DNA release.

## Changes

- Added database support for Player DNA snapshots and recalculation runs.
- Added `includes/player_dna.php` with Player DNA scoring, strengths, weaknesses, style indicators, comparisons and recommendations.
- Added `api/player-dna.php` for loading the latest snapshot and triggering manual recomputation.
- Added the `ADN del jugador` page with profile summary, dimensions, strengths, weaknesses, style indicators, historical comparisons and next-step recommendation.
- Added a manual Player DNA recompute block to `profile.php`.
- Added `ADN del jugador` to the app menu.
- Added `assets/images/statistics_background.jpg` as the Player DNA hero background.
- Added a compact Player DNA summary block to the home dashboard after `Resumen de rendimiento`.
- Updated Player DNA dimension colors so scores 70+ use green, scores 40-69 use orange and scores below 40 use red.
- Bumped `config/version.php` to `1.3.0`.
- Updated the service worker cache to `chess-coach-v1.3.0`.

## SQL migration

Run the new migration before using Player DNA:

```text
sql/migrations/023_changes_1.3.0.sql
```

The migration adds:

- `player_dna_snapshots`
- `player_dna_runs`

## Manual steps

1. Upload the changed files.
2. Run `sql/migrations/023_changes_1.3.0.sql` if it has not already been applied.
3. Open `Ajustes / Perfil`.
4. Use the Player DNA recompute action to generate the first snapshot.
5. Open `ADN del jugador` from the menu.
6. Confirm the home dashboard shows the compact Player DNA summary after `Resumen de rendimiento`.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `config/version.php` contains `1.3.0`.
- Confirm `service-worker.js` uses `chess-coach-v1.3.0`.
- Confirm the PWA cache includes `player-dna.php`, `assets/js/player_dna.js` and `assets/images/statistics_background.jpg`.
- Confirm the Player DNA page shows an empty state before a snapshot exists.
- Confirm manual recompute from profile creates a snapshot.
- Confirm the Player DNA page shows dimensions, strengths, weaknesses, style indicators, comparisons and next-step recommendation.
- Confirm the home Player DNA block degrades safely if the snapshot API is unavailable.

---

# Chess Coach v1.2.9 Update Notes

## Release type

Openings Lab UI polish release.

## Changes

- Added `assets/images/lab_background.jpg` as the local background for the Openings Lab hero block.
- Refreshed `openings-lab.php` with the new Lab background image.
- Aligned `Ejercicios relacionados` action buttons to the right on desktop.
- Styled `Revisar`, `Revisar foco` and example-game review actions in green.
- Styled `Entrenar` actions in blue.
- Bumped `config/version.php` to `1.2.9`.
- Updated the service worker cache to `chess-coach-v1.2.9`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `openings-lab.php` shows the new hero background.
- Confirm related exercise buttons align to the right on desktop.
- Confirm Openings Lab review buttons are green and training buttons are blue.
- Confirm the PWA cache includes `assets/images/lab_background.jpg`.

---

# Chess Coach v1.2.8 Update Notes

## Release type

Training Center solver UI release.

## Changes

- Added `assets/images/training_background.jpg` as the local background for Training Center hero blocks.
- Refreshed the `training.php` hero card with the new training background.
- Added `training-exercise.php` as a dedicated page for solving exercises.
- Changed pending exercise actions so they navigate to the dedicated solver page instead of opening an inline solver block.
- Reused the existing Training Center API, sessions, attempts, hints, skips and board logic in the dedicated solver.
- Added exercise metadata areas for difficulty, priority, source game, Smart Tags, details and attempt history.
- Added board coordinates, a side-panel objective reminder and a review-style summary for the source game.
- Updated Openings Lab training links to use the dedicated solver page.
- Bumped `config/version.php` to `1.2.8`.
- Updated the service worker cache to `chess-coach-v1.2.8`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `training.php` shows the new training hero background.
- Confirm pending exercises open `training-exercise.php?id=...`.
- Confirm attempts, hints, skips, next exercise and review links still work from the dedicated solver.
- Confirm Openings Lab `Entrenar` links open the selected exercise in the dedicated solver.
- Confirm the PWA cache includes `training-exercise.php` and `assets/images/training_background.jpg`.

---

# Chess Coach v1.2.7 Update Notes

## Release type

Import and analysis queue UI polish release.

## Changes

- Added `assets/images/analysis_background.jpg` as the local background for the analysis queue hero block.
- Added `assets/images/import_background.jpg` as the local background for the import page hero block.
- Refreshed `analysis-pending.php` with the new analysis hero background.
- Refreshed `import-chesscom.php` with the new import hero background.
- Removed the `Volver` button from the import page.
- Reworked the import page layout with two import cards and a bottom help panel.
- Bumped `config/version.php` to `1.2.7`.
- Updated the service worker cache to `chess-coach-v1.2.7`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `analysis-pending.php` shows the new hero background.
- Confirm `import-chesscom.php` shows the new hero background and no `Volver` button.
- Confirm PGN import and Chess.com import controls still use their existing IDs/actions.
- Confirm the PWA cache includes both new background images.

---

# Chess Coach v1.2.6 Update Notes

## Release type

Home, review and games UI polish release.

## Changes

- Renamed the home summary label from `Decisión grave` to `Omisión grave`.
- Adjusted the review quick insight `Enfoque` card to show title, focus value and explanation on separate lines.
- Added `assets/images/games_background.jpg` as the local background for the games page hero block.
- Refreshed the `games.php` hero card with the new background image.
- Split games table actions into separate unlabeled columns for review and reanalysis.
- Styled `Revisar` as a green action in games lists and kept `Reanalizar` with the existing secondary button style.
- Added B/E/I counts to recommended games in the home games block.
- Bumped `config/version.php` to `1.2.6`.
- Updated the service worker cache to `chess-coach-v1.2.6`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm the home summary uses `Omisión grave`.
- Confirm the review `Enfoque` card uses three lines.
- Confirm `games.php` shows the new hero background and separated action columns.
- Confirm the PWA cache includes `assets/images/games_background.jpg`.

---

# Chess Coach v1.2.5 Update Notes

## Release type

Review UI refresh release.

## Changes

- Added `assets/images/review_background.jpg` as the local background for the review hero block.
- Refreshed the review hero card with the new background image.
- Updated the evaluation chart to use a white evaluation line and a semi-transparent filled area.
- Added a bottom review section with a coach tip and quick insights based on the current analysis summary.
- Bumped `config/version.php` to `1.2.5`.
- Updated the service worker cache to `chess-coach-v1.2.5`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `review.php` shows the new background in the initial review block.
- Confirm the evaluation chart line is white and the filled area is slightly transparent.
- Confirm the bottom coach tip and quick insights appear after the existing review blocks.

---

# Chess Coach v1.2.4 Update Notes

## Release type

Review and home UI polish release.

## Changes

- Changed the `Excelente` review icon from an emoji to a green arrow-style symbol.
- Filtered move Smart Tags in `review.php` so tags identical to the move evaluation are not repeated.
- Reused the same review summary icons in the home latest-review summary block.
- Kept detected-pattern rows on one line in the mobile home layout.
- Improved the quote mark styling in the home motivational quote block.
- Bumped `config/version.php` to `1.2.4`.
- Updated the service worker cache to `chess-coach-v1.2.4`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `Excelente` appears green in `review.php`.
- Confirm duplicate move Smart Tags no longer appear next to identical move evaluations.
- Confirm the home latest-review summary uses the same icons as review.
- Confirm detected-pattern rows stay on one line on mobile.

---

# Chess Coach v1.2.3 Update Notes

## Release type

Home UI polish release.

## PR1 - Home top refresh

- Added `assets/images/home_greeting_background.jpg` as the greeting-card background.
- Refreshed the home greeting block while keeping the main header unchanged.
- Added compact trend sparklines to the top KPI cards.
- Added a visual accuracy ring to the recent performance summary.
- Bumped `config/version.php` to `1.2.3`.
- Updated the service worker cache to `chess-coach-v1.2.3`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm the home header remains unchanged.
- Confirm the greeting block uses the new background image.
- Confirm KPI cards and the recent summary remain responsive on mobile.

---

# Chess Coach v1.2.2 Update Notes

## Release type

Login UI refresh.

## Changes

- Redesigned `index.php` with a full-screen branded login experience.
- Added `assets/images/background_login.png` as the local login background.
- Added icon-enhanced username/password fields and a password visibility toggle.
- Improved responsive login layout for mobile screens.
- Bumped `config/version.php` to `1.2.2`.
- Updated the service worker cache to `chess-coach-v1.2.2`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm the login page loads on desktop and mobile.
- Confirm login success and invalid-password error still work.
- Confirm the service worker includes the new background asset.

---

# Chess Coach v1.2.1 Update Notes

## Release type

Small UX polish release.

## Changes

- In `review.php`, clicking `Mejor` now highlights the origin and destination squares of the Stockfish best move in blue.
- In `openings-lab.php`, the detected-openings KPI values are grouped and aligned consistently.
- Bumped `config/version.php` to `1.2.1`.
- Updated the service worker cache to `chess-coach-v1.2.1`.

## SQL migration

No SQL migration required.

## Verification

- Run PHP syntax checks.
- Run JavaScript syntax checks for changed files.
- Confirm `review.php` highlights the best-move squares when clicking `Mejor`.
- Confirm detected-opening cards align `partidas`, `score` and `accuracy` cleanly.

---

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

## PR3 - Lab de Aperturas UI

The third v1.2.0 PR adds the first user-facing Openings Lab page and navigation entry.

Changed files:

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/openings_lab.js`
- `includes/helpers.php`
- `openings-lab.php`
- `profile.php`
- `service-worker.js`

### Page and navigation

The hamburger menu entry previously shown as a disabled Aperturas placeholder now opens:

```text
openings-lab.php
```

The menu label is:

```text
Lab de Aperturas
```

### UI included

`openings-lab.php` shows:

- summary KPI cards
- detected openings list
- minimum-games selector
- detail panel for the selected opening
- score rate, opening accuracy, opening ACPL and move-10 evaluation
- practical diagnosis and recommendation
- example games with direct links to `review.php`

This PR consumes `api/openings.php` from PR2. It does not add training exercises from openings and does not add a full opening-repertoire editor.

### Profile backfill

The profile page now exposes a manual Openings Lab backfill in the existing Procesos batch block. It calls:

```text
POST api/openings.php?action=backfill
```

The action requires CSRF and processes up to 25 games per execution.

## PR4 - Opening detail and review links

The fourth v1.2.0 PR improves navigation from the Openings Lab into concrete games and review moments.

Changed files:

- `CHANGELOG.md`
- `README_UPDATE.md`
- `api/games.php`
- `assets/js/games.js`
- `assets/js/openings_lab.js`
- `assets/js/review.js`
- `includes/openings.php`
- `service-worker.js`

### Opening detail improvements

The opening detail panel now shows a dedicated **Partidas recomendadas para revisar** section. These games are selected from the same opening when the user has early opening blunders, mistakes or inaccuracies.

Recommended review links point to:

```text
review.php?id=X&ply=Y
```

when a safe first-error ply is available. If not, they fall back to:

```text
review.php?id=X
```

`review.php` now accepts `ply` as an optional read-only query parameter. Invalid or missing values fall back to the first move.

### Games links

The Lab de Aperturas detail button now links to:

```text
games.php?opening_key=...
```

`api/games.php` supports that filter by checking the current user's `game_opening_profiles` rows. This keeps ownership scoped to the authenticated user.

## PR5 - Smart Tags and Training connection

The fifth v1.2.0 PR connects the Openings Lab detail view with existing Smart Tags and Training Center data.

Changed files:

- `CHANGELOG.md`
- `README_UPDATE.md`
- `assets/css/app.css`
- `assets/js/openings_lab.js`
- `includes/openings.php`
- `service-worker.js`

### Practical guidance

Each opening now exposes a structured recommended principle:

- sample-size warning when there are too few games
- tactical-collapse prevention when there are opening blunders
- typical-plan guidance when there are repeated mistakes
- basic principles when opening accuracy is low
- model-game guidance when the opening is working well

The guidance remains practical and non-memorization-heavy.

### Smart Tags connection

Opening details now include the 5 most frequent Smart Tags for that opening. Game tags and opening-window move tags are counted together, scoped to the current user's games and analyses.

### Recurrent early errors

Opening details now include recurrent early error patterns, grouped by ply and error type, with direct review links to examples.

### Existing Training connection

Opening details now show existing Training Center exercises for games with the same ECO code and `ply <= 16`. This PR only reads already-generated exercises and links to review/training. Training links now use the dedicated solver page so the selected exercise opens directly. It does not generate new exercises from the Lab.

## PR6 - Polish and release readiness

The sixth v1.2.0 PR closes the release by aligning version metadata, the final service worker cache name and release documentation.

Changed files:

- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `config/version.php`
- `service-worker.js`

### Version and cache

`config/version.php` now reports:

```text
1.2.0
```

`service-worker.js` now uses the final release cache:

```text
chess-coach-v1.2.0
```

This replaces the intermediate PR cache names used while building the Openings Lab.

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

PR3 adds a new page and JavaScript file, so `service-worker.js` now includes:

- `./openings-lab.php`
- `./assets/js/openings_lab.js`

The final v1.2.0 release readiness PR sets the final cache name to `chess-coach-v1.2.0`.

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
- Open `openings-lab.php` from the hamburger menu.
- Confirm the page renders summary cards, opening list and detail panel.
- Confirm changing the minimum-games selector reloads the list.
- Confirm example-game links open `review.php`.
- Open `profile.php` and confirm the Procesos batch block shows Backfill de aperturas.
- Run the Openings Lab backfill and confirm the pending counter updates.
- From `openings-lab.php`, open an opening detail and confirm recommended games are shown when early opening errors exist.
- Click a recommended review link and confirm `review.php` opens at the requested move when `ply` is present.
- Click `Ver partidas` in an opening detail and confirm `games.php` lists games for that opening.
- Confirm the opening detail shows frequent Smart Tags when tags exist.
- Confirm recurrent early errors link to `review.php`.
- Confirm existing related exercises appear when Training Center exercises already exist for games in that opening.
- Confirm `config/version.php` reports `1.2.0`.
- Confirm `service-worker.js` uses `chess-coach-v1.2.0`.
