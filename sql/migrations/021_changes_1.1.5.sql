-- v1.1.5 - User-selectable chess piece sets.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS piece_set VARCHAR(80) NOT NULL DEFAULT 'Set 1' AFTER password_hash;

UPDATE users
SET piece_set = 'Set 1'
WHERE piece_set IS NULL OR piece_set = '';

INSERT INTO app_migrations (version, description)
VALUES ('1.1.5', 'User-selectable chess piece sets')
ON DUPLICATE KEY UPDATE description = VALUES(description);

