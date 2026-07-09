-- Chess Coach v1.2.0
-- Openings Lab foundation: per-game opening profiles and generation runs.

CREATE TABLE IF NOT EXISTS game_opening_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  analysis_id BIGINT UNSIGNED DEFAULT NULL,
  user_color ENUM('white','black','unknown') NOT NULL DEFAULT 'unknown',
  opening_key VARCHAR(320) NOT NULL,
  display_name VARCHAR(320) NOT NULL,
  eco_code VARCHAR(10) DEFAULT NULL,
  opening_name VARCHAR(255) DEFAULT NULL,
  eco_url VARCHAR(500) DEFAULT NULL,
  opening_source ENUM('pgn','eco','signature','unknown') NOT NULL DEFAULT 'unknown',
  opening_signature VARCHAR(255) DEFAULT NULL,
  first_moves_san TEXT DEFAULT NULL,
  first_moves_uci TEXT DEFAULT NULL,
  plies_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_opening_profile_game (user_id, game_id),
  KEY idx_opening_profiles_user_key (user_id, opening_key),
  KEY idx_opening_profiles_user_color (user_id, user_color),
  KEY idx_opening_profiles_eco (eco_code),
  KEY idx_opening_profiles_analysis (analysis_id),
  CONSTRAINT fk_opening_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_opening_profiles_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_opening_profiles_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opening_profile_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  processed_games INT UNSIGNED NOT NULL DEFAULT 0,
  updated_profiles INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_opening_profile_runs_user_started (user_id, started_at),
  KEY idx_opening_profile_runs_status (status),
  CONSTRAINT fk_opening_profile_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('1.2.0', 'Openings Lab data foundation')
ON DUPLICATE KEY UPDATE description = VALUES(description);
