-- Chess Coach v1.5.0
-- Coach foundation and ordered Training v2 plans. Existing exercises are inferred as Flash.

ALTER TABLE training_sessions
  ADD COLUMN IF NOT EXISTS coach_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER source,
  ADD COLUMN IF NOT EXISTS coach_focus_code VARCHAR(60) DEFAULT NULL AFTER coach_version,
  ADD COLUMN IF NOT EXISTS coach_focus_title VARCHAR(160) DEFAULT NULL AFTER coach_focus_code,
  ADD COLUMN IF NOT EXISTS coach_rationale VARCHAR(255) DEFAULT NULL AFTER coach_focus_title,
  ADD COLUMN IF NOT EXISTS coach_evidence_json TEXT DEFAULT NULL AFTER coach_rationale,
  ADD COLUMN IF NOT EXISTS estimated_duration_min SMALLINT UNSIGNED DEFAULT NULL AFTER coach_evidence_json,
  ADD COLUMN IF NOT EXISTS planned_item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER estimated_duration_min;

CREATE TABLE IF NOT EXISTS training_session_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  item_type ENUM('flash','scenario') NOT NULL DEFAULT 'flash',
  exercise_id BIGINT UNSIGNED DEFAULT NULL,
  scenario_id BIGINT UNSIGNED DEFAULT NULL,
  concept_code VARCHAR(60) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  evidence_json TEXT DEFAULT NULL,
  status ENUM('pending','active','completed','failed','skipped','abandoned') NOT NULL DEFAULT 'pending',
  started_at TIMESTAMP NULL DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_session_items_position (session_id, position),
  KEY idx_training_session_items_user_status (user_id, status, created_at),
  KEY idx_training_session_items_exercise (exercise_id),
  KEY idx_training_session_items_scenario (scenario_id),
  CONSTRAINT fk_training_session_items_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_session_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_session_items_exercise FOREIGN KEY (exercise_id) REFERENCES training_exercises(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_solve_runs
  ADD COLUMN IF NOT EXISTS session_item_id BIGINT UNSIGNED DEFAULT NULL AFTER session_id,
  ADD KEY IF NOT EXISTS idx_training_solve_runs_session_item (session_item_id),
  ADD CONSTRAINT fk_training_solve_runs_session_item FOREIGN KEY IF NOT EXISTS (session_item_id) REFERENCES training_session_items(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS training_coach_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  session_item_id BIGINT UNSIGNED DEFAULT NULL,
  solve_run_id BIGINT UNSIGNED DEFAULT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  message_type ENUM('intro','selection','feedback','hint','explanation','completion','system') NOT NULL,
  coach_state ENUM('welcome','neutral','thinking','explaining','correct','error') NOT NULL DEFAULT 'neutral',
  message_text TEXT NOT NULL,
  related_ply SMALLINT UNSIGNED DEFAULT NULL,
  metadata_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_coach_messages_sequence (session_id, sequence_no),
  KEY idx_training_coach_messages_user_created (user_id, created_at),
  KEY idx_training_coach_messages_item (session_item_id, sequence_no),
  KEY idx_training_coach_messages_run (solve_run_id, sequence_no),
  CONSTRAINT fk_training_coach_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_coach_messages_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_coach_messages_item FOREIGN KEY (session_item_id) REFERENCES training_session_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_coach_messages_run FOREIGN KEY (solve_run_id) REFERENCES training_solve_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('1.5.0', 'Coach foundation, ordered Training v2 plans and semantic Coach feed')
ON DUPLICATE KEY UPDATE description=VALUES(description);
