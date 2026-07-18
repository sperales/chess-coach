# ROADMAP.md

## Chess Coach Roadmap

This roadmap describes the planned evolution of Chess Coach from the current stable baseline.

Current stable baseline: **v1.4.14**

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

## Current baseline — v1.4.14

v1.4.14 keeps completed daily and weekly objectives visible throughout their active period and recovers objectives hidden by the earlier refresh-order bug. It retains the SAN review presentation delivered in v1.4.13.

It keeps Player DNA, Openings Lab and the Training Center as stable capabilities, including the autonomous 500-code ECO reference catalog, Spanish opening and representative variation labels, PGN-first metadata resolution and clearer opening identities without runtime APIs.

The Training Experience now exposes a personal plan in Home and Training, keeps the streak and smart repetition system, and tracks a game review as completed only after enough distinct plies have actually been visited. This creates a deterministic data layer that a future AI Coach can interpret without inventing chess facts.

Smart Tags, the Personal Trainer Dashboard, Training Center exercise generation, the interactive solver, hints, internal session tracking, selectable board piece sets, Openings Lab, Player DNA and Training Experience are now part of the stable product baseline.

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
- Player DNA profile, snapshots and home summary.
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

## Completed in v1.3.0 — Player DNA

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

## v1.4.0 — Training Experience

Status: completed in v1.4.0.

### Goal

Make training feel continuous, personal and worth returning to.

This version should build on the existing Training Center and help the user see daily progress, repeat the right exercises and maintain a professional training habit without turning Chess Coach into a game.

The guiding principle is:

```text
train today → see progress → know what to repeat → come back tomorrow
```

Gamify the user's progress, not the application.

### Main features

#### Training goals

Allow the user to configure personal training goals from the profile page.

Possible goals:

- Daily exercise target.
- Daily time target.
- Weekly training target.

Training pages may show a compact link to adjust goals, but the profile page should remain the main settings area.

#### Training streak

Show a clear but restrained streak indicator.

Rules:

- A day with at least one non-skipped exercise counts as a training day.
- The main streak should be based on meeting the configured daily goal.
- Training activity and goal completion should be shown as related but distinct concepts.
- The home dashboard should show the streak visually.
- A compact streak indicator may also appear in the header.

The tone should be encouraging, not punitive. Breaking a streak should invite the user to resume training instead of creating pressure.

#### Smart repetition

Introduce lightweight repetition logic for exercises.

Rules:

- Failed exercises should reappear.
- Only a small number of older failed exercises should be surfaced each day.
- Skipped exercises should not count as training activity.
- Solved exercises may reappear, but with very low frequency.
- Exercises should explain why they are recommended when possible, for example `Fallado el 2026-07-10`.

The goal is to reinforce learning without flooding the user.

#### Training progress

Show progress by meaningful chess areas.

Possible areas:

- Tactical vision.
- Defense.
- Endgames.
- Opening discipline.
- Conversion / precision.

Progress should be based on existing exercises, attempts, Smart Tags, Player DNA and recent training results.

#### Training feedback

After solving, failing or exhausting an exercise, provide clear feedback:

- What happened.
- Whether it counts toward today's goal.
- Whether the exercise will return later.
- What the next recommended action is.

The user should not need to manage sessions manually. Sessions may remain an internal data model for grouping activity, but the UI should speak in terms of training, daily progress, streaks and goals.

#### Milestones

Add small professional milestones related to consistency and improvement.

Examples:

- First complete training day.
- Daily goal completed.
- Weekly goal completed.
- Three goal days in a row.
- A repeated exercise solved after previous failure.

Avoid points, coins, XP, levels, chests, rankings or anything that makes the app feel like a game.

### Acceptance criteria

- The user can configure a training goal.
- The home dashboard shows training progress and streak state clearly.
- The header may show a compact streak indicator.
- The Training Center shows what to train today.
- Failed exercises can return in a controlled way.
- Skipped exercises do not count as daily training activity.
- The UI no longer requires the user to understand or manage sessions.
- The implementation remains compatible with shared hosting.

---

## v1.4.3 — Spanish ECO Catalog

Status: completed in v1.4.3.

### Goal

Make Openings Lab understandable without requiring the user to know ECO codes.

### Delivered

- Autonomous local catalog for all 500 codes from `A00` to `E99`.
- Stable opening-family grouping for future statistics and recommendations.
- Canonical Spanish opening and representative variation labels.
- Imported PGN opening metadata kept as the highest-priority source.
- Friendly `ECO · opening` hierarchy with the variation on a secondary line.
- No runtime API dependency and no rewrite of existing opening profiles.

### Future boundary

Exact move-sequence classification, opening trees and repertoire management remain future work. An ECO category may contain multiple named lines and must not be presented as exact-line detection.

---

## v1.4.11 — SAN notation for training exercises

Status: completed in v1.4.11.

### Goal

Make training attempts and solutions readable as chess notation without changing the UCI contracts used by Stockfish and exercise validation.

### Delivered

- Central server-side conversion from UCI to SAN using the FEN immediately before the move.
- SAN presentation for attempts, revealed solutions and correct moves.
- Coverage for captures, disambiguation, check, mate, castling, promotion and en passant.
- Safe coordinate fallback for malformed historical positions or illegal moves.
- Dependency-free notation fixtures suitable for shared hosting.

### Future boundary

UCI remains the stored and validated format. Broader SAN presentation outside training can reuse the helper in later versions without changing analysis data.

---

## v1.4.12 — Personal training plan and performance tracking

Status: completed in v1.4.12.

### Goal

Make progress visible and turn the Training Center into a clear plan of work without introducing coins, XP or game-like rewards.

### Delivered

- Persistent solve runs with difficulty, attempts, hint usage and resolution quality.
- Three progressive hints that reveal the idea, the piece and the action/region without exposing the destination square.
- Índice de rendimiento from a moving window of recent exercises and analyzed games, so it can rise or fall with performance.
- Autonomía metric focused on solving exercises without assistance, calibrated after a minimum useful sample.
- Deterministic daily and weekly goals with measurable progress and direct links to the recommended action.
- Review completion tracking based on visiting 17 distinct plies, or every ply in a shorter game.
- Personal-plan UI in Home and Training, compact header visibility, profile context and review progress feedback.
- A deterministic data foundation ready to be consumed by the future AI Coach.

### Data boundary

Historical exercise attempts made before the v1.4.12 tracking tables were active are not reconstructed automatically because their hint level and solve-run context cannot be inferred reliably. New activity is tracked from deployment onward; recent analyzed games can be synchronized safely.

### Future boundary

The plan remains rule-based. Natural-language interpretation, conversational coaching and AI-generated objectives belong to v1.5.x and later versions.

---

## v1.4.13 — SAN notation in game review

Status: completed in v1.4.13.

### Goal

Make Stockfish alternatives in game reviews readable as standard chess notation.

### Delivered

- SAN conversion for the best-move text shown in move comments and by the `Mejor` action.
- Conversion from the position immediately before the reviewed move.
- UCI retained for Stockfish, board highlights and internal validation.
- Safe coordinate fallback when historical position data cannot produce legal SAN.

---

## v1.4.14 — Completed training goal persistence

Status: completed in v1.4.14.

### Goal

Keep the personal training plan stable after the user completes an objective.

### Delivered

- Goal progress is evaluated before obsolete rule-based candidates are dismissed.
- Completed daily and weekly goals remain visible until their period ends.
- Goals already hidden by the previous refresh order are recovered automatically when their stored progress proves completion.
- Completed goals cannot return to pending during the same active period.

---

## v1.5.0 — AI Coach MVP

### Goal

Add a first AI-assisted coaching layer on top of Stockfish, Smart Tags, Player DNA and the Training Experience data.

This version should interpret already stored facts. It should not replace Stockfish or invent chess analysis.

### Main features

#### Coaching summaries

Generate plain-language summaries of games.

Examples:

- What went wrong?
- What was the turning point?
- What should I learn from this game?
- What should I train next?

#### Weekly coaching report

Generate a training report from real stored activity.

Possible sections:

- Games played.
- Training days.
- Daily goals completed.
- Exercises solved.
- Exercises to repeat.
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

## v1.5.1 — AI Coach Plus

### Goal

Improve the first AI Coach with better context, richer reports and more useful training guidance.

### Main features

- Better weekly and recent-period summaries.
- More precise training recommendations.
- Coach notes based on Player DNA trends.
- Smarter use of repeated exercises and missed goals.
- Safer prompt/context construction.
- Clear distinction between facts, interpretation and advice.

### Acceptance criteria

- Coach advice is more specific than generic encouragement.
- Recommendations are traceable to games, tags, exercises or training history.
- The feature remains optional and does not block the non-AI workflow.

---

## v1.6.0 — Conversación con el entrenador

### Goal

Allow the user to ask questions about their own games, training progress and recurring weaknesses.

### Main features

- Chat-style coaching interface.
- Questions grounded in stored game, review, Player DNA and training data.
- Links back to relevant games, exercises and openings.
- Guardrails to avoid unsupported chess claims.

### Acceptance criteria

- The conversation is useful because it knows the user's training history.
- It does not require changing the core analysis pipeline.
- It remains compatible with manual deployment and shared hosting constraints.

---

## v1.7.0 — Automation

### Goal

Reduce manual maintenance by adding safe automated processes once the training loop and coaching model are mature.

### Main features

#### Chess.com Sync

Move from manual Chess.com import to automatic recurring synchronization.

Features:

- Chess.com username settings.
- Import frequency.
- Starting date.
- Duplicate-safe import.
- Optional auto-analysis.
- Cron-compatible HTTP GET sync worker.
- Sync history and error logging.

Possible table:

```text
chesscom_sync_runs
```

#### Automated maintenance

Possible future processes:

- Scheduled retagging.
- Training repetition queue maintenance.
- Opening profile recalculation.
- Player DNA refresh.
- AI report generation, if enabled.

### Acceptance criteria

- Chess.com sync does not create duplicates.
- Automated workers remain token-protected.
- All automation remains compatible with shared hosting HTTP GET cron.
- Manual import and manual analysis continue working.

---

# v2.0.0 — Full Personal Chess Coach

## Goal

Bring together analysis, training, repetition, Player DNA, openings, automation and coaching into a cohesive personal chess improvement system.

The product should feel like a lightweight personal trainer for chess, not just a database of analyzed games.

---

# Future ideas beyond v2.0.0

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
training_goal_settings
training_milestones
coach_reports
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
Entrenador
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
