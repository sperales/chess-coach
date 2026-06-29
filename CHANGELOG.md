# CHANGELOG

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
