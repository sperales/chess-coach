-- Chess Coach v1.3.0
-- Player DNA foundation: stored profile snapshots and generation runs.

CREATE TABLE IF NOT EXISTS player_dna_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  period_size TINYINT UNSIGNED NOT NULL DEFAULT 10,
  minimum_games TINYINT UNSIGNED NOT NULL DEFAULT 6,
  analyzed_games INT UNSIGNED NOT NULL DEFAULT 0,
  recent_games INT UNSIGNED NOT NULL DEFAULT 0,
  previous_games INT UNSIGNED NOT NULL DEFAULT 0,
  baseline_games INT UNSIGNED NOT NULL DEFAULT 0,
  current_month_games INT UNSIGNED NOT NULL DEFAULT 0,
  previous_month_games INT UNSIGNED NOT NULL DEFAULT 0,
  latest_analysis_id BIGINT UNSIGNED DEFAULT NULL,
  latest_game_id BIGINT UNSIGNED DEFAULT NULL,
  latest_game_date DATE DEFAULT NULL,
  confidence ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  profile_label VARCHAR(120) DEFAULT NULL,
  summary_text TEXT DEFAULT NULL,
  dimensions_json MEDIUMTEXT NOT NULL,
  style_json MEDIUMTEXT NOT NULL,
  strengths_json MEDIUMTEXT NOT NULL,
  weaknesses_json MEDIUMTEXT NOT NULL,
  comparisons_json MEDIUMTEXT NOT NULL,
  recommendations_json MEDIUMTEXT DEFAULT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_player_dna_user_generated (user_id, generated_at),
  KEY idx_player_dna_user_confidence (user_id, confidence),
  KEY idx_player_dna_latest_analysis (latest_analysis_id),
  KEY idx_player_dna_latest_game (latest_game_id),
  CONSTRAINT fk_player_dna_snapshots_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_player_dna_snapshots_analysis FOREIGN KEY (latest_analysis_id) REFERENCES game_analysis(id) ON DELETE SET NULL,
  CONSTRAINT fk_player_dna_snapshots_game FOREIGN KEY (latest_game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_dna_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  snapshot_id BIGINT UNSIGNED DEFAULT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  processed_games INT UNSIGNED NOT NULL DEFAULT 0,
  generated_snapshots INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_player_dna_runs_user_started (user_id, started_at),
  KEY idx_player_dna_runs_status (status),
  KEY idx_player_dna_runs_snapshot (snapshot_id),
  CONSTRAINT fk_player_dna_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_player_dna_runs_snapshot FOREIGN KEY (snapshot_id) REFERENCES player_dna_snapshots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('1.3.0', 'Player DNA data foundation')
ON DUPLICATE KEY UPDATE description = VALUES(description);
