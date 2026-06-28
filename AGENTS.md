# AGENTS.md

## Project: Chess Coach

Chess Coach is a personal chess training PWA built with PHP, MariaDB, vanilla JavaScript and Stockfish.

This file defines the working rules for Codex and other coding agents. Follow these instructions unless the user explicitly overrides them.

---

## Current stable baseline

Current stable version: **0.8.5**

The app is deployed manually to shared hosting and must remain compatible with that environment.

---

## Core principle

Work incrementally and safely.

This project is not a playground for large rewrites. Prefer small, controlled changes that can be uploaded to shared hosting and verified quickly.

Every change must preserve the ability to deploy manually by uploading files.

---

## Technology constraints

Use:

- PHP
- MariaDB-compatible SQL
- Vanilla JavaScript
- HTML/CSS
- Local assets
- HTTP GET cron worker
- Server-side Stockfish

Avoid unless explicitly approved:

- npm
- Node.js build steps
- Composer dependencies
- Docker-only workflows
- queues requiring daemon processes
- background workers requiring persistent shell sessions
- external paid APIs
- frameworks that require a deployment/build pipeline

The app must continue working on shared hosting.

---

## Files that must never be overwritten casually

Do not overwrite or modify real production config files unless the user explicitly asks:

```text
config/database.php
config/engine.php
config/cron.php
```

These files may contain environment-specific or sensitive information.

Use and update the example files instead:

```text
config/database.example.php
config/engine.example.php
config/cron.example.php
```

If a config structure changes, update the corresponding `.example.php` file and document the change.

---

## Credentials and secrets

Never commit:

- database passwords
- cron worker tokens
- hosting paths with sensitive information
- API keys
- private credentials
- production-only secrets

If a secret is needed, use a placeholder in example files:

```text
replace-with-your-value
```

---

## Versioning rules

Every requested app change must have a version number.

If the user asks for a code/app change without specifying a version, ask which version number to use before implementing.

For every versioned release, update:

```text
config/version.php
service-worker.js
README_UPDATE.md
```

The version in `config/version.php` must match the release version.

The service worker cache name must change on each release to prevent stale browser/PWA assets.

Example:

```js
const CACHE_NAME = 'chess-coach-v0.8.6';
```

---

## SQL migration rules

Only create a SQL migration when the database schema or stored baseline data needs to change.

Migration naming convention:

```text
sql/migrations/NNN_changes_X.Y.Z.sql
```

Example:

```text
sql/migrations/017_changes_0.8.6.sql
```

Migration rules:

1. Use MariaDB-compatible SQL.
2. Make migrations idempotent where reasonably possible.
3. Ensure INSERT statements match the exact number of table columns.
4. Avoid destructive changes unless explicitly requested.
5. Update `app_migrations` correctly.
6. If the migration only registers the version, make sure the syntax matches the actual table definition.
7. Do not assume `created_at` or `applied_at` columns exist unless verified from `install.sql`.

Before adding a migration, inspect:

```text
sql/install.sql
```

and relevant previous migrations.

---

## Service worker rules

When assets are added, removed or renamed:

1. Update the `ASSETS` list in `service-worker.js`.
2. Change the cache name.
3. Remove references to deleted files.
4. Confirm the app does not cache old assets by mistake.

This is especially important for:

- icons
- piece images
- JavaScript files
- CSS files
- review board assets

---

## Chess board rendering rules

Current stable approach in v0.8.5:

- Use Unicode chess pieces in the review board.
- Do not reintroduce SVG/PNG chess piece sets unless the user explicitly asks.

Previous attempts using custom SVG/PNG pieces caused visual inconsistencies and should not be restored accidentally.

---

## Stockfish rules

Stockfish runs server-side.

Do not move analysis to the browser unless explicitly requested.

Preserve configurability through:

```text
config/engine.php
config/engine.example.php
```

Avoid hardcoding hosting-specific Stockfish paths in source files.

---

## Worker / cron rules

The hosting supports HTTP GET cron jobs.

Preserve this architecture:

```text
worker/analyze_queue.php?token=...
```

The worker token must come from:

```text
config/cron.php
```

Do not require CLI cron unless the user explicitly changes hosting strategy.

---

## UI / UX rules

Keep the current design direction:

- Dark UI
- Chess Coach branding
- Mobile-friendly layout
- Header with logo, user, ELO, version and menu
- Clear Spanish labels in the app UI unless the user asks otherwise

Do not make large visual redesigns without approval.

---

## Language rules

The user usually discusses the project in Spanish.

Code comments and technical file names can remain in English.

User-facing app labels should generally remain Spanish unless the existing file uses English or the user requests a change.

---

## Review page rules

The review page is one of the most important areas of the app.

When changing it, verify:

- board rendering
- move navigation
- FEN reconstruction
- evaluation graph
- move classification
- best move display
- accuracy / ACPL display
- mobile layout
- service worker cache update

Do not change evaluation logic casually.

---

## Chess analysis correctness rules

Be careful with chess-specific logic.

When changing move parsing, FEN handling or analysis interpretation:

1. Verify color perspective.
2. Verify evaluation normalization.
3. Verify mate handling.
4. Verify centipawn loss calculation.
5. Verify final positions and game results.
6. Avoid introducing impossible or inconsistent board states.

Past issues included evaluation direction and mate handling, so treat these areas as high risk.

---

## Testing requirements

Before delivering a version, run PHP syntax checks:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

If `xargs -0` is unavailable, use an equivalent loop.

Also inspect changed JavaScript for obvious syntax errors.

Manual validation checklist:

- Login page loads.
- App dashboard loads.
- Import page loads.
- Manual PGN import works.
- Chess.com import works.
- Analysis queue page loads.
- Worker endpoint returns valid JSON.
- Review page loads.
- Existing analyzed game can be reviewed.
- Service worker cache name changed.
- No deleted assets are still referenced.
- No real credentials are included.

---

## Release packaging rules

When preparing a release ZIP:

1. Include the full project structure.
2. Exclude real config files if they exist locally.
3. Include example config files.
4. Include `README_UPDATE.md`.
5. Include any new SQL migration.
6. Confirm `config/version.php` matches the ZIP version.
7. Confirm `service-worker.js` cache name matches the ZIP version.

---

## Documentation rules

Keep these files updated:

```text
README.md
README_UPDATE.md
AGENTS.md
ROADMAP.md
CHANGELOG.md
```

Recommended responsibilities:

- `README.md`: project overview, installation, architecture, setup and maintenance.
- `README_UPDATE.md`: specific notes for the current release.
- `AGENTS.md`: coding-agent rules.
- `ROADMAP.md`: planned features and version roadmap.
- `CHANGELOG.md`: release history.

---

## Git rules

Prefer small commits.

Suggested commit style:

```text
feat: add smart tags foundation
fix: restore unicode board pieces
chore: bump version to 0.8.6
docs: update installation notes
```

Do not mix unrelated changes in the same commit.

For larger changes, use feature branches:

```text
feature/0.9.0-smart-tags
fix/0.8.6-review-board
```

---

## Roadmap handling

Do not implement roadmap items unless the user explicitly asks.

The current likely roadmap direction is:

- v0.9.0 — Smart Tags
- v1.0.0 — Personal Trainer
- v1.1.0 — Training Center
- v1.2.0 — Openings Lab
- v1.3.0 — Player DNA
- v1.4.0 — Chess.com Sync
- v1.5.0+ — Coach AI

Use `ROADMAP.md` for details.

---

## Communication rules

When finishing a task, summarize:

1. Version generated or changed.
2. Files changed.
3. SQL migrations added or not added.
4. Manual steps required.
5. Anything that could not be verified.

Be direct. Do not claim tests passed unless they were actually run.

---

## High-risk areas

Treat these areas carefully:

- SQL migrations
- service worker cache
- Stockfish integration
- FEN parsing
- move reconstruction
- evaluation normalization
- mate handling
- config files
- cron worker
- PWA cached assets
- review board rendering

When in doubt, inspect the existing implementation first instead of guessing.

---

## Current known stable decision

As of v0.8.5, the app intentionally uses Unicode chess pieces.

Do not reintroduce generated SVG/PNG pieces unless the user explicitly asks for another attempt.
