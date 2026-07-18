# CHANGELOG

## v1.4.14 - Completed training goal persistence

### Fixed

- Evaluated goal progress before dismissing rule-based goals that are no longer current candidates.
- Kept completed daily and weekly goals visible until their active period ends.
- Recovered completed goals that had already been hidden by the previous refresh order.
- Prevented completed goals from returning to pending during the same period.
- Added regression coverage for daily goals, weekly goals and recovery of previously dismissed completions.
- Bumped the app version and PWA cache to `1.4.14`.

### Database

- No SQL migration or manual data repair is required.

## v1.4.13 - SAN notation for review best moves

### Changed

- Converted Stockfish best-move alternatives in review comments to SAN using each move's `fen_before`.
- Reused the same SAN presentation for the review `Mejor` action.
- Preserved UCI for engine data and board highlighting.
- Added a readable coordinate fallback for invalid historical positions.
- Added black-to-move notation coverage.
- Bumped the app version and PWA cache to `1.4.13`.

### Database

- No SQL migration or backfill is required.

## v1.4.12 - Personal training plan and performance tracking

### Added

- Added persistent solve runs, progressive hint history and normalized progress events.
- Added three contextual hint levels that reveal progressively more information without exposing the destination square.
- Added the moving `Índice de rendimiento` based on recent exercises and analyzed games.
- Added the `Autonomía` metric for measuring successful solving without assistance.
- Added deterministic daily and weekly training plans with measurable goals and action links.
- Added review completion tracking based on distinct visited plies.
- Added plan, performance and autonomy presentation across Home, Training, Profile, header and Review.
- Added dependency-free tests for resolution quality, hints, performance formulas and training plans.

### Changed

- Kept training sessions internal and centered the UI on progress, objectives and recommended actions.
- Replaced the previous home milestone emphasis with a concrete personal plan.
- Made progressive hints and exercise outcomes contribute to future coaching evidence.
- Bumped the app version and PWA cache to `1.4.12`.

### Database

- SQL migration required: `sql/migrations/030_changes_1.4.12.sql`.
- Historical attempts remain valid but are not reconstructed as solve runs because their hint context is unknown.

### Security

- Plan generation and progress refresh use authenticated CSRF-protected POST endpoints.
- Review and progress data remain scoped to the authenticated user.
- Progressive hints do not expose solution UCI or destination coordinates.

## v1.4.11 - SAN notation for training exercises

### Added

- Added a centralized server-side UCI-to-SAN presentation helper based on each exercise's pre-move FEN.
- Added SAN support for captures, disambiguation, check, mate, castling, promotion and en passant.
- Added dependency-free PHP fixtures for legal SAN conversion and coordinate fallbacks.

### Changed

- Displayed SAN in training attempts, revealed solutions and the correct-move summary while preserving UCI internally.
- Added a readable coordinate fallback when a historical FEN or move cannot be converted safely.
- Bumped the app version and PWA cache to `1.4.11`.

### Database

- No SQL migration is required.

## v1.4.10 - Mobile-first training solver

### Changed

- Reorganized the mobile exercise solver around the board, instructions, feedback and primary actions.
- Increased the usable board width on narrow screens while preserving coordinates and a square aspect ratio.
- Moved progress, exercise metadata and source-game information below the active solving controls on mobile.
- Compacted the mobile hero and toolbar without changing their desktop presentation.
- Added a dedicated landscape layout with the board and solving panel aligned side by side.
- Hid the `Comprobar` button on mobile when automatic move submission is enabled.
- Kept manual submission as a full-width primary action when automatic submission is disabled.
- Stabilized the solved-move check marker dimensions and glyph alignment on mobile browsers.
- Bumped the app version and PWA cache to `1.4.10`.

### Database

- No SQL migration is required.

## v1.4.9 - Training solution highlight

### Changed

- Kept the latest incorrect destination marked in red when an exercise reaches the attempt limit.
- Changed the revealed solution squares to blue so the failed attempt and the correct solution remain visually distinct.
- Gave the solution highlight precedence when a square belongs to both states.
- Bumped the app version and PWA cache to `1.4.9`.

### Database

- No SQL migration is required.

## v1.4.8 - Training feedback and principal variations

### Added

- Added a persistent red destination marker for the latest incorrect exercise attempt.
- Added a resumable Stockfish enrichment process for unresolved version 2 exercises.
- Stored refreshed bestmove, principal variation, score, score type, depth and refresh timestamp.
- Recorded bestmove mismatches without replacing the exercise's accepted solution.
- Added constrained Stockfish validation for historical solutions when the refreshed bestmove differs.
- Added support for one validated alternative solution per exercise.
- Exposed safe per-exercise diagnostics for partially completed enrichment batches in the profile process result.

### Changed

- Enriched safe mate and evaluation descriptions when the refreshed solution matches.
- Kept up to 50 enrichments per user action while splitting the work into HTTP requests of at most 10 exercises to avoid shared-hosting timeouts.
- Added a clear client-side diagnostic when the hosting returns an HTML timeout/error page instead of JSON.
- Accepted alternative moves only within a 30-centipawn tolerance or a conservative equivalent mate distance.
- Kept newly generated exercises at content version 2 until explicitly enriched.
- Bumped the app version and PWA cache to `1.4.8`.

### Database

- SQL migration required: `sql/migrations/029_changes_1.4.8.sql`.

### Security

- Principal variations and refreshed engine solutions are hidden from unresolved exercise API payloads.

## v1.4.7 - Contextual training exercise content

### Changed

- Reworked deterministic exercise classification to prioritize move-level evidence.
- Restricted game-level Smart Tags to the exact plies recorded as evidence.
- Distinguished favorable and unfavorable mate evaluations from the side-to-move perspective.
- Added concise exercise titles and richer instructions and feedback without external AI.
- Recalculated exercise priority using only relevant position context.
- Added a resumable profile backfill that preserves exercise IDs, attempts, progress and repetition dates.
- Bumped the app version and PWA cache to `1.4.7`.

### Database

- SQL migration required: `sql/migrations/028_changes_1.4.7.sql`.
- Existing exercises remain valid and are upgraded in batches of 200 from `profile.php`.

## v1.4.6 - Training mobile metadata

### Changed

- Kept exercise attempts, difficulty and priority aligned in three columns on mobile.
- Centered the three indicators for a more compact training solver sidebar.
- Added persistent settings for legal-move hints and automatic move submission.
- Enforced correct side-to-move and legal destination selection on the training board.
- Removed the disabled legal-move checkbox from the exercise page.
- Kept a correctly solved move on the board and highlighted its origin and destination in green.
- Added a green check indicator over the correct destination square.
- Bumped the app version and PWA cache to `1.4.6`.

### Database

- SQL migration required: `sql/migrations/027_changes_1.4.6.sql`.

## v1.4.5 - Mobile summary cards

### Changed

- Compacted the summary metric cards on mobile in Analysis Pending, Training Center and Openings Lab.
- Reorganized each card with icon and title on the left, plus value and supporting detail on the right.
- Preserved the four-column desktop grid while updating the internal card composition.
- Bumped the app version and PWA cache to `1.4.5`.

### Database

- No SQL migration is required.

## v1.4.4 - Board personalization

### Added

- Added local transparent PNG `Set 3` pieces.
- Added per-user green, brown, blue and gray board color themes.
- Added richer piece and board previews to profile settings.

### Changed

- Applied shared board colors to review, training lists and the exercise solver.
- Bumped `config/version.php` to `1.4.4`.
- Updated the service worker cache and precached `Set 3` assets.

### Database

- SQL migration required: `sql/migrations/026_changes_1.4.4.sql`.

## v1.4.3 - Spanish ECO catalog

### Added

- Added `opening_families` for stable opening-family grouping.
- Added `eco_codes` with the complete `A00`-`E99` catalog.
- Added canonical Spanish opening and representative variation labels.
- Added the same catalog baseline to `sql/install.sql` for new installations.
- Added a reusable backend resolver with PGN-first label precedence.
- Added family and variation metadata to Openings Lab and games API responses.
- Added friendly ECO, opening and variation hierarchy to Openings Lab.

### Notes

- The catalog is derived from a pinned CC0 Lichess dataset revision.
- ECO labels are canonical categories and do not claim exact line detection.
- Existing opening profiles are enriched at read time and do not require a rewrite.
- Opening grouping and training metrics remain unchanged.

### Changed

- Bumped `config/version.php` to `1.4.3`.
- Updated the service worker cache name to `chess-coach-v1.4.3`.
- Updated stable baseline and release documentation.

### Database

- SQL migration required: `sql/migrations/025_changes_1.4.3.sql`.

## v1.4.2 - Training milestones polish

### Changed

- Made the home milestone summary expandable with a complete achieved/pending milestone list.
- Improved responsive milestone detail presentation.
- Reused and aligned the Openings Lab toggle arrow in the milestone control.
- Fixed vertical alignment in highlighted training exercise metadata chips.
- Bumped `config/version.php` to `1.4.2`.
- Updated the service worker cache name to `chess-coach-v1.4.2`.

### Database

- No SQL migration required.

## v1.4.1 - Header streak polish

### Changed

- Changed the header streak indicator to gray until valid training activity is recorded for the current day.
- Changed the indicator to green after training, independently of whether the full daily goal has been completed.
- Removed the redundant `racha` text from the compact header indicator.
- Added a `Fallados` status filter to the Training Center.
- Highlighted the first exercise with a board preview and difficulty.
- Added a dedicated difficulty column to the remaining exercise list.
- Bumped `config/version.php` to `1.4.1`.
- Updated the service worker cache name to `chess-coach-v1.4.1`.

### Database

- No SQL migration required.

## v1.4.0 - Training Experience

### Added

- Added training goal settings for daily exercises, daily minutes and weekly targets.
- Added automatic Training Experience progress for today, week, streaks and repeat queue.
- Added smart repetition scheduling for solved, failed and skipped exercises.
- Added clearer exercise feedback after solving, exhausting attempts or skipping.
- Added a compact Training Experience block to the home dashboard.
- Added a compact header streak indicator.
- Added lightweight professional milestones for consistency and progress.

### Changed

- Removed visible manual session management from the training UI in favor of automatic progress.
- Prioritized due repeat exercises in the pending/recommended training lists.
- Bumped `config/version.php` to `1.4.0`.
- Updated the service worker cache name to `chess-coach-v1.4.0`.

### Database

- SQL migration required: `sql/migrations/024_changes_1.4.0.sql`.

## v1.3.1 - Openings Lab UI polish

### Changed

- Removed the disabled `Etiquetas` entry from the hamburger menu.
- Collapsed the Openings Lab `Vista` filter panel by default.
- Added a `Vista` filter toggle for expanding and collapsing the panel.
- Added spacing between the Openings Lab `Vista` panel and the lower content layout.
- Bumped `config/version.php` to `1.3.1`.
- Updated the service worker cache name to `chess-coach-v1.3.1`.

### Database

- No SQL migration required.

## v1.3.0 - Player DNA

### Added

- Added `sql/migrations/023_changes_1.3.0.sql` with `player_dna_snapshots` and `player_dna_runs`.
- Added Player DNA backend scoring and snapshot generation in `includes/player_dna.php`.
- Added `api/player-dna.php` for snapshot reads and manual recomputation.
- Added the `ADN del jugador` page with summary, dimensions, strengths, weaknesses, style indicators, comparisons and next-step recommendation.
- Added manual Player DNA recompute controls to `profile.php`.
- Added `ADN del jugador` to the main menu.
- Added `assets/images/statistics_background.jpg` as the Player DNA hero background.
- Added a compact Player DNA summary block to the home dashboard.

### Changed

- Updated Player DNA dimension colors to green/orange/red thresholds.
- Bumped `config/version.php` to `1.3.0`.
- Updated the service worker cache name to `chess-coach-v1.3.0`.
- Updated README, release notes, roadmap and agent baseline documentation for v1.3.0.

### Database

- SQL migration required: `sql/migrations/023_changes_1.3.0.sql`.

## v1.2.9 - Openings Lab UI polish

### Added

- Added `assets/images/lab_background.jpg` as the Openings Lab hero background.

### Changed

- Refreshed the `openings-lab.php` hero block with a background-image treatment.
- Aligned related exercise actions to the right on desktop.
- Styled Openings Lab review actions in green and training actions in blue.
- Applied the green review action style to recommended games and example games.
- Bumped `config/version.php` to `1.2.9`.
- Updated the service worker cache name to `chess-coach-v1.2.9`.

### Database

- No SQL migration required.

## v1.2.8 - Training solver page refresh

### Added

- Added `assets/images/training_background.jpg` as the Training Center hero background.
- Added `training-exercise.php` as the dedicated exercise-solving page.

### Changed

- Refreshed the `training.php` hero block with a background-image treatment.
- Changed Training Center exercise actions so pending exercises open on the dedicated solver page.
- Reused the existing Training Center API/session/attempt logic in the dedicated solver page.
- Added exercise metadata blocks for difficulty, priority, source game, details and attempt history.
- Updated Openings Lab training links to point to the dedicated solver page.
- Bumped `config/version.php` to `1.2.8`.
- Updated the service worker cache name to `chess-coach-v1.2.8`.

### Database

- No SQL migration required.

## v1.2.7 - Import and analysis queue UI polish

### Added

- Added `assets/images/analysis_background.jpg` as the analysis queue hero background.
- Added `assets/images/import_background.jpg` as the import page hero background.

### Changed

- Refreshed the `analysis-pending.php` hero block with a background-image treatment.
- Refreshed the `import-chesscom.php` hero block with a background-image treatment.
- Reworked the import page into two focused import cards and a bottom help panel.
- Removed the `Volver` button from the import page.
- Bumped `config/version.php` to `1.2.7`.
- Updated the service worker cache name to `chess-coach-v1.2.7`.

### Database

- No SQL migration required.

## v1.2.6 - Home, review and games UI polish

### Added

- Added `assets/images/games_background.jpg` as the games page hero background.

### Changed

- Renamed the home summary `Decisión grave` label to `Omisión grave`.
- Adjusted the review `Enfoque` insight card into title, focus value and explanation lines.
- Refreshed the `games.php` hero block with a background-image treatment.
- Split games table actions into separate unlabeled columns.
- Styled `Revisar` as the primary green action in games lists while keeping `Reanalizar` as a secondary action.
- Added B/E/I counts to recommended games in the home games block.
- Bumped `config/version.php` to `1.2.6`.
- Updated the service worker cache name to `chess-coach-v1.2.6`.

### Database

- No SQL migration required.

## v1.2.5 - Review UI refresh

### Added

- Added `assets/images/review_background.jpg` as the review hero background.
- Added a bottom review section with a coach tip and quick insights.

### Changed

- Refreshed the initial `review.php` hero block with a background-image treatment.
- Updated the evaluation chart with a white evaluation line and semi-transparent filled area.
- Bumped `config/version.php` to `1.2.5`.
- Updated the service worker cache name to `chess-coach-v1.2.5`.

### Database

- No SQL migration required.

## v1.2.4 - Review and home UI polish

### Changed

- Changed the `Excelente` review icon from a browser-colored emoji to a green arrow-style symbol.
- Filtered move Smart Tags in `review.php` when they duplicate the move evaluation label.
- Reused review summary icons in the home latest-review summary block.
- Kept detected-pattern rows on one line in the mobile home layout.
- Improved the home motivational quote mark typography.
- Bumped `config/version.php` to `1.2.4`.
- Updated the service worker cache name to `chess-coach-v1.2.4`.

### Database

- No SQL migration required.

## v1.2.3 - Home UI polish

### Changed

- Refreshed the top home greeting block with a local chess background image.
- Added compact trend sparklines to the home KPI cards.
- Added a visual accuracy ring to the recent performance summary block.
- Bumped `config/version.php` to `1.2.3`.
- Updated the service worker cache name to `chess-coach-v1.2.3`.

### Database

- No SQL migration required.

## v1.2.2 - Login UI refresh

### Changed

- Redesigned the login page with a full-screen Chess Coach background and glass-style login panel.
- Added local `assets/images/background_login.png` for the login background.
- Added icon-enhanced username/password inputs and a password visibility toggle.
- Bumped `config/version.php` to `1.2.2`.
- Updated the service worker cache name to `chess-coach-v1.2.2`.

### Database

- No SQL migration required.

## v1.2.1 - Review and Openings Lab polish

### Changed

- Highlighted the Stockfish best-move origin and destination squares in blue when clicking `Mejor` in review.
- Aligned detected-opening KPI values and labels in the Openings Lab list.
- Bumped `config/version.php` to `1.2.1`.
- Updated the service worker cache name to `chess-coach-v1.2.1`.

### Database

- No SQL migration required.

## v1.2.0 - Openings Lab

### Added

- Added `game_opening_profiles` to store one opening profile per user game.
- Added `opening_profile_runs` to log opening-profile backfills.
- Added `includes/openings.php` with helpers for opening identity, fallback signatures, profile refresh and batch backfill.
- Connected completed Stockfish analyses to refresh opening profiles as derived metadata.
- Added `sql/migrations/022_changes_1.2.0.sql` for the Openings Lab data foundation.
- Added `api/openings.php` with authenticated Openings Lab JSON endpoints.
- Added backend metrics for games, results, opening accuracy, early mistakes, move-10 evaluation, example games and practical opening recommendations.
- Added the first `openings-lab.php` UI with summary cards, opening list, detail panel and review links.
- Added `assets/js/openings_lab.js` for Openings Lab API rendering.
- Enabled the hamburger menu entry as `Lab de Aperturas`.
- Added the Openings Lab manual backfill action to `profile.php`.
- Added recommended review games to the opening detail view.
- Added opening-key filtering support for `games.php`.
- Added optional `review.php?id=X&ply=Y` deep links for safe opening review moments.
- Added structured practical opening principles to the Openings Lab detail view.
- Connected opening details with frequent Smart Tags, recurrent early errors and existing related Training Center exercises, including direct links to open a specific exercise.
- Bumped `config/version.php` to `1.2.0`.
- Updated the final service worker cache name to `chess-coach-v1.2.0`.
- Updated release documentation and roadmap baseline for the completed Openings Lab release.

### Notes

- No Openings Lab UI is included in PR1 or PR2.
- No Training Center exercise generation changes are included in v1.2.0.

### Database

- Added `sql/migrations/022_changes_1.2.0.sql` for `game_opening_profiles` and `opening_profile_runs`.

## v1.1.6 - Security and documentation hardening

### Changed

- Started the v1.1.6 hardening release notes.
- Aligned README, AGENTS and ROADMAP during the v1.1.6 hardening pass.
- Documented that v1.1.6 is a hardening release before v1.2.0 Openings Lab.
- Updated chess board documentation to reflect local PNG piece sets in review and training boards.
- Added an encoding audit covering repository Markdown, PHP, JavaScript, CSS and SQL text files.
- Confirmed repository text files are valid UTF-8 and no real mojibake cleanup is required.
- Tightened Training Center exercise-tag loading so tags are scoped through the current user's exercises.
- Tightened analysis queue ownership checks for missing-game detection and worker history game details.
- Added session-based CSRF protection for authenticated mutating actions.
- Added CSRF tokens to profile forms and JavaScript POST requests.
- Kept the login form and HTTP GET cron worker out of CSRF scope for this hardening pass.
- Kept Training Center GET endpoints read-only for session creation; session changes now go through CSRF-protected POST actions.
- Removed the raw cron worker token from authenticated worker summary responses.
- Stopped exposing the configured Stockfish filesystem path in engine status responses.
- Sanitized analysis, Chess.com import, Smart Tags backfill and Training Center backfill errors before storing or returning them.
- Centralized JSON request-body parsing and GET-only request validation in shared helpers.
- Bumped `config/version.php` to `1.1.6`.
- Updated README, AGENTS and ROADMAP to make v1.1.6 the stable baseline.
- Updated the final service worker cache name to `chess-coach-v1.1.6`.

### Database

- No SQL migration required for the documentation alignment, encoding audit, API ownership, CSRF hardening, output-safety or light architecture cleanup PRs.

## v1.1.5 - Training Center hints

### Changed

- Added a `Pista` button that highlights/selects the origin square of the correct move.
- Removed the `Reintentar selección` button from Training Center exercise controls.
- Added post-exercise actions: `Siguiente`, `Cerrar` and `Ver partida`.
- Hid `Comprobar`, `Pista` and `Saltar` after solving or exhausting an exercise.
- Exposed only `hint_from` for pending exercises so hints do not reveal the full solution.
- Added selectable chess piece sets from `Ajustes / Mi Perfil`.
- Moved piece assets into `assets/pieces/Set 1` and added `assets/pieces/Set 2`.
- Changed the dashboard recent-summary error label from `B/M/I` to `B/E/I`.
- Changed the recommended-games table third column label from `Ritmo` to `Accuracy`.
- Changed the home games block title between `Últimas partidas` and `Partidas recomendadas`.
- Bumped `config/version.php` to `1.1.5`.
- Updated the service worker cache name to `chess-coach-v1.1.5-dashboard-labels`.

### Database

- Added `sql/migrations/021_changes_1.1.5.sql` to store the selected piece set per user.

## v1.1.4 - Training Center polish

### Changed

- Highlighted the side to move in Training Center exercise prompts.
- Added a white or black pawn icon next to the side-to-move text.
- Added a visible per-exercise timer in the Training Center solver.
- Added red origin/destination highlights when the solution is revealed after 5 failed attempts.
- Added legal destination hints when selecting a piece in Training Center exercises.
- Marked Training Center exercises as closed after 5 failed attempts so they no longer appear as pending recommendations.
- Made Training Center session failed/skipped counters refresh consistently after exhausted or skipped exercises.
- Replaced manual Training Center start/end session controls with automatic active sessions and a single `Nueva sesión` action.
- Changed the review move list to show White and Black moves side by side in one row per full move.
- Bumped `config/version.php` to `1.1.4`.
- Updated the service worker cache name to `chess-coach-v1.1.4-training-auto-session`.

### Database

- No SQL migration required.

## v1.1.3 - Review board coordinates

### Added

- Added rank and file coordinates next to the review board.
- Kept coordinates synchronized with board orientation and the manual board flip control.
- Bumped `config/version.php` to `1.1.3`.
- Updated the service worker cache name to `chess-coach-v1.1.3-review-board-coordinates`.

### Database

- No SQL migration required.

## v1.1.2 - Board highlights

### Changed

- Changed review and training board move highlights to color the full origin and destination squares.
- Added a light-blue selected-move highlight in Training Center.
- Added Training Center move preview so the selected piece appears on the destination square before submitting.
- Bumped `config/version.php` to `1.1.2`.
- Updated the service worker cache name to `chess-coach-v1.1.2-board-highlights`.

### Database

- No SQL migration required.

## v1.1.1 - Training sessions and polish

### Added

- Added start/end controls for Training Center sessions.
- Added per-session metrics for exercises trained, solved, failed, skipped, attempts and average time.
- Linked exercise attempts and skipped exercises to the active training session.
- Improved Training Center feedback and empty states.
- Bumped `config/version.php` to `1.1.1`.
- Updated the service worker cache name to `chess-coach-v1.1.1-training-sessions-polish`.

### Database

- No SQL migration required; this release uses the Training Center tables added in `v1.1.0`.

## v1.1.0 - Training Center

### Added

- Added Training Center foundation schema for generated exercises, exercise tags, sessions, attempts and generation runs.
- Added `includes/training.php` with shared exercise type, difficulty, priority, prompt and feedback helpers.
- Added support in the data model for exercises generated from both player moves and opponent moves.
- Added fields to keep resolved exercises out of the recommended queue while pending exercises remain available.
- Added automatic Training Center exercise generation after completed Stockfish analysis.
- Added a manual Training Center exercise backfill process to `profile.php`.
- Added authenticated backfill/status endpoints for Training Center exercise generation.
- Added the first Training Center page with exercise stats, filters and a paginated exercise list.
- Enabled the `Entrenamiento` menu item and home quick action.
- Added `assets/js/training.js` and cached the Training Center page/assets in the service worker.
- Added interactive Training Center exercise solving on a board.
- Added server-side exact `bestmove` validation and attempt recording.
- Added a 5-attempt limit per exercise interaction with immediate feedback.
- Added previous-move context and review-board visual styling to the Training Center board.
- Bumped `config/version.php` to `1.1.0`.
- Updated the service worker cache name to `chess-coach-v1.1.0-training-solver`.

### Notes

- PR1 adds the Training Center backend foundation.
- PR2 adds exercise generation and profile backfill.
- PR3 adds the Training Center page and exercise browsing.
- PR4 adds interactive board solving and attempt tracking.

### Database

- Added `sql/migrations/020_changes_1.1.0.sql`.

## v1.0.4 - Review player perspective and UX

### Changed

- Added automatic review board orientation based on the player's color.
- Added a manual `Girar tablero` control to flip the review board.
- Kept move highlights tied to real board coordinates after orientation changes.
- Replaced raw UCI best-move hints in review with a minimal human-readable notation.
- Bumped `config/version.php` to `1.0.4`.
- Updated the service worker cache name to `chess-coach-v1.0.4`.

### Database

- No SQL migration required.

## v1.0.3 - Stockfish runner performance

### Changed

- Reused Stockfish processes in bounded chunks during each analyzed game instead of starting a new process for every FEN.
- Added per-game FEN evaluation caching so repeated positions are evaluated only once during an analysis run.
- Added explicit Stockfish UCI options for `Threads` and `Hash`, with `threads = 1` and `hash_mb = 32` defaults.
- Added configurable Stockfish runner recycling through `restart_after_evaluations`, defaulting to `40`.
- Documented the new engine config keys in `config/engine.example.php`.
- Bumped `config/version.php` to `1.0.3`.
- Updated the service worker cache name to `chess-coach-v1.0.3`.

### Database

- No SQL migration required.

## v1.0.2 - Review board piece images

### Changed

- Replaced Unicode chess pieces in the review board with local transparent PNG piece assets.
- Added responsive board-piece CSS so pieces scale with each square on desktop and mobile.
- Adjusted the mobile review board layout so the board fills the available panel width while staying square.
- Added the 12 PNG piece assets to the service worker cache.
- Bumped `config/version.php` to `1.0.2`.
- Updated the service worker cache name to `chess-coach-v1.0.2`.

### Database

- No SQL migration required.

## v1.0.1 - Games tag filter polish

### Changed

- Made Smart Tag chips in `games.php` clickable so they open the games list filtered by that tag.
- Fixed the home "Pendientes de análisis" KPI so `0` is displayed correctly and "Ver cola" links to `analysis-pending.php`.
- Highlighted the current main focus in the home greeting and labeled summary error counts as `B`, `M` and `I`.
- Added an `Accuracy` KPI to the home "Resumen de últimas partidas" block.
- Simplified the home games panel toggle so only the alternate list mode button is shown.
- Added optional ECO/opening metadata to games and displayed it in a new `Apertura` column in `games.php`.
- Linked ECO codes to the PGN `ECOUrl` value when available.
- Bumped `config/version.php` to `1.0.1`.
- Updated the service worker cache name to `chess-coach-v1.0.1`.

### Database

- Added `sql/migrations/019_changes_1.0.1.sql` to add optional `eco_code`, `opening_name` and `eco_url` columns to `games`.

## v1.0.0 - Personal Trainer Dashboard

### Added

- Added Personal Trainer Dashboard backend foundation.
- Added authenticated read-only `api/dashboard.php` endpoint.
- Added `includes/dashboard.php` service layer for recent performance, trends, top training focus, strengths and recommended reviews.
- Added calculations based on the latest 10 analyzed games, with a 6-game minimum for trend confidence.
- Added Personal Trainer Dashboard home UI blocks for current state, top 3 training focus, recent summary, strengths, detected patterns and recommended reviews.
- Added `assets/js/dashboard.js` for home dashboard rendering.
- Added URL-driven filters to `games.php` so dashboard links can open filtered game lists.
- Polished Personal Trainer Dashboard focus scoring, Spanish UI copy, empty states and actionable links.
- Expanded `games.php?tag=...` filtering to include both game-level and move-level Smart Tags.
- Bumped `config/version.php` to `1.0.0`.
- Updated the service worker cache name to `chess-coach-v1.0.0`.

### Notes

- PR1 added backend data. PR2 added the home UI that consumes it. PR3 polished focus scoring, copy, empty states and links. PR4 closes the release version/cache readiness.
- No external AI, paid APIs, npm, Composer, Docker or persistent worker processes were introduced.

### Database

- No SQL migration required.

## v0.9.5 - Cron worker access fix

### Changed

- Removed the blocking `worker/.htaccess` file so shared-hosting HTTP GET cron jobs can reach `worker/analyze_queue.php`.
- Documented that the worker endpoint must remain reachable and is protected by the token in `config/cron.php`.
- Bumped `config/version.php` to `0.9.5`.
- Updated the service worker cache name to `chess-coach-v0.9.5`.

### Verification

- PHP syntax lint passed locally across all PHP files.
- No JavaScript files changed in this release.

### Database

- No SQL migration required.

## v0.9.4 - CSS formatting maintenance

### Changed

- Reformatted `assets/css/app.css` to make future UI changes easier to review and maintain.
- Bumped `config/version.php` to `0.9.4`.
- Updated the service worker cache name to `chess-coach-v0.9.4`.

### Verification

- PHP syntax lint passed locally across all PHP files.
- No JavaScript files changed in this release.

### Database

- No SQL migration required.

## v0.9.3 - More UX/UI polish

### Added

- Added dedicated `games.php` page for paginated game browsing.
- Added game filters for color, result and Smart Tag.
- Added `assets/js/games.js` for the dedicated games page.

### Changed

- Updated the hamburger menu "Partidas" link to open `games.php`.
- Updated the home "Ver todas" link to open `games.php`.
- Updated analysis queue KPI colors: analyzed games use green and errors use red.
- Bumped `config/version.php` to `0.9.3`.
- Updated the service worker cache name to `chess-coach-v0.9.3`.
- Added `games.php` and `assets/js/games.js` to the service worker asset list.

### Verification

- PHP syntax lint passed locally across all PHP files.
- JavaScript syntax checks passed locally for changed JS files.

### Database

- No SQL migration required.

## v0.9.2 - UX/UI polish

### Added

- Added `motivational_quotes` table for home dashboard motivational quotes.
- Added an initial catalog of 15 chess-inspired motivational quotes with authors and source URLs.
- Added a server-side quote helper with a safe fallback while the migration is pending.
- Added configurable analysis queue pagination through `analysis_per_page` in `config/app.php`.

### Changed

- Replaced the fixed home quote with a random active quote on each home page load.
- Paginated `analysis-pending.php` with 50 analysis jobs per page by default.
- Bumped `config/version.php` to `0.9.2`.
- Updated the service worker cache name to `chess-coach-v0.9.2`.
- Updated `sql/install.sql` for fresh installs.

### Verification

- PHP syntax lint passed locally across all PHP files.
- JavaScript syntax check could not be run because Node.js is not installed locally.

### Database

- Added SQL migration `sql/migrations/018_changes_0.9.2.sql`.

## v0.9.1 - Dashboard accuracy and review piece polish

### Changed

- Updated the review board so white pieces use the same filled Unicode glyphs as black pieces, with color applied through CSS.
- Calculated the home dashboard "Accuracy media" from completed analyzed games instead of showing a placeholder.
- Bumped `config/version.php` to `0.9.1`.
- Updated the service worker cache name to `chess-coach-v0.9.1`.

### Verification

- PHP syntax lint passed locally across all PHP files.
- JavaScript syntax check could not be run because Node.js is not installed locally.

### Database

- No SQL migration required.

## v0.9.0 - Smart Tags

### Added

- Added Smart Tags schema and initial Spanish tag catalog.
- Added automatic Smart Tags generation after completed Stockfish analysis.
- Added game-level tags for opening issues, endgame mistakes, lost winning positions, comebacks, converted advantages, precise games and strong finishes.
- Added move-level tags for own inaccuracies, mistakes, blunders, missed mates and allowed mates.
- Added Smart Tags chips to the home game list.
- Added frequent tags summary to the dashboard.
- Added game and move tags to the review page.
- Added profile "Procesos batch" block for Smart Tags backfill on previously analyzed games.
- Added `smart_tag_runs` tracking for backfill executions.

### Changed

- Bumped `config/version.php` to `0.9.0`.
- Updated the service worker cache name to `chess-coach-v0.9.0`.
- Added `profile.php` to the service worker asset list.
- Added cache-busting query parameters for page CSS/JS assets touched by Smart Tags UI.

### Verification

- PHP syntax lint passed locally across all PHP files.

### Database

- Added SQL migration `sql/migrations/017_changes_0.9.0.sql`.

### Notes

- Smart Tags detect, store and display patterns only.
- This release does not add training recommendations or generated exercises.
- Existing analyzed games require the profile backfill process to receive Smart Tags.

## v0.8.8 - Review chart visual refinement

### Changed

- Updated the review page evaluation chart to fill the area under the evaluation line in light color.
- Changed the evaluation chart line to a darker neutral stroke for contrast.
- Kept critical move markers color-coded.
- Bumped `config/version.php` to `0.8.8`.
- Updated the service worker cache name to `chess-coach-v0.8.8`.
- Updated release documentation for the current version.

### Verification

- PHP syntax lint passed locally across all PHP files.

### Database

- No SQL migration required.

### Notes

- No analysis logic changed.
- No Stockfish, PGN parsing, move reconstruction, review calculation, authentication, or database logic changed.

## v0.8.7 - Developer verification docs

### Added

- Added `VERIFY.md` with lightweight local verification commands.
- Documented the Windows PowerShell PHP lint command.
- Documented the Unix-like PHP lint command.

### Changed

- Bumped `config/version.php` to `0.8.7`.
- Updated the service worker cache name to `chess-coach-v0.8.7`.
- Updated release documentation for the current version.
- Updated project documentation to point to `VERIFY.md`.

### Verification

- PHP syntax lint passed locally across all PHP files.

### Database

- No SQL migration required.

### Notes

- No application behavior changed.
- No Stockfish, analysis, PGN parsing, review, authentication, or database logic changed.

## v0.8.6 - Repository cleanup

### Added

- Added `README_UPDATE.md` for release-specific deployment notes.
- Added `CHANGELOG.md` for release history.
- Added `config/.htaccess` to reflect the deployed config-directory protection.
- Added `worker/.htaccess` to reflect the deployed worker-directory protection.
- Added `storage/logs/.gitkeep` so the log directory structure is tracked.
- Added Git workflow guidance to `AGENTS.md`: use version/scoped branches, open PRs to `main`, and do not merge without explicit user approval.

### Changed

- Renamed `gitignore` to `.gitignore`.
- Bumped `config/version.php` to `0.8.6`.
- Updated the service worker cache name to `chess-coach-v0.8.6`.
- Updated basic documentation to describe the tracked deployment files and current baseline.

### Database

- No SQL migration required.

### Notes

- No application behavior changed.
- No Stockfish, analysis, PGN parsing, review, authentication, or database logic changed.

## v0.8.5 - Stable baseline

### Notes

- Stable baseline documented in `README.md` and `ROADMAP.md`.
- Review board uses Unicode chess pieces for stability.
