-- Chess Coach v0.9.0
-- Smart Tags schema and initial reliable tag catalog.

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

INSERT INTO app_migrations (version, description)
VALUES ('0.9.0', 'Smart Tags schema and initial tag catalog')
ON DUPLICATE KEY UPDATE description = VALUES(description);
