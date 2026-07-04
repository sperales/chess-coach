# Chess Coach v1.0.0 Update Notes

## Release type

Personal Trainer Dashboard UI foundation.

This PR adds the first Personal Trainer Dashboard UI on the home page, consuming the backend payload from PR1.

## Changed files

- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `api/dashboard.php`
- `app.php`
- `assets/css/app.css`
- `assets/js/dashboard.js`
- `assets/js/games.js`
- `includes/dashboard.php`
- `service-worker.js`

## User-facing changes

- The home dashboard now shows:
  - current state
  - top 3 training focus
  - recent summary
  - recent strengths
  - detected patterns
  - recommended games to review
- Existing home KPIs, latest games, quick actions and motivational quote remain available.

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

The service worker asset list now includes:

```text
assets/js/dashboard.js
```

The final v1.0.0 release PR should bump the service worker cache name.

## Local verification performed

PHP syntax lint passed locally with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

JavaScript syntax check passed locally with:

```powershell
node --check assets\js\dashboard.js
```

## Manual verification checklist

- Confirm `api/dashboard.php` returns JSON when logged in.
- Confirm `api/dashboard.php` redirects to login when logged out.
- Confirm the payload includes `overview`, `training_focus`, `strengths`, `recommended_reviews`, `patterns`, `recent_games` and `queue`.
- Confirm the home dashboard renders current state, focus cards, strengths and recommended reviews.
- Confirm the home "Partidas" panel can switch between latest and recommended games.
- Confirm dashboard links to `games.php?tag=...` open the games page with the tag filter applied.
- Confirm no real credentials were committed.
