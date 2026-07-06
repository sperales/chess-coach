# Chess Coach v1.0.3 Update Notes

## Release type

Stockfish analysis performance release.

This release optimizes server-side Stockfish analysis without changing hosting requirements, the HTTP GET cron worker, analysis classification thresholds or review calculations.

## Changed files

- `AGENTS.md`
- `CHANGELOG.md`
- `README_UPDATE.md`
- `config/engine.example.php`
- `config/version.php`
- `includes/analysis_queue.php`
- `includes/stockfish.php`
- `service-worker.js`

## User-facing changes

- Analysis should complete faster because each game now reuses one Stockfish process instead of starting Stockfish for every evaluated position.
- Analysis avoids duplicate FEN evaluations inside the same game.
- Stockfish now receives explicit UCI options for `Threads` and `Hash`.
- `config/version.php` is bumped to `1.0.3`.
- The PWA service worker cache name is bumped to `chess-coach-v1.0.3`.

## Deployment notes

Upload the changed files to the server.

Do not overwrite real environment config files:

```text
config/database.php
config/engine.php
config/cron.php
```

No real config files changed in this release.

## Config notes

`config/engine.example.php` now documents:

```php
'threads' => 1,
'hash_mb' => 32,
```

If you want the production `config/engine.php` to use those values explicitly, add them manually to the real server config. If omitted, the code defaults to `threads = 1` and `hash_mb = 32`.

## SQL migration

No SQL migration is required for this release.

## Service worker

The service worker cache name is now:

```text
chess-coach-v1.0.3
```

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Manual verification checklist

- Confirm `worker/analyze_queue.php?token=...` still runs from HTTP cron.
- Confirm a queued game moves from `queued` to `running` to `done`.
- Confirm review still loads the completed analysis.
- Confirm move classifications, ACPL and accuracy are present after analysis.
- Confirm Smart Tags are still generated after analysis.
- Compare worker duration against a previous similar game if possible.
- Confirm the header/footer version displays `1.0.3`.
- Confirm the service worker cache name is `chess-coach-v1.0.3`.
- Confirm no real credentials were committed.
