# Chess Coach v1.0.0 Update Notes

## Release type

Personal Trainer Dashboard backend foundation.

This PR adds the backend foundation for the Personal Trainer Dashboard. It does not change the home UI yet.

## Changed files

- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `api/dashboard.php`
- `includes/dashboard.php`

## User-facing changes

- No visible UI change yet.
- A new authenticated dashboard API is available for the upcoming Personal Trainer home UI.

## Deployment notes

Upload the changed files to the server.

Do not overwrite real environment config files:

```text
config/database.php
config/engine.php
config/cron.php
```

No real config files changed in this release.

## SQL migration

No SQL migration is required for this PR.

## Service worker

No service worker asset list change is required for this backend-only PR.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

No JavaScript files changed in this release.

## Manual verification checklist

- Confirm `api/dashboard.php` returns JSON when logged in.
- Confirm `api/dashboard.php` redirects to login when logged out.
- Confirm the payload includes `overview`, `training_focus`, `strengths`, `recommended_reviews`, `patterns`, `recent_games` and `queue`.
- Confirm no real credentials were committed.
