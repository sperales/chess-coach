-- Chess Coach v0.7.0
-- Cola automática de análisis + worker en segundo plano

ALTER TABLE game_analysis
  MODIFY COLUMN status ENUM('queued','running','done','error','cancelled') NOT NULL DEFAULT 'queued';

ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS current_ply INT UNSIGNED NOT NULL DEFAULT 0 AFTER inaccuracies;
ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS total_ply INT UNSIGNED NOT NULL DEFAULT 0 AFTER current_ply;
ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_ply;
ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS cancel_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER attempts;
ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL DEFAULT NULL AFTER error_message;
ALTER TABLE game_analysis ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL AFTER completed_at;

CREATE INDEX IF NOT EXISTS idx_analysis_queue ON game_analysis (user_id, status, created_at);

INSERT INTO app_migrations (version, description, applied_at)
VALUES ('0.7.0', 'Automatic analysis queue and worker', NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
