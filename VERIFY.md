# VERIFY.md

## Purpose

This file documents lightweight verification steps for Chess Coach releases.

The project is designed for shared hosting and intentionally avoids npm, Composer, Docker and build steps. Verification should stay simple and practical.

---

## Required before every release

1. Confirm the working tree only contains intended changes.
2. Run PHP syntax checks.
3. Confirm version files were updated.
4. Confirm the service worker cache name changed.
5. Confirm no real credentials are included.
6. Confirm whether a SQL migration is required.
7. Update `README_UPDATE.md`.
8. Update `CHANGELOG.md`.

---

## PHP Syntax Check

### Windows PowerShell

Run from the repository root:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected result:

```text
No syntax errors detected ...
```

### Unix-Like Shells

Run from the repository root:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

---

## Version Checks

Confirm the application version:

```powershell
Get-Content config\version.php
```

Confirm the service worker cache name:

```powershell
Select-String -Path service-worker.js -Pattern "CACHE_NAME"
```

Both should match the release version.

---

## Credential Check

Real environment files must not be committed:

```text
config/database.php
config/engine.php
config/cron.php
```

Only example files should be tracked:

```text
config/database.example.php
config/engine.example.php
config/cron.example.php
```

---

## SQL Migration Check

Add a SQL migration only when the database schema or stored baseline data changes.

If no database changes are needed, state this clearly in:

```text
README_UPDATE.md
CHANGELOG.md
```

---

## Manual Smoke Checklist

When a deployment is available, verify:

- Login page loads.
- App dashboard loads.
- Import page loads.
- Chess.com import page loads.
- Analysis queue page loads.
- Worker endpoint returns valid JSON when called with the configured token.
- Review page loads for an analyzed game.
- No stale PWA assets appear after hard refresh or PWA reinstall.
