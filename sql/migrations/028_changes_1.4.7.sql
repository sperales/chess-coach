-- Chess Coach v1.4.7
-- Versioned contextual content for training exercises.

ALTER TABLE training_exercises
  ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL AFTER source_focus_code,
  ADD COLUMN IF NOT EXISTS content_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER feedback_failure,
  ADD INDEX IF NOT EXISTS idx_training_exercises_content (user_id, content_version, id);

INSERT INTO app_migrations (version, description)
VALUES ('1.4.7', 'Contextual training exercise content')
ON DUPLICATE KEY UPDATE description = VALUES(description);
