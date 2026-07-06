-- Chess Coach v1.0.1
-- Optional ECO/opening metadata for imported games.

ALTER TABLE games
  ADD COLUMN IF NOT EXISTS eco_code VARCHAR(10) DEFAULT NULL AFTER site,
  ADD COLUMN IF NOT EXISTS opening_name VARCHAR(255) DEFAULT NULL AFTER eco_code,
  ADD COLUMN IF NOT EXISTS eco_url VARCHAR(500) DEFAULT NULL AFTER opening_name,
  ADD INDEX IF NOT EXISTS idx_games_opening (eco_code);

INSERT INTO app_migrations (version, description)
VALUES ('1.0.1', 'Store optional ECO, opening and ECO URL metadata for games')
ON DUPLICATE KEY UPDATE description = VALUES(description);
