-- Chess Coach v1.5.0
-- Training v2 multi-move scenarios, execution history and interactive engine cache.

CREATE TABLE IF NOT EXISTS training_scenarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED DEFAULT NULL,
  analysis_id BIGINT UNSIGNED DEFAULT NULL,
  move_analysis_id BIGINT UNSIGNED DEFAULT NULL,
  starting_ply SMALLINT UNSIGNED NOT NULL,
  starting_fen VARCHAR(120) NOT NULL,
  player_color ENUM('w','b') NOT NULL,
  scenario_type ENUM('conversion','defense','mate') NOT NULL,
  difficulty ENUM('easy','medium','hard','critical') NOT NULL DEFAULT 'medium',
  title VARCHAR(160) NOT NULL,
  prompt VARCHAR(255) NOT NULL,
  source_type ENUM('real_game','player_dna','recent_pattern','curated','external') NOT NULL DEFAULT 'real_game',
  source_focus_code VARCHAR(60) DEFAULT NULL,
  selection_reason VARCHAR(255) DEFAULT NULL,
  initial_score INT DEFAULT NULL,
  initial_score_type ENUM('cp','mate') DEFAULT NULL,
  initial_eval_cp INT DEFAULT NULL,
  target_player_moves TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_player_moves TINYINT UNSIGNED NOT NULL DEFAULT 4,
  acceptance_loss_cp SMALLINT UNSIGNED NOT NULL DEFAULT 80,
  objective_json TEXT DEFAULT NULL,
  completion_json TEXT DEFAULT NULL,
  generator_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_scenarios_source (user_id, analysis_id, starting_ply, scenario_type),
  KEY idx_training_scenarios_user_type (user_id, status, scenario_type, difficulty),
  KEY idx_training_scenarios_analysis (analysis_id),
  KEY idx_training_scenarios_move (move_analysis_id),
  CONSTRAINT fk_training_scenarios_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenarios_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenarios_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenarios_move FOREIGN KEY (move_analysis_id) REFERENCES game_move_analysis(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_scenario_generations (
  analysis_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  generator_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  candidate_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  scenario_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (analysis_id, generator_version),
  KEY idx_training_scenario_generations_user (user_id, generator_version, processed_at),
  CONSTRAINT fk_training_scenario_generations_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenario_generations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_session_items
  ADD CONSTRAINT fk_training_session_items_scenario FOREIGN KEY IF NOT EXISTS (scenario_id) REFERENCES training_scenarios(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS training_scenario_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scenario_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  session_item_id BIGINT UNSIGNED DEFAULT NULL,
  current_fen VARCHAR(120) NOT NULL,
  current_eval_cp INT DEFAULT NULL,
  player_moves_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  attempts_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  highest_hint_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  why_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  quality_score DECIMAL(5,2) DEFAULT NULL,
  evidence_weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  scoring_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','completed','failed','skipped','abandoned') NOT NULL DEFAULT 'active',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_scenario_runs_user_status (user_id, status, started_at),
  KEY idx_training_scenario_runs_scenario (scenario_id, status, started_at),
  KEY idx_training_scenario_runs_session (session_id),
  KEY idx_training_scenario_runs_item (session_item_id),
  CONSTRAINT fk_training_scenario_runs_scenario FOREIGN KEY (scenario_id) REFERENCES training_scenarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenario_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenario_runs_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_scenario_runs_item FOREIGN KEY (session_item_id) REFERENCES training_session_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_scenario_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  actor ENUM('user','opponent','coach','system') NOT NULL,
  event_type ENUM('start','move','retry','hint','explanation','completion','skip','error') NOT NULL,
  fen_before VARCHAR(120) DEFAULT NULL,
  fen_after VARCHAR(120) DEFAULT NULL,
  move_uci VARCHAR(10) DEFAULT NULL,
  move_san VARCHAR(30) DEFAULT NULL,
  score_before_cp INT DEFAULT NULL,
  score_after_cp INT DEFAULT NULL,
  centipawn_loss SMALLINT UNSIGNED DEFAULT NULL,
  decision_bucket ENUM('optimal','acceptable','problematic','critical') DEFAULT NULL,
  accepted TINYINT(1) DEFAULT NULL,
  feedback_text TEXT DEFAULT NULL,
  metadata_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_scenario_events_sequence (run_id, sequence_no),
  KEY idx_training_scenario_events_user_created (user_id, created_at),
  CONSTRAINT fk_training_scenario_events_run FOREIGN KEY (run_id) REFERENCES training_scenario_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_scenario_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_scenario_engine_cache (
  fen_hash CHAR(64) NOT NULL,
  profile_hash CHAR(64) NOT NULL,
  fen VARCHAR(120) NOT NULL,
  score INT NOT NULL,
  score_type ENUM('cp','mate') NOT NULL,
  bestmove VARCHAR(10) DEFAULT NULL,
  pv_uci TEXT DEFAULT NULL,
  depth SMALLINT UNSIGNED DEFAULT NULL,
  nodes BIGINT UNSIGNED DEFAULT NULL,
  time_ms INT UNSIGNED DEFAULT NULL,
  engine_name VARCHAR(80) DEFAULT NULL,
  engine_version VARCHAR(40) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  PRIMARY KEY (fen_hash, profile_hash),
  KEY idx_training_scenario_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('1.5.0-scenarios', 'Training v2 scenarios, execution history and interactive Stockfish cache')
ON DUPLICATE KEY UPDATE description=VALUES(description);
