-- Chess Coach PWA v0.9.0 - instalación completa
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS games (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_uid CHAR(64) NOT NULL,
  white_player VARCHAR(120) DEFAULT NULL,
  black_player VARCHAR(120) DEFAULT NULL,
  result_raw VARCHAR(20) DEFAULT NULL,
  user_result ENUM('win','loss','draw','unknown') NOT NULL DEFAULT 'unknown',
  played_at DATE DEFAULT NULL,
  event_name VARCHAR(255) DEFAULT NULL,
  site VARCHAR(255) DEFAULT NULL,
  eco_code VARCHAR(10) DEFAULT NULL,
  opening_name VARCHAR(255) DEFAULT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'manual',
  pgn MEDIUMTEXT NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_games_user_uid (user_id, game_uid),
  KEY idx_games_user_date (user_id, played_at),
  KEY idx_games_opening (eco_code),
  KEY idx_games_source (source),
  CONSTRAINT fk_games_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_analysis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  engine_name VARCHAR(80) NOT NULL DEFAULT 'Stockfish',
  engine_depth INT UNSIGNED DEFAULT NULL,
  status ENUM('queued','running','done','error','cancelled') NOT NULL DEFAULT 'queued',
  blunders INT UNSIGNED NOT NULL DEFAULT 0,
  mistakes INT UNSIGNED NOT NULL DEFAULT 0,
  inaccuracies INT UNSIGNED NOT NULL DEFAULT 0,
  current_ply INT UNSIGNED NOT NULL DEFAULT 0,
  total_ply INT UNSIGNED NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  cancel_requested TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  started_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_analysis_game (game_id),
  KEY idx_analysis_user (user_id),
  KEY idx_analysis_queue (user_id, status, created_at),
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


CREATE TABLE IF NOT EXISTS analysis_worker_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  queue_before INT UNSIGNED NOT NULL DEFAULT 0,
  queue_after INT UNSIGNED NOT NULL DEFAULT 0,
  processed_count INT UNSIGNED NOT NULL DEFAULT 0,
  success_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_analysis_id BIGINT UNSIGNED DEFAULT NULL,
  last_status VARCHAR(20) DEFAULT NULL,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_worker_runs_user (user_id, created_at),
  KEY idx_worker_runs_analysis (last_analysis_id),
  CONSTRAINT fk_worker_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_worker_runs_analysis FOREIGN KEY (last_analysis_id) REFERENCES game_analysis(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS smart_tag_definitions (
  code VARCHAR(60) NOT NULL,
  label VARCHAR(80) NOT NULL,
  scope ENUM('game','move','both') NOT NULL DEFAULT 'both',
  category VARCHAR(40) NOT NULL DEFAULT 'general',
  severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'info',
  description VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (code),
  KEY idx_smart_tag_scope (scope),
  KEY idx_smart_tag_category (category),
  KEY idx_smart_tag_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  analysis_id BIGINT UNSIGNED NOT NULL,
  tag_code VARCHAR(60) NOT NULL,
  confidence DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  evidence_count INT UNSIGNED NOT NULL DEFAULT 0,
  primary_ply INT UNSIGNED DEFAULT NULL,
  details_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_game_tag_analysis_code (analysis_id, tag_code),
  KEY idx_game_tags_user_tag (user_id, tag_code),
  KEY idx_game_tags_game (game_id),
  KEY idx_game_tags_analysis (analysis_id),
  KEY idx_game_tags_tag (tag_code),
  CONSTRAINT fk_game_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_game_tags_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_game_tags_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_game_tags_definition FOREIGN KEY (tag_code) REFERENCES smart_tag_definitions(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS move_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  analysis_id BIGINT UNSIGNED NOT NULL,
  move_analysis_id BIGINT UNSIGNED NOT NULL,
  ply INT UNSIGNED NOT NULL,
  tag_code VARCHAR(60) NOT NULL,
  confidence DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'info',
  details_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_move_tag_move_code (move_analysis_id, tag_code),
  KEY idx_move_tags_user_tag (user_id, tag_code),
  KEY idx_move_tags_game (game_id),
  KEY idx_move_tags_analysis (analysis_id),
  KEY idx_move_tags_move (move_analysis_id),
  KEY idx_move_tags_tag (tag_code),
  CONSTRAINT fk_move_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_move_tags_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_move_tags_analysis FOREIGN KEY (analysis_id) REFERENCES game_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_move_tags_move FOREIGN KEY (move_analysis_id) REFERENCES game_move_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_move_tags_definition FOREIGN KEY (tag_code) REFERENCES smart_tag_definitions(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smart_tag_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'unknown',
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  processed_games INT UNSIGNED NOT NULL DEFAULT 0,
  tagged_games INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_smart_tag_runs_user (user_id, started_at),
  KEY idx_smart_tag_runs_status (status),
  CONSTRAINT fk_smart_tag_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS motivational_quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_text VARCHAR(255) NOT NULL,
  author VARCHAR(120) NOT NULL,
  source_name VARCHAR(120) DEFAULT NULL,
  source_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_motivational_quote_text (quote_text),
  KEY idx_motivational_quotes_active (is_active),
  KEY idx_motivational_quotes_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  version VARCHAR(30) NOT NULL,
  description VARCHAR(255) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_app_migrations_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (username, password_hash)
VALUES ('sperales', '$2y$12$1zypokfJvKDhAYgsiVuK1Outhq2uKUMHmr.WcoYdNpU9WPyXRmoxC')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO smart_tag_definitions (code, label, scope, category, severity, description, is_active) VALUES
('blunder_own', 'Omisión grave', 'both', 'error', 'critical', 'Jugada propia con una pérdida muy alta de evaluación.', 1),
('mistake_own', 'Error importante', 'both', 'error', 'high', 'Jugada propia que concede una oportunidad clara al rival.', 1),
('inaccuracy_own', 'Imprecisión', 'both', 'error', 'low', 'Jugada propia que deja escapar parte de la ventaja o iniciativa.', 1),
('opening_issue', 'Problema de apertura', 'game', 'phase', 'medium', 'La partida contiene errores relevantes en la fase inicial.', 1),
('endgame_mistake', 'Error en final', 'game', 'phase', 'high', 'La partida contiene un error relevante en el tramo final.', 1),
('missed_mate', 'Mate omitido', 'move', 'tactical', 'critical', 'La posición ofrecía una secuencia de mate favorable que no se aprovechó.', 1),
('allowed_mate', 'Mate permitido', 'move', 'tactical', 'critical', 'La jugada permitió una secuencia de mate favorable al rival.', 1),
('lost_winning_position', 'Ventaja desperdiciada', 'game', 'error', 'high', 'La partida pasó de una ventaja clara a una posición igualada o peor.', 1),
('comeback', 'Remontada', 'game', 'positive', 'medium', 'La partida terminó bien después de haber estado claramente peor.', 1),
('converted_advantage', 'Ventaja convertida', 'game', 'positive', 'medium', 'La partida convirtió una ventaja clara en un buen resultado.', 1),
('precise_game', 'Partida precisa', 'game', 'positive', 'low', 'La partida tuvo pocas pérdidas relevantes de evaluación.', 1),
('strong_finish', 'Buen tramo final', 'game', 'positive', 'low', 'La partida mantuvo o mejoró la evaluación en el tramo final.', 1)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  scope = VALUES(scope),
  category = VALUES(category),
  severity = VALUES(severity),
  description = VALUES(description),
  is_active = VALUES(is_active);

INSERT INTO motivational_quotes (quote_text, author, source_name, source_url, is_active, display_order) VALUES
('Para mejorar, estudia el final antes que cualquier otra cosa.', 'José Raúl Capablanca', 'Wikiquote', 'https://en.wikiquote.org/wiki/Jos%C3%A9_Ra%C3%BAl_Capablanca', 1, 10),
('El buen ajedrez nace de pensar por ti mismo.', 'Emanuel Lasker', 'Wikiquote', 'https://en.wikiquote.org/wiki/Emanuel_Lasker', 1, 20),
('Recuerda métodos, no solo variantes.', 'Emanuel Lasker', 'Wikiquote', 'https://en.wikiquote.org/wiki/Emanuel_Lasker', 1, 30),
('El ajedrez, como la música, tiene poder para hacernos felices.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 40),
('Muchos llegaron a maestros; nadie ha dominado por completo el ajedrez.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 50),
('Antes del final, los dioses pusieron el medio juego.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 60),
('En torneo no basta con saber ajedrez: también hay que jugar bien.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 70),
('La desconfianza es una cualidad necesaria del ajedrecista.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 80),
('En ajedrez no hay nada accidental.', 'Tigran Petrosian', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 90),
('El ajedrez es el juego del filósofo.', 'Paul Morphy', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 100),
('El ajedrez enseña previsión, prudencia y cautela.', 'Benjamin Franklin', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 110),
('El objetivo de la apertura es llegar a un medio juego jugable.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 120),
('La partida se gana con buenas jugadas, una detrás de otra.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 130),
('Quien arriesga puede perder; quien no arriesga siempre pierde.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 140),
('Una mala idea es mejor que no tener ningún plan.', 'Frank Marshall', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 150)
ON DUPLICATE KEY UPDATE
  author = VALUES(author),
  source_name = VALUES(source_name),
  source_url = VALUES(source_url),
  is_active = VALUES(is_active),
  display_order = VALUES(display_order);

INSERT INTO app_migrations (version, description) VALUES
('0.5.1', 'Initial MariaDB schema, login, profile password change and game storage'),
('0.5.2', 'Import PGN collapsed by default'),
('0.5.3', 'Separated app and database configuration'),
('0.5.4', 'Refactored project structure and removed legacy private folder'),
('0.6.0', 'Server-side Stockfish analysis and Chess.com import'),
('0.6.1', 'Background-like analysis UX and result badges'),
('0.6.3', 'Games pagination'),
('0.6.5', 'Home redesign and unified import page'),
('0.6.7', 'Header-only navigation and approved logo'),
('0.7.0', 'Automatic analysis queue and worker'),
('0.7.1', 'Cron token and worker execution history'),
('0.7.2', 'Home toggle, 6-hour cron estimate and regenerated PWA icons'),
('0.8.0', 'Chess Review with evaluation graph and move-by-move review'),
('0.8.1', 'Review board piece colors and normalized evaluation graph'),
('0.8.2', 'Review fixes for mate scores, ACPL and final evaluation'),
('0.8.3', 'Chess.com style SVG piece set integrated into review board'),
('0.8.4', 'Replace chess piece assets with PNG set'),
('0.8.5', 'Return to Unicode pieces and fix migration SQL'),
('0.9.0', 'Smart Tags schema and initial tag catalog'),
('0.9.2', 'Home motivational quotes catalog')
ON DUPLICATE KEY UPDATE version = version;
