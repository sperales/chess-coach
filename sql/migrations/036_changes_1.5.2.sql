-- Chess Coach v1.5.2
-- Durable Smart Tag processing state and repeatable training plans.

ALTER TABLE game_analysis
  ADD COLUMN IF NOT EXISTS smart_tags_version SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_error_message,
  ADD COLUMN IF NOT EXISTS smart_tags_generated_at TIMESTAMP NULL DEFAULT NULL AFTER smart_tags_version,
  ADD COLUMN IF NOT EXISTS smart_tags_error TEXT DEFAULT NULL AFTER smart_tags_generated_at;

UPDATE game_analysis a
SET a.smart_tags_version=2, a.smart_tags_generated_at=COALESCE(a.completed_at,a.updated_at,a.created_at), a.smart_tags_error=NULL
WHERE a.status='done'
  AND a.smart_tags_version=0
  AND (
    EXISTS (SELECT 1 FROM game_tags gt WHERE gt.analysis_id=a.id)
    OR EXISTS (SELECT 1 FROM move_tags mt WHERE mt.analysis_id=a.id)
  );

ALTER TABLE training_sessions
  ADD COLUMN IF NOT EXISTS repeated_from_session_id BIGINT UNSIGNED DEFAULT NULL AFTER planned_item_count,
  ADD INDEX IF NOT EXISTS idx_training_sessions_repeated_from (repeated_from_session_id);

INSERT INTO app_migrations (version, description)
VALUES ('1.5.2-training-stability', 'Durable Smart Tag state and repeatable training plans')
ON DUPLICATE KEY UPDATE description=VALUES(description);
