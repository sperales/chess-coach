# CHANGELOG

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
