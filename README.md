# Chess Coach

Chess Coach is a personal chess training PWA designed to help a player improve through their own games.

It allows importing PGNs or Chess.com games, storing them in MariaDB, running Stockfish analysis on the server, reviewing key moments move by move, and tracking improvement over time.

The goal is not to replace a chess engine. The goal is to create a practical training loop:

```text
play → import → analyze → review → learn → improve
```

---

## Current status

Current stable baseline: **v0.8.5**

The project is currently a PHP + MariaDB PWA designed for shared hosting. It does not require npm, Node.js, Composer, Docker, or a build step.

---

## Tech stack

- PHP
- MariaDB 10.11+
- Vanilla JavaScript
- HTML/CSS
- PWA service worker
- Stockfish running server-side
- Chess.com public game import
- Shared hosting compatible

---

## Main features implemented

### Authentication

- Login page
- Session-based authentication
- User table in MariaDB
- Protected app pages
- Profile page
- Password change flow

### Game storage

- MariaDB-backed game persistence
- Manual PGN import
- Chess.com import
- Duplicate detection
- Game metadata:
  - white player
  - black player
  - result
  - date
  - event
  - source
  - user result

### Home dashboard

- Dark mode UI
- Approved Chess Coach branding
- Header with:
  - logo
  - logged-in user
  - ELO
  - version
  - hamburger menu
- Recent games list
- Pagination
- Global stats
- Last 10 days stats
- Average games per day
- Quick actions

### Import page

Menu option: **Importar partidas**

Includes:

- Manual PGN import
- Chess.com import
- Automatic queueing for analysis

### Analysis queue

Menu option: **Análisis pendientes**

Includes:

- Pending analysis queue
- Server-side analysis process
- Worker endpoint
- Cron-compatible HTTP GET worker
- Worker token protection
- Worker execution history
- Worker status panel
- Average time per game
- Consecutive error count
- Manual “execute now” option
- Retry failed jobs
- Cancel pending jobs

### Stockfish integration

- Server-side Stockfish analysis
- Configurable Stockfish path
- Configurable depth / movetime / max halfmoves
- Hosting-compatible approach
- No external AI API dependency for chess analysis

### Review page

- Game review page
- Board reconstruction from FEN
- Move-by-move review
- Evaluation graph
- Approximate accuracy
- ACPL
- Move classification
- Best move hint
- Unicode chess pieces restored in v0.8.5 for stability

---

## Project structure

```text
chess-coach/
  api/
    analyze.php
    chesscom.php
    games.php
    review.php

  assets/
    css/
      app.css
    icons/
      favicon.ico
      icon-192.png
      icon-512.png
      logo-approved.png
    js/
      analysis_queue.js
      app.js
      chesscom.js
      layout.js
      review.js

  config/
    .htaccess
    app.php
    cron.example.php
    database.example.php
    engine.example.php
    security.php
    version.php

  includes/
    analysis_queue.php
    auth.php
    chess_server.php
    chesscom.php
    config.php
    db.php
    helpers.php
    pgn.php
    session.php
    stockfish.php

  sql/
    install.sql
    migrations/

  storage/
    logs/
      .gitkeep

  worker/
    .htaccess
    analyze_queue.php

  index.php
  app.php
  import-chesscom.php
  analysis-pending.php
  review.php
  profile.php
  logout.php
  manifest.webmanifest
  service-worker.js
```

---

## Files that must not be committed with real credentials

Do **not** commit real production versions of:

```text
config/database.php
config/engine.php
config/cron.php
```

Only commit example files:

```text
config/database.example.php
config/engine.example.php
config/cron.example.php
```

Recommended `.gitignore`:

```gitignore
config/database.php
config/engine.php
config/cron.php

storage/logs/*
!storage/logs/.gitkeep

.DS_Store
Thumbs.db
```

---

## Configuration files

### Database config

Create:

```text
config/database.php
```

Based on:

```text
config/database.example.php
```

This file should contain the real MariaDB connection details for the hosting environment.

Never commit the real file.

---

### Engine config

Create:

```text
config/engine.php
```

Based on:

```text
config/engine.example.php
```

It should define:

- Stockfish binary path
- depth
- movetime
- max halfmoves
- worker batch size

Example:

```php
return [
  'stockfish_path' => '/path/to/stockfish',
  'depth' => 10,
  'movetime_ms' => 800,
  'max_halfmoves' => 90,
  'worker_batch_size' => 1,
];
```

Never commit the real file.

---

### Cron config

Create:

```text
config/cron.php
```

Based on:

```text
config/cron.example.php
```

It should contain:

- worker token
- expected cron interval in minutes

Example:

```php
return [
  'worker_token' => 'replace-with-secret-token',
  'expected_interval_minutes' => 360,
];
```

Never commit the real file.

---

## Installation

### 1. Upload files

Upload the project files to the hosting directory.

Example:

```text
public_html/chess/
```

or:

```text
htdocs/chess/
```

---

### 2. Create configuration files

Copy the example files:

```text
config/database.example.php → config/database.php
config/engine.example.php   → config/engine.php
config/cron.example.php     → config/cron.php
```

Then fill the real values on the server.

---

### 3. Import database schema

For a fresh installation, import:

```text
sql/install.sql
```

For an existing installation, apply migrations in order from the current installed version.

---

### 4. Upload Stockfish

Upload a Linux-compatible Stockfish binary to the server.

Known working setup so far:

```text
Stockfish 16 Linux 64-bit
```

Then configure the path in:

```text
config/engine.php
```

Make sure the binary has execution permissions if the hosting requires it.

---

### 5. Configure cron

The hosting currently supports HTTP GET cron jobs.

Use:

```text
https://your-domain.com/chess/worker/analyze_queue.php?token=YOUR_TOKEN
```

The token must match the one configured in:

```text
config/cron.php
```

Recommended interval:

```text
Every 6 hours
```

or the most frequent interval allowed by the hosting.

---

## Updating to a new version

For each release:

1. Upload all changed files.
2. Do **not** overwrite:
   - `config/database.php`
   - `config/engine.php`
   - `config/cron.php`
3. Run the new SQL migration if one exists.
4. Confirm `config/version.php` has the expected version.
5. Confirm `service-worker.js` cache name was updated.
6. Hard refresh the browser or reinstall the PWA if cached assets appear stale.

---

## Versioning rules

Each version should update:

```text
config/version.php
service-worker.js
README_UPDATE.md
```

A SQL migration should be added only when needed.

Migration naming convention:

```text
sql/migrations/NNN_changes_X.Y.Z.sql
```

Example:

```text
sql/migrations/016_changes_0.8.5.sql
```

---

## Development constraints

This project is designed for shared hosting.

Avoid introducing:

- npm
- Node.js build steps
- Composer dependencies
- Docker-only workflows
- background daemons that require shell access
- services that cannot run on shared hosting

Prefer:

- plain PHP
- vanilla JS
- progressive enhancement
- simple SQL migrations
- local assets
- server-compatible workers
- HTTP GET cron support

---

## Testing checklist before release

Before producing a new version, run:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Also verify manually:

- Login works
- Home loads
- PGN import works
- Chess.com import works
- Games list loads
- Queue page loads
- Worker endpoint returns valid JSON
- Stockfish is available
- Review page loads for analyzed games
- Service worker cache name changed
- No real credentials were committed

---

## Important rules for Codex

Codex should follow these project rules:

1. Do not overwrite real config files:
   - `config/database.php`
   - `config/engine.php`
   - `config/cron.php`

2. Keep example files updated when configuration changes:
   - `config/database.example.php`
   - `config/engine.example.php`
   - `config/cron.example.php`

3. Always update:
   - `config/version.php`
   - `service-worker.js`
   - `README_UPDATE.md`

4. Add a SQL migration only when required.

5. If a SQL migration is added, make sure:
   - column counts match
   - syntax works with MariaDB
   - the migration is idempotent when possible
   - `app_migrations` is updated correctly

6. Do not introduce external package managers unless explicitly approved.

7. Keep the application compatible with shared hosting.

8. Avoid relying on long-running browser sessions for background analysis.

9. Prefer worker + cron architecture for background processing.

10. Summarize changed files after every implementation.

---

## Current roadmap snapshot

The detailed roadmap should live in a separate file:

```text
ROADMAP.md
```

The README should only contain a short roadmap snapshot so it stays useful as installation and project documentation.

Current planned direction:

### v0.9.0 — Smart Tags

- Automatic game tags
- Tactical/strategic pattern detection
- Search games by tag
- Stats by recurring error type

### v1.0.0 — Personal Trainer

- Stronger dashboard
- Weekly summary
- Main weakness detection
- Training recommendation

### v1.1.0 — Training Center

- Exercises generated from personal games
- Pattern-based practice
- Training history

### v1.2.0 — Openings Lab

- Opening stats
- Win rate by opening
- Common mistakes by opening

### v1.3.0 — Player DNA

- Player profile
- Strengths and weaknesses
- Behavioural insights

### v1.4.0 — Chess.com Sync

- Automatic periodic import
- Duplicate-safe sync
- Auto-analysis after import

### v1.5.0+ — Coach AI

- Higher-level coaching explanations
- Weekly training plan
- Long-term progress interpretation

---

## Recommended additional repository files

The repository should also include:

```text
AGENTS.md
ROADMAP.md
CHANGELOG.md
```

Suggested purpose:

- `AGENTS.md`: strict instructions for Codex and other coding agents
- `ROADMAP.md`: product roadmap and planned versions
- `CHANGELOG.md`: release history and user-facing changes

---

## Current production notes

- The app is deployed manually to shared hosting.
- The worker is executed through HTTP GET cron.
- Stockfish runs server-side.
- The app currently uses Unicode chess pieces in the review board for stability.
- Real credentials must stay outside GitHub.
