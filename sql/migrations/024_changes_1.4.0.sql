-- Chess Coach v1.4.0
-- Training Experience foundation: goals and lightweight repetition state.

CREATE TABLE IF NOT EXISTS training_goal_settings (
  user_id INT UNSIGNED NOT NULL,
  daily_goal_mode ENUM('exercises','minutes','both') NOT NULL DEFAULT 'exercises',
  daily_exercise_goal SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  daily_minutes_goal SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  weekly_training_days_goal TINYINT UNSIGNED NOT NULL DEFAULT 4,
  weekly_exercise_goal SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_training_goal_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_exercises
  ADD COLUMN IF NOT EXISTS next_due_at TIMESTAMP NULL DEFAULT NULL AFTER last_attempt_at,
  ADD COLUMN IF NOT EXISTS repeat_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER next_due_at,
  ADD COLUMN IF NOT EXISTS last_training_result ENUM('solved','failed','skipped') DEFAULT NULL AFTER repeat_count,
  ADD COLUMN IF NOT EXISTS last_completed_at TIMESTAMP NULL DEFAULT NULL AFTER last_training_result;

CREATE INDEX IF NOT EXISTS idx_training_exercises_due ON training_exercises (user_id, status, next_due_at, priority_score);
CREATE INDEX IF NOT EXISTS idx_training_exercises_last_result ON training_exercises (user_id, last_training_result, last_completed_at);

INSERT INTO app_migrations (version, description)
VALUES ('1.4.0', 'Training Experience goals and repetition foundation')
ON DUPLICATE KEY UPDATE description = VALUES(description);
