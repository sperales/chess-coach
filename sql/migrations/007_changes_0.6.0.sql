-- Chess Coach PWA v0.6.0
-- Ejecutar solo al actualizar desde v0.5.4.

ALTER TABLE games
  ADD COLUMN IF NOT EXISTS source VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER site,
  ADD INDEX IF NOT EXISTS idx_games_source (source);

CREATE TABLE IF NOT EXISTS game_analysis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  engine_name VARCHAR(80) NOT NULL DEFAULT 'Stockfish',
  engine_depth INT UNSIGNED DEFAULT NULL,
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  blunders INT UNSIGNED NOT NULL DEFAULT 0,
  mistakes INT UNSIGNED NOT NULL DEFAULT 0,
  inaccuracies INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_analysis_game (game_id),
  KEY idx_analysis_user (user_id),
  CONSTRAINT fk_analysis_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_analysis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_move_analysis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id BIGINT UNSIGNED NOT NULL,
  ply INT UNSIGNED NOT NULL,
  san VARCHAR(30) NOT NULL,
  uci VARCHAR(10) NOT NULL,
  fen_before VARCHAR(120) NOT NULL,
  fen_after VARCHAR(120) NOT NULL,
  bestmove VARCHAR(10) DEFAULT NULL,
  score_before INT DEFAULT NULL,
  score_before_type ENUM('cp','mate') NOT NULL DEFAULT 'cp',
  score_after INT DEFAULT NULL,
  score_after_type ENUM('cp','mate') NOT NULL DEFAULT 'cp',
  centipawn_loss INT UNSIGNED NOT NULL DEFAULT 0,
  classification ENUM('ok','inaccuracy','mistake','blunder') NOT NULL DEFAULT 'ok',
  PRIMARY KEY (id),
  UNIQUE KEY uq_move_analysis_ply (analysis_id, ply),
  KEY idx_move_classification (classification),
  CONSTRAINT fk_move_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chesscom_imports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  chesscom_username VARCHAR(80) NOT NULL,
  requested_limit INT UNSIGNED NOT NULL,
  imported_count INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('done','error') NOT NULL DEFAULT 'done',
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chesscom_user (user_id, created_at),
  CONSTRAINT fk_chesscom_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migrations (version, description)
VALUES ('0.6.0', 'Server-side Stockfish analysis and Chess.com import')
ON DUPLICATE KEY UPDATE version = version;
