-- Chess Coach v1.1.0
-- Training Center foundation: exercises, tags, sessions, attempts and generation runs.

CREATE TABLE IF NOT EXISTS training_exercises (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  analysis_id BIGINT UNSIGNED NOT NULL,
  move_analysis_id BIGINT UNSIGNED NOT NULL,
  ply INT UNSIGNED NOT NULL,
  source_side ENUM('user','opponent') NOT NULL DEFAULT 'user',
  exercise_type ENUM('find_best_move','avoid_blunder','find_mate','spot_threat','find_tactic','defend_position','convert_advantage','other') NOT NULL DEFAULT 'find_best_move',
  fen VARCHAR(120) NOT NULL,
  solution_uci VARCHAR(10) NOT NULL,
  solution_san VARCHAR(30) DEFAULT NULL,
  played_uci VARCHAR(10) DEFAULT NULL,
  played_san VARCHAR(30) DEFAULT NULL,
  centipawn_loss INT UNSIGNED NOT NULL DEFAULT 0,
  classification ENUM('ok','inaccuracy','mistake','blunder') NOT NULL DEFAULT 'ok',
  difficulty ENUM('easy','medium','hard','critical') NOT NULL DEFAULT 'medium',
  priority_score INT NOT NULL DEFAULT 0,
  source_focus_code VARCHAR(40) DEFAULT NULL,
  prompt VARCHAR(255) NOT NULL,
  feedback_success VARCHAR(255) DEFAULT NULL,
  feedback_failure VARCHAR(255) DEFAULT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  last_attempt_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_exercise_move_type (user_id, move_analysis_id, exercise_type),
  KEY idx_training_exercises_user_status (user_id, status, resolved_at, priority_score),
  KEY idx_training_exercises_user_type (user_id, exercise_type, status),
  KEY idx_training_exercises_source (user_id, source_side, priority_score),
  KEY idx_training_exercises_game (game_id),
  KEY idx_training_exercises_analysis (analysis_id),
  KEY idx_training_exercises_move (move_analysis_id),
  CONSTRAINT fk_training_exercises_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_exercises_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_exercises_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_exercises_move FOREIGN KEY (move_analysis_id) REFERENCES game_move_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_exercise_tags (
  exercise_id BIGINT UNSIGNED NOT NULL,
  tag_code VARCHAR(60) NOT NULL,
  source ENUM('move','game','focus') NOT NULL DEFAULT 'move',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (exercise_id, tag_code, source),
  KEY idx_training_exercise_tags_tag (tag_code),
  CONSTRAINT fk_training_exercise_tags_exercise FOREIGN KEY (exercise_id) REFERENCES training_exercises(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_exercise_tags_definition FOREIGN KEY (tag_code) REFERENCES smart_tag_definitions(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  focus_code VARCHAR(40) DEFAULT NULL,
  selected_type ENUM('recommended','find_best_move','avoid_blunder','find_mate','spot_threat','find_tactic','defend_position','convert_advantage','other') NOT NULL DEFAULT 'recommended',
  source ENUM('manual','dashboard','recommended') NOT NULL DEFAULT 'manual',
  status ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  exercise_count INT UNSIGNED NOT NULL DEFAULT 0,
  solved_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  avg_time_ms INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_sessions_user_started (user_id, started_at),
  KEY idx_training_sessions_user_status (user_id, status),
  CONSTRAINT fk_training_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  attempts_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_move_uci VARCHAR(10) DEFAULT NULL,
  final_move_uci VARCHAR(10) DEFAULT NULL,
  attempted_moves_json TEXT DEFAULT NULL,
  is_solved TINYINT(1) NOT NULL DEFAULT 0,
  result ENUM('solved','failed','skipped') NOT NULL DEFAULT 'failed',
  used_hint TINYINT(1) NOT NULL DEFAULT 0,
  difficulty_after_attempt ENUM('easy','medium','hard','critical') DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_attempts_user_created (user_id, created_at),
  KEY idx_training_attempts_exercise (exercise_id, created_at),
  KEY idx_training_attempts_session (session_id),
  CONSTRAINT fk_training_attempts_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_attempts_exercise FOREIGN KEY (exercise_id) REFERENCES training_exercises(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_generation_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  processed_moves INT UNSIGNED NOT NULL DEFAULT 0,
  created_exercises INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_existing INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_training_generation_user_started (user_id, started_at),
  KEY idx_training_generation_status (status),
  CONSTRAINT fk_training_generation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('1.1.0', 'Training Center foundation schema')
ON DUPLICATE KEY UPDATE description = VALUES(description);
