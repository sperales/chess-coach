-- Chess Coach PWA v0.6.1
-- Ejecutar solo al actualizar desde v0.6.0.

ALTER TABLE game_analysis
  MODIFY COLUMN status ENUM('queued','running','done','error') NOT NULL DEFAULT 'queued';

INSERT INTO app_migrations (version, description)
VALUES ('0.6.1', 'Background-like analysis UX and result badges')
ON DUPLICATE KEY UPDATE version = version;
