-- Chess Coach v1.4.4
-- User-selectable board color theme.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS board_theme VARCHAR(20) NOT NULL DEFAULT 'green' AFTER piece_set;

UPDATE users
SET board_theme = 'green'
WHERE board_theme IS NULL OR board_theme NOT IN ('green', 'brown', 'blue', 'gray');

INSERT INTO app_migrations (version, description)
VALUES ('1.4.4', 'User-selectable board color themes')
ON DUPLICATE KEY UPDATE description = VALUES(description);
