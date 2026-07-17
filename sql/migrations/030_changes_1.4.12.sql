-- Chess Coach v1.4.12
-- Training progress foundation: solve runs, progressive hints, progress events,
-- score snapshots, personalized plans and review completion tracking.

CREATE TABLE IF NOT EXISTS training_solve_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  status ENUM('active','solved','failed','skipped','abandoned') NOT NULL DEFAULT 'active',
  attempts_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  highest_hint_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  difficulty_snapshot ENUM('easy','medium','hard','critical') NOT NULL DEFAULT 'medium',
  quality_score DECIMAL(5,2) DEFAULT NULL,
  evidence_weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  scoring_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_solve_runs_user_status (user_id, status, started_at),
  KEY idx_training_solve_runs_user_completed (user_id, completed_at),
  KEY idx_training_solve_runs_exercise (exercise_id, status, started_at),
  KEY idx_training_solve_runs_session (session_id),
  CONSTRAINT fk_training_solve_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_solve_runs_exercise FOREIGN KEY (exercise_id) REFERENCES training_exercises(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_solve_runs_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_solve_hints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  solve_run_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  hint_level TINYINT UNSIGNED NOT NULL,
  hint_type VARCHAR(40) NOT NULL,
  hint_text VARCHAR(255) NOT NULL,
  generator_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_solve_hints_run_level (solve_run_id, hint_level),
  KEY idx_training_solve_hints_user_created (user_id, created_at),
  KEY idx_training_solve_hints_exercise (exercise_id, created_at),
  CONSTRAINT fk_training_solve_hints_run FOREIGN KEY (solve_run_id) REFERENCES training_solve_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_solve_hints_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_solve_hints_exercise FOREIGN KEY (exercise_id) REFERENCES training_exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_progress_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  source_type VARCHAR(40) NOT NULL,
  source_id BIGINT UNSIGNED DEFAULT NULL,
  solve_run_id BIGINT UNSIGNED DEFAULT NULL,
  quality_score DECIMAL(5,2) DEFAULT NULL,
  evidence_weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  metadata_json LONGTEXT DEFAULT NULL,
  rule_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_player_progress_events_user_key (user_id, event_key),
  KEY idx_player_progress_events_user_occurred (user_id, occurred_at),
  KEY idx_player_progress_events_user_type (user_id, event_type, occurred_at),
  KEY idx_player_progress_events_source (source_type, source_id),
  KEY idx_player_progress_events_run (solve_run_id),
  CONSTRAINT fk_player_progress_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_player_progress_events_run FOREIGN KEY (solve_run_id) REFERENCES training_solve_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_progress_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  progress_score SMALLINT UNSIGNED NOT NULL,
  autonomy_score DECIMAL(5,2) DEFAULT NULL,
  exercise_component DECIMAL(5,2) DEFAULT NULL,
  game_component DECIMAL(5,2) DEFAULT NULL,
  exercise_samples SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  game_samples SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  calculation_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  reason VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_player_progress_snapshots_user_created (user_id, created_at),
  CONSTRAINT fk_player_progress_snapshots_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_plan_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  goal_key VARCHAR(190) NOT NULL,
  period_type ENUM('daily','weekly') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  goal_type VARCHAR(50) NOT NULL,
  context_type VARCHAR(40) DEFAULT NULL,
  context_key VARCHAR(190) DEFAULT NULL,
  title VARCHAR(160) NOT NULL,
  rationale VARCHAR(255) DEFAULT NULL,
  target_value SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','completed','dismissed','expired') NOT NULL DEFAULT 'pending',
  source ENUM('rules','manual','ai') NOT NULL DEFAULT 'rules',
  generation_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_plan_goals_user_key (user_id, goal_key),
  KEY idx_training_plan_goals_user_period (user_id, period_type, period_start, status),
  KEY idx_training_plan_goals_user_status (user_id, status, period_end),
  CONSTRAINT fk_training_plan_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_review_progress (
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  visited_plies_json TEXT DEFAULT NULL,
  visited_plies_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_plies SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  KEY idx_game_review_progress_user_completed (user_id, completed_at),
  KEY idx_game_review_progress_game (game_id),
  CONSTRAINT fk_game_review_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_game_review_progress_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_attempts
  ADD COLUMN IF NOT EXISTS solve_run_id BIGINT UNSIGNED DEFAULT NULL AFTER session_id,
  ADD INDEX IF NOT EXISTS idx_training_attempts_solve_run (solve_run_id, created_at);

INSERT INTO app_migrations (version, description)
VALUES ('1.4.12', 'Training progress, autonomy and personalized plan foundation')
ON DUPLICATE KEY UPDATE description = VALUES(description);
