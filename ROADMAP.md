# ROADMAP.md

## Chess Coach Roadmap

This roadmap describes the planned evolution of Chess Coach from the current stable baseline.

Current stable baseline: **v1.2.7**

The roadmap should be treated as a planning document, not as an implementation contract. Features may be moved, split or refined as the product evolves.

---

## Product vision

Chess Coach should become a personal chess improvement system based on the user's own games.

The long-term goal is to move from simple engine analysis to a full training loop:

```text
play → import → analyze → detect patterns → train → measure progress
```

The product should help answer questions like:

- What mistakes do I repeat most often?
- Which tactical patterns am I missing?
- Which openings give me worse positions?
- Am I improving over time?
- What should I train next?
- Which games best illustrate my weaknesses?
- What would a coach tell me after reviewing my recent games?

---

## Current baseline — v1.2.7

v1.2.7 polishes the import and analysis queue UI while keeping the first Openings Lab release line stable.

It detects and stores opening profiles from the user's own games, groups openings by ECO code and opening name when available, exposes Openings Lab metrics, shows a dedicated `openings-lab.php` page, links opening diagnostics to review moments, filters games by opening and connects opening details with Smart Tags and existing Training Center exercises.

Smart Tags, the Personal Trainer Dashboard, Training Center exercise generation, the interactive solver, hints, session tracking, selectable board piece sets and Openings Lab are now part of the stable product baseline.

The v1.1.6 hardening pass tightened documentation, encoding checks, ownership checks, CSRF coverage, output safety and shared API request helpers before v1.2.0 Openings Lab.

### v0.8.8 review chart baseline

v0.8.8 is a review chart visual refinement built on top of v0.8.7.

It updates the review page evaluation chart to fill the area under the evaluation line in light color, while keeping the chart background and surrounding review UI dark.

### v0.8.7 developer verification baseline

v0.8.7 is a developer verification and documentation release built on top of v0.8.6.

It adds:

- `VERIFY.md`
- documented PHP lint commands for Windows PowerShell and Unix-like shells
- clearer release verification notes

### v0.8.6 repository baseline

v0.8.6 is a repository hygiene and documentation release built on top of the v0.8.5 app baseline.

It adds the deployment structure that should be tracked in Git:

- `.gitignore`
- `config/.htaccess`
- `worker/.htaccess`
- `storage/logs/.gitkeep`
- `README_UPDATE.md`
- `CHANGELOG.md`

### Stable capabilities

- PHP + MariaDB PWA.
- Login and session-based authentication.
- Manual PGN import.
- Chess.com game import.
- Server-side Stockfish analysis.
- Analysis queue.
- HTTP GET cron worker.
- Token-protected worker endpoint.
- Worker history.
- Dashboard with recent games and stats.
- Review page with:
  - board reconstruction
  - local PNG pieces
  - move-by-move review
  - evaluation graph
  - move classification
  - accuracy / ACPL approximation
  - best move hint

### Current technical decisions

- Use local transparent PNG pieces for the review and training boards.
- Keep app compatible with shared hosting.
- Keep Stockfish server-side.
- Keep background analysis based on queue + cron.
- Avoid npm, Composer, Docker and complex build pipelines unless explicitly approved.

---

# Version roadmap

---

## v0.9.0 — Smart Tags

Status: completed in v0.9.0.

### Goal

Automatically classify analyzed games and moves using meaningful chess training tags.

This version should make the system more useful as a learning tool by identifying recurring error types.

### Main features

#### Game-level tags

Automatically tag games based on analysis patterns.

Examples:

- `Opening issue`
- `Tactical blunder`
- `Missed mate`
- `Hanging piece`
- `Endgame mistake`
- `Time trouble candidate`
- `Comeback`
- `Converted advantage`
- `Lost winning position`
- `Early queen activity`
- `King safety issue`
- `Poor piece development`
- `Missed opponent threat`

#### Move-level tags

Add tags to specific moves.

Examples:

- `Blunder`
- `Mistake`
- `Inaccuracy`
- `Missed tactic`
- `Missed checkmate`
- `Allowed checkmate`
- `Piece left en prise`
- `Bad capture`
- `Unprotected king`
- `Opening principle violation`
- `Endgame conversion error`

#### Tag storage

Expected database changes:

- Add a table for game tags.
- Add a table for move tags.
- Keep tags queryable.
- Avoid storing only free text inside existing analysis JSON.

Possible tables:

```text
game_tags
move_tags
```

#### UI features

- Show tags in the game list.
- Show tags inside the review page.
- Filter/search games by tag.
- Add a tag summary block to the dashboard.

#### Dashboard additions

- Most frequent tags.
- Recent recurring mistakes.
- Number of games with tactical blunders.
- Number of games with opening issues.
- Top 3 current improvement areas.

### Acceptance criteria

- Tags are generated automatically after analysis.
- Tags are visible on the game list.
- Tags are visible on review.
- Tags can be filtered.
- Existing analyzed games can be backfilled or re-tagged safely.

---

## v1.0.0 — Personal Trainer Dashboard

Status: completed in Chess Coach v1.0.0.

### Goal

Turn the dashboard into a real personal training cockpit.

This version should help the user know what to focus on next.

### Main features

#### Improved dashboard

Add higher-level training indicators:

- Current form.
- Recent accuracy trend.
- Average centipawn loss trend.
- Blunders per game.
- Mistakes per game.
- Most common error type.
- Best recent game.
- Worst recent game.
- Most instructive recent game.

#### Weekly summary

Generate a weekly recap based on analyzed games.

Example output:

```text
This week you played 8 games.
Your main recurring issue was leaving pieces undefended.
Your best game was against X.
Your most instructive mistake was move 14 in game Y.
Recommended focus: tactics involving loose pieces.
```

#### Main weakness detection

Automatically detect the main current weakness from recent games.

Examples:

- Tactical vision.
- Opening development.
- King safety.
- Calculation depth.
- Endgame conversion.
- Defensive awareness.
- Piece coordination.

#### Training recommendation

Recommend what to train next based on the user's own games.

Examples:

- Practice forks.
- Practice mate threats against your king.
- Review opening principles.
- Practice simple rook endgames.
- Solve puzzles from positions similar to your mistakes.

### Acceptance criteria

- Dashboard clearly shows what the user should train next.
- Weekly summary is understandable without engine jargon.
- Weakness detection is based on real analyzed data.
- No external AI dependency is required unless explicitly approved.

---

## v1.1.0 — Training Center

Status: completed and refined across the v1.1.x line.

### Goal

Create exercises from the user's own games.

This version should move Chess Coach from passive review to active training.

### Main features

#### Exercise generation

Generate exercises from mistakes and missed opportunities.

Exercise types:

- Find the best move.
- Avoid the blunder.
- Spot the opponent threat.
- Find the tactic.
- Convert the advantage.
- Defend the position.
- Find the mate.
- Choose the correct recapture.

#### Exercise page

Create a new Training Center page.

Possible menu label:

```text
Entrenamiento
```

Features:

- Daily exercise list.
- Exercises generated from personal games.
- Board position.
- User move input.
- Feedback after answer.
- Link back to the original game.
- Difficulty indicator.

#### Exercise tracking

Store:

- exercise source game
- source move
- FEN
- best move
- user answer
- result
- attempts
- completion date
- exercise type

Possible table:

```text
training_exercises
```

#### Spaced repetition foundation

Add basic repeat logic:

- Repeat failed exercises.
- Reduce frequency for solved exercises.
- Highlight exercises from recurring weakness tags.

### Acceptance criteria

- User can solve exercises generated from their own games.
- Exercises are linked to original review positions.
- Solved/failed status is persisted.
- Training page works on mobile.

---

## v1.2.0 — Openings Lab

Status: completed in Chess Coach v1.2.0.

### Goal

Help the user understand which openings are working and where early mistakes happen.

This version should avoid deep memorization and focus on practical improvement.

### Main features

#### Opening detection

Detect opening names or at least classify early move patterns.

Options:

- Basic ECO lookup if feasible.
- Lightweight internal opening classifier.
- Store first N moves as opening signature.

#### Opening statistics

Show:

- Games by opening.
- Win/draw/loss by opening.
- Average accuracy by opening.
- Average evaluation after move 10.
- Blunders in first 10 moves.
- Common early mistake positions.

#### Opening review page

For each opening:

- Number of games.
- Results.
- Best game.
- Worst game.
- Common mistake.
- Recommended principle.
- Example games from the user.

#### Practical opening guidance

Instead of memorized lines, focus on:

- development
- king safety
- central control
- avoiding repeated early mistakes
- understanding typical plans

### Acceptance criteria

- User can see which openings perform better or worse.
- User can identify recurring opening mistakes.
- Opening guidance remains practical and non-memorization-heavy.

---

## v1.3.0 — Player DNA

### Goal

Create a profile of the user's chess style, strengths and weaknesses.

This version should summarize how the user tends to play.

### Main features

#### Player profile

Generate a personal chess profile based on analyzed games.

Possible sections:

- Tactical awareness.
- Opening discipline.
- Calculation.
- Endgame skill.
- Risk level.
- Conversion ability.
- Defensive awareness.
- Consistency.
- Resilience after mistakes.

#### Strengths and weaknesses

Show:

- Top 3 strengths.
- Top 3 weaknesses.
- Biggest improvement since last period.
- Most persistent problem.

#### Style indicators

Possible indicators:

- Aggressive vs solid.
- Tactical vs positional.
- Fast simplifier vs tension keeper.
- Good starter vs good finisher.
- Stable under pressure vs collapses after blunder.

#### Historical comparison

Compare:

- last 10 games vs previous 10 games
- this month vs previous month
- recent accuracy vs baseline

### Acceptance criteria

- The player profile is based on stored analysis data.
- It is written in plain language.
- It avoids pretending to be more precise than the data supports.
- It helps decide what to train next.

---

## v1.4.0 — Chess.com Sync

### Goal

Move from manual import to automatic recurring synchronization.

This version should reduce manual work and keep the training database current.

### Main features

#### Sync settings

Allow configuring:

- Chess.com username.
- Import frequency.
- Starting date.
- Whether to auto-analyze imported games.
- Maximum games per sync batch.

#### Duplicate-safe import

Ensure games are not imported twice.

Use stable identifiers when available.

#### Automatic sync

Use cron-compatible HTTP GET worker.

Possible endpoint:

```text
worker/sync_chesscom.php?token=...
```

#### Sync history

Track:

- sync start time
- sync end time
- games found
- games imported
- duplicates skipped
- errors
- next expected sync

Possible table:

```text
chesscom_sync_runs
```

#### Auto-analysis

Imported games should optionally enter the analysis queue automatically.

### Acceptance criteria

- Chess.com games can be imported automatically.
- Duplicate games are skipped.
- Sync status is visible.
- Failed syncs are logged clearly.
- Auto-analysis can be enabled or disabled.

---

## v1.5.0 — Coach AI Layer

### Goal

Add higher-level coaching explanations on top of the engine and stored analysis.

This version should be considered only after the data model is mature enough.

### Main features

#### Coaching summaries

Generate plain-language summaries of games.

Examples:

- What went wrong?
- What was the turning point?
- What should I learn from this game?
- What should I train next?

#### Weekly coaching report

Generate a weekly training report.

Possible sections:

- Games played.
- Best game.
- Worst mistake.
- Recurring weakness.
- Training focus.
- Recommended exercises.
- Encouragement / mindset note.

#### Review explanations

For key moves:

- Explain why the move was bad.
- Explain what the best move achieved.
- Explain the opponent threat.
- Suggest a simpler thinking rule.

#### AI safety / quality constraints

The AI should not replace Stockfish calculation.

Use Stockfish and stored data as the factual base.

The AI layer should explain, summarize and coach — not invent chess analysis.

### Acceptance criteria

- AI explanations are grounded in stored analysis.
- The user can distinguish engine facts from coaching interpretation.
- Reports are useful and not generic.
- No sensitive credentials or API keys are committed.

---

# Future ideas beyond v1.5.0

These are not committed versions yet.

## Mobile-first training mode

- Daily training card.
- One position at a time.
- Swipe/next flow.
- Fast feedback.

## DGT board integration support

- Export positions/FENs for physical board replay.
- Training list printable/exportable.
- Better workflow for reviewing games on DGT board.

## ChessBase export

- Export selected games with annotations.
- Export PGN with tags and comments.
- Export training positions.

## Multi-user support

- Multiple users.
- User-specific dashboards.
- User-specific Chess.com accounts.
- Admin panel.

## Tournament / competition preparation mode

- Select preparation period.
- Track training consistency.
- Identify urgent weaknesses.
- Generate focused training pack.

## Opening repertoire builder

- Suggest practical repertoire based on games.
- Identify move orders causing trouble.
- Recommend simple plans.

## Endgame module

- Detect repeated endgame types.
- Generate basic endgame practice.
- Track conversion errors.

---

# Prioritization principles

When deciding what to build next, prefer features that:

1. Help the user learn from their own games.
2. Reduce manual effort.
3. Improve review quality.
4. Create repeatable training.
5. Preserve shared-hosting compatibility.
6. Avoid unnecessary complexity.
7. Keep the product stable.

Avoid features that:

1. Add complexity without improving training.
2. Require fragile infrastructure.
3. Depend on unavailable hosting features.
4. Create maintenance burden too early.
5. Pretend to provide coaching insight without reliable data.

---

# Technical roadmap notes

## Likely database additions

Future versions will probably need tables for:

```text
game_tags
move_tags
training_exercises
training_attempts
weekly_reports
opening_stats
player_profile_snapshots
chesscom_sync_runs
```

Do not create these tables before the corresponding feature needs them.

---

## Likely UI additions

Future menu items may include:

```text
Entrenamiento
Aperturas
Mi perfil
Informes
Sincronización
```

Do not add empty menu items before pages exist.

---

## Risk areas

High-risk areas to handle carefully:

- SQL migrations.
- PWA service worker cache.
- Stockfish execution.
- Mate evaluation.
- FEN reconstruction.
- Move parsing.
- Backfilling existing analyzed games.
- Long-running analysis jobs.
- Shared-hosting timeouts.
- Cron execution limits.

---

# Definition of done for each roadmap version

Each version should be considered done only when:

1. The feature works end to end.
2. The version number is updated.
3. Service worker cache name is updated.
4. SQL migrations are included if needed.
5. Existing features still work.
6. PHP syntax checks pass.
7. README_UPDATE.md explains the release.
8. No real credentials are included.
9. Manual deployment instructions are clear.
10. The user can validate the feature in the deployed app.
