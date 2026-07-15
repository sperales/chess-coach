-- Chess Coach v1.4.6
-- Training board interaction preferences.

ALTER TABLE training_goal_settings
  ADD COLUMN IF NOT EXISTS show_legal_moves TINYINT(1) NOT NULL DEFAULT 1 AFTER weekly_exercise_goal,
  ADD COLUMN IF NOT EXISTS auto_submit_move TINYINT(1) NOT NULL DEFAULT 0 AFTER show_legal_moves;

INSERT INTO app_migrations (version, description)
VALUES ('1.4.6', 'Training board interaction preferences')
ON DUPLICATE KEY UPDATE description = VALUES(description);
