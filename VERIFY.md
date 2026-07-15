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
9. After the approved version PR is merged to `main`, create the matching tag.
10. After the tag is pushed, create the matching GitHub Release.

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

## ECO Catalog Checks

After applying `sql/migrations/025_changes_1.4.3.sql`, verify the catalog:

```sql
SELECT COUNT(*) AS total_codes FROM eco_codes;

SELECT LEFT(eco_code, 1) AS volume, COUNT(*) AS total
FROM eco_codes
GROUP BY LEFT(eco_code, 1)
ORDER BY volume;

SELECT COUNT(*) AS invalid_codes
FROM eco_codes
WHERE eco_code NOT REGEXP '^[A-E][0-9]{2}$';

SELECT COUNT(*) AS incomplete_labels
FROM eco_codes ec
JOIN opening_families f ON f.family_key = ec.family_key
WHERE ec.opening_name = '' OR f.family_name = '';

SELECT eco_code, family_name, opening_name, variation_name
FROM eco_codes ec
JOIN opening_families f ON f.family_key = ec.family_key
WHERE eco_code IN ('B90', 'C65')
ORDER BY eco_code;
```

Expected results:

- `total_codes`: `500`.
- Each volume `A`, `B`, `C`, `D` and `E`: `100`.
- `invalid_codes`: `0`.
- `incomplete_labels`: `0`.
- `B90`: `Defensa Siciliana` / `Variante Najdorf`.
- `C65`: `Apertura Española` / `Defensa Berlinesa`.

After deploying the PR2 backend resolver, verify its precedence and API payloads:

- A game with an `Opening` PGN tag keeps that imported name.
- A game with ECO `B90` and no opening name resolves to `Defensa Siciliana`.
- The same game exposes `opening_variation_name` as `Variante Najdorf` in `api/games.php?action=list`.
- Openings Lab exposes `family_name`, `opening_name`, `variation_name` and `label_source` for catalog matches.
- Temporarily missing catalog tables do not prevent the games or Openings Lab endpoints from returning a response.
- Openings Lab renders `C65 · Apertura Española` with `Defensa Berlinesa` below it.
- Openings Lab renders `B90 · Defensa Siciliana` with `Variante Najdorf` below it.
- Long names wrap without overlapping KPIs on desktop and mobile.
- ECO links still open only safe HTTP(S) `ECOUrl` values in a new tab.

---

## Board Personalization Checks

After applying `sql/migrations/026_changes_1.4.4.sql`:

- Confirm `users.board_theme` defaults to `green`.
- Confirm profile settings list `Set 1`, `Set 2` and `Set 3`.
- Confirm the available themes are `Verde`, `Marrón`, `Azul` and `Gris`.
- Save every set/theme combination and reload the page.
- Confirm review and training boards preserve the selected combination.
- Confirm move, best-move, selection, legal-target, hint and solution highlights remain distinguishable.
- Confirm all 36 piece-set assets declared in `service-worker.js` exist.

---

## Mobile Summary Card Checks

At a viewport of 640 px or narrower:

- Confirm the four summary cards in `analysis-pending.php`, `training.php` and `openings-lab.php` render as compact rows.
- Confirm each icon appears above its title in the left column.
- Confirm each primary value appears above its supporting detail in the right column.
- Confirm labels and supporting text do not overlap or clip with large values.
- Confirm the home dashboard metric cards are unchanged.
- Confirm the three pages retain their four-column summary layout on desktop.

---

## Training Exercise Mobile Metadata Checks

At a viewport of 560 px or narrower:

- Confirm Intentos, Dificultad and Prioridad render in three equal columns.
- Confirm the labels, values and difficulty bars are centered and do not overlap.
- Confirm the source game remains below the metadata row.
- Confirm the desktop training solver sidebar is unchanged.

---

## Training Interaction Preference Checks

After applying `sql/migrations/028_changes_1.4.7.sql`:

- Confirm `training_exercises.title` exists.
- Confirm `training_exercises.content_version` exists and defaults to `1` for existing rows.
- Run `Actualizar contenido de ejercicios` from `profile.php` and confirm the pending count decreases.
- Confirm attempts, `resolved_at`, `next_due_at` and `last_training_result` are unchanged for updated exercises.
- Confirm updated rows have `content_version = 2`.

After applying `sql/migrations/027_changes_1.4.6.sql`:

- Confirm existing users default to legal-move hints enabled and automatic submission disabled.
- Save each preference combination in `profile.php` and reload the page.
- Confirm selecting an empty square or an opponent piece does not set the origin.
- Confirm selecting another own piece changes the origin selection.
- Confirm an illegal destination is rejected and does not consume an attempt.
- Confirm legal destinations remain enforced when visual hints are disabled.
- Confirm automatic submission registers exactly one attempt after a legal destination is selected.
- Confirm manual mode keeps the Comprobar button enabled after a complete legal move.
- Confirm solving an exercise leaves the piece on its destination and highlights both move squares in green.
- Confirm the solved destination displays a green check above the piece at mobile and desktop sizes.

---

## Tag And Release Checks

Create tags only after the version PR has been approved by the user and merged into `main`.

Use the version tag format:

```text
vX.Y.Z
```

Example for `v0.8.7`:

```powershell
git switch main
git pull --ff-only origin main
git tag -a v0.8.7 -m "Chess Coach v0.8.7"
git push origin v0.8.7
gh release create v0.8.7 --title "Chess Coach v0.8.7" --notes-file README_UPDATE.md
```

Confirm the tag points at the merged `main` commit:

```powershell
git log -1 --oneline "v0.8.7^{}"
```

Confirm the GitHub Release exists:

```powershell
gh release view v0.8.7
```

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
