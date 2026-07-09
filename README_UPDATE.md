# Chess Coach v1.1.6 Update Notes

## Release type

Security, documentation, encoding and architecture hardening release.

This release does not add new product features. It stabilizes the v1.1.x Training Center line before starting v1.2.0 Openings Lab.

## Planned scope

- Align project documentation with the current v1.1.6 stable baseline.
- Audit encoding/mojibake issues where real UTF-8 file content is affected.
- Review authenticated API endpoints and ownership checks.
- Add CSRF protection to authenticated mutating actions.
- Review output escaping, JSON responses and sensitive data exposure.
- Apply only small architecture cleanups that reduce security or maintenance risk.
- Bump the application version and service worker cache for release readiness.

## Changed files

The v1.1.6 documentation, encoding-audit, API-ownership, CSRF-hardening, output-safety and light architecture cleanup PRs have touched:

- `AGENTS.md`
- `CHANGELOG.md`
- `ENCODING_AUDIT.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`
- `analysis-pending.php`
- `api/analyze.php`
- `api/chesscom.php`
- `api/dashboard.php`
- `api/games.php`
- `api/training.php`
- `app.php`
- `assets/js/analysis_queue.js`
- `assets/js/app.js`
- `assets/js/chesscom.js`
- `assets/js/dashboard.js`
- `assets/js/games.js`
- `assets/js/layout.js`
- `assets/js/training.js`
- `config/version.php`
- `games.php`
- `includes/analysis_queue.php`
- `includes/helpers.php`
- `includes/smart_tags.php`
- `includes/stockfish.php`
- `includes/training.php`
- `import-chesscom.php`
- `profile.php`
- `service-worker.js`
- `training.php`

## Encoding audit

The v1.1.6 encoding audit scanned 76 repository text files across Markdown, PHP, JavaScript, CSS and SQL after adding the audit report.

Result:

- No real mojibake markers were found in repository file contents.
- No invalid UTF-8 files were found.
- No code or UI text changes were required for encoding cleanup.

See `ENCODING_AUDIT.md` for the audit scope and database-data note.

## API ownership review

The v1.1.6 API ownership review tightened derived-resource checks without changing visible product behavior:

- Training exercise tags are now fetched through `training_exercises.user_id`, not only by exercise IDs already scoped elsewhere.
- Analysis queue "missing games" checks now scope the latest-analysis subquery by the current user.
- Worker history can still show global worker runs, but game details are joined only when the related analysis belongs to the current user.

## CSRF hardening

Authenticated mutating actions now require a session CSRF token.

Covered in this PR:

- Training Center session, attempt and skip actions.
- Analysis queue mutations, manual worker actions and backfills.
- Manual PGN import.
- Chess.com import.
- Profile password and piece-set forms.

The login form is intentionally not covered in v1.1.6, and the HTTP GET cron worker remains protected by its existing secret token.

Training Center GET endpoints now only read the current active session. Creating or renewing a training session happens through POST actions protected by CSRF.

## Output and JSON safety

The v1.1.6 output-safety pass reduces accidental exposure of internal details:

- Worker summaries no longer return the raw cron token.
- The analysis queue UI displays the worker endpoint with `token=***`.
- Stockfish status no longer returns the configured filesystem path.
- Stockfish status now reports whether the path is configured without exposing it.
- Analysis, Chess.com import, Smart Tags backfill and Training Center backfill errors use public error messages before being stored or returned.

## Light architecture cleanup

The v1.1.6 light architecture cleanup keeps behavior unchanged while reducing duplicated request handling:

- JSON request bodies are now read through `request_json_body()`.
- `request_json_body()` caches the decoded payload per request so multiple action handlers do not re-read `php://input`.
- GET-only endpoints can use `require_get_request()` with the same JSON error shape as POST-only helpers.
- API endpoints for analysis, Chess.com import, manual PGN import, dashboard and Training Center now use those helpers.

## SQL migration

No SQL migration is required for the documentation alignment, encoding audit, API ownership, CSRF hardening, output-safety or light architecture cleanup PRs.

## Service worker

No service worker cache change is required for documentation-only or audit-only PRs.

The output-safety PR updates the cache name to:

```text
chess-coach-v1.1.6-output-json-safety
```

The final v1.1.6 release readiness PR updates the cache name to:

```text
chess-coach-v1.1.6
```

The version in `config/version.php` is now `1.1.6`.

## Local verification commands

```powershell
git diff --check
```

Encoding audit checks used in PR2:

- Node-based scan for common mojibake markers.
- Node-based strict UTF-8 decode check using `TextDecoder`.

For later v1.1.6 PRs that touch PHP or JavaScript, also run:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\changed-file.js
node --check service-worker.js
```

## Manual verification checklist

- Confirm README, AGENTS and ROADMAP agree on the current v1.1.6 stable baseline.
- Confirm README_UPDATE describes v1.1.6 as a hardening release.
- Confirm CHANGELOG contains a v1.1.6 entry.
- Confirm ENCODING_AUDIT documents the mojibake audit result.
- Confirm v1.2.0 Openings Lab remains the next major product line.
- Confirm no production config files or credentials were changed.
- Confirm `config/version.php` and `service-worker.js` match v1.1.6.
