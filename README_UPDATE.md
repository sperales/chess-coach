# Chess Coach v1.1.6 Update Notes

## Release type

Security, documentation, encoding and architecture hardening release.

This release does not add new product features. It stabilizes the v1.1.x Training Center line before starting v1.2.0 Openings Lab.

## Planned scope

- Align project documentation with the current v1.1.5 stable baseline and the v1.1.6 hardening release.
- Audit and clean up encoding/mojibake issues where real UTF-8 file content is affected.
- Review authenticated API endpoints and ownership checks.
- Add CSRF protection to authenticated mutating actions.
- Review output escaping, JSON responses and sensitive data exposure.
- Apply only small architecture cleanups that reduce security or maintenance risk.
- Bump the application version and service worker cache at release readiness.

## Changed files

This PR starts the v1.1.6 release documentation.

- `AGENTS.md`
- `CHANGELOG.md`
- `README.md`
- `README_UPDATE.md`
- `ROADMAP.md`

## SQL migration

No SQL migration is required for the documentation alignment PR.

## Service worker

No service worker cache change is required for this documentation-only PR.

The final v1.1.6 release readiness PR will update `service-worker.js`.

## Local verification commands

```powershell
git diff --check
```

For later v1.1.6 PRs that touch PHP or JavaScript, also run:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\changed-file.js
node --check service-worker.js
```

## Manual verification checklist

- Confirm README, AGENTS and ROADMAP agree on the current v1.1.5 stable baseline.
- Confirm README_UPDATE describes v1.1.6 as a hardening release.
- Confirm CHANGELOG contains a v1.1.6 entry.
- Confirm v1.2.0 Openings Lab remains the next major product line.
- Confirm no production config files or credentials were changed.
