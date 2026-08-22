-- Chess Coach v1.6.0
-- Training Quality & Coach Foundation. Additive schema for shadow rollout.

CREATE TABLE IF NOT EXISTS training_concepts (
  code VARCHAR(60) NOT NULL,
  label VARCHAR(160) NOT NULL,
  description VARCHAR(255) NOT NULL,
  taxonomy_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (code),
  KEY idx_training_concepts_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO training_concepts (code,label,description,taxonomy_version,sort_order,active) VALUES
('tactics_combinations','Táctica y combinaciones','Secuencias concretas que ganan material o fuerzan una ventaja.',1,10,1),
('threats_defense','Amenazas y defensa','Detección de amenazas, defensa precisa y contrajuego defensivo.',1,20,1),
('king_safety_mate','Seguridad del rey y mate','Ataque, prevención y ejecución de redes de mate.',1,30,1),
('calculation_candidates','Cálculo y candidatos','Comparación de jugadas candidatas y cálculo de continuaciones.',1,40,1),
('advantage_conversion','Conversión de ventajas','Transformación de una ventaja en una posición más fácil o ganada.',1,50,1),
('simplification_exchanges','Simplificación e intercambios','Decisiones de cambio y transición que favorecen el objetivo de la posición.',1,60,1),
('activity_coordination_initiative','Actividad, coordinación e iniciativa','Actividad de piezas, coordinación, desarrollo de planes e iniciativa.',1,70,1),
('opening_development','Apertura y desarrollo','Desarrollo, control del centro y seguridad del rey en la apertura.',1,80,1),
('pawn_structure_positional','Estructura de peones y decisiones posicionales','Decisiones estratégicas ligadas a estructura, casillas y piezas.',1,90,1),
('endgames_technique','Finales y técnica','Principios y técnica con material reducido.',1,100,1)
ON DUPLICATE KEY UPDATE
  label=VALUES(label), description=VALUES(description), taxonomy_version=VALUES(taxonomy_version),
  sort_order=VALUES(sort_order), active=VALUES(active);

CREATE TABLE IF NOT EXISTS training_concept_mappings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  signal_type VARCHAR(40) NOT NULL,
  signal_key VARCHAR(80) NOT NULL,
  concept_code VARCHAR(60) NOT NULL,
  role_hint ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.600,
  evidence_json TEXT DEFAULT NULL,
  mapping_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_concept_mapping (signal_type,signal_key,concept_code,mapping_version),
  KEY idx_training_concept_mappings_signal (signal_type,signal_key,active),
  CONSTRAINT fk_training_concept_mappings_concept FOREIGN KEY (concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_opportunities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  canonical_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  canonical_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  normalized_fen VARCHAR(120) NOT NULL,
  side_to_move ENUM('w','b') NOT NULL,
  objective_code VARCHAR(60) NOT NULL,
  objective_json TEXT DEFAULT NULL,
  primary_solution_uci VARCHAR(10) NOT NULL,
  accepted_solutions_json TEXT DEFAULT NULL,
  primary_concept_code VARCHAR(60) NOT NULL,
  concept_confidence DECIMAL(4,3) NOT NULL,
  estimated_difficulty ENUM('easy','medium','hard','critical') NOT NULL DEFAULT 'medium',
  meaningful_decisions TINYINT UNSIGNED NOT NULL DEFAULT 1,
  recommended_format ENUM('flash','scenario','none') NOT NULL DEFAULT 'none',
  relevance_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  concept_confidence_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  decision_clarity_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pedagogical_value_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recurrence_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  adaptive_fit_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  novelty_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  format_suitability_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ambiguity_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  redundancy_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  complexity_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  overexposure_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pedagogical_score SMALLINT NOT NULL DEFAULT 0,
  publication_state ENUM('published','reserve','rejected','inactive','superseded') NOT NULL DEFAULT 'rejected',
  rejection_reason_code VARCHAR(60) DEFAULT NULL,
  rejection_evidence_json TEXT DEFAULT NULL,
  currency_state ENUM('current','stale','unknown') NOT NULL DEFAULT 'unknown',
  recurrence_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  next_review_at TIMESTAMP NULL DEFAULT NULL,
  last_selected_at TIMESTAMP NULL DEFAULT NULL,
  filter_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  scoring_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  difficulty_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  format_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  review_rule_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_opportunity_canonical (user_id,canonical_hash,canonical_version),
  KEY idx_training_opportunities_select (user_id,publication_state,currency_state,primary_concept_code,pedagogical_score),
  KEY idx_training_opportunities_review (user_id,next_review_at,publication_state),
  CONSTRAINT fk_training_opportunities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_opportunities_concept FOREIGN KEY (primary_concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_opportunity_concepts (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  concept_code VARCHAR(60) NOT NULL,
  role ENUM('primary','secondary') NOT NULL DEFAULT 'secondary',
  confidence DECIMAL(4,3) NOT NULL,
  evidence_json TEXT DEFAULT NULL,
  taxonomy_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  mapping_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (opportunity_id,concept_code),
  KEY idx_training_opportunity_concepts_lookup (concept_code,role,confidence),
  CONSTRAINT fk_training_opportunity_concepts_opportunity FOREIGN KEY (opportunity_id) REFERENCES training_opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_opportunity_concepts_concept FOREIGN KEY (concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_opportunity_sources (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  source_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  game_id BIGINT UNSIGNED DEFAULT NULL,
  analysis_id BIGINT UNSIGNED DEFAULT NULL,
  move_analysis_id BIGINT UNSIGNED DEFAULT NULL,
  exercise_id BIGINT UNSIGNED DEFAULT NULL,
  scenario_id BIGINT UNSIGNED DEFAULT NULL,
  source_side ENUM('user','opponent','curated') NOT NULL DEFAULT 'user',
  analysis_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  source_valid TINYINT(1) NOT NULL DEFAULT 1,
  evidence_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_opportunity_source (opportunity_id,source_key),
  KEY idx_training_opportunity_sources_analysis (user_id,analysis_id,source_valid),
  KEY idx_training_opportunity_sources_exercise (exercise_id),
  KEY idx_training_opportunity_sources_scenario (scenario_id),
  CONSTRAINT fk_training_opportunity_sources_opportunity FOREIGN KEY (opportunity_id) REFERENCES training_opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_opportunity_sources_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_opportunity_audits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED DEFAULT NULL,
  user_id INT UNSIGNED NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  reason_code VARCHAR(60) DEFAULT NULL,
  algorithm_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  data_json TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_opportunity_audits_user (user_id,event_code,created_at),
  KEY idx_training_opportunity_audits_opportunity (opportunity_id,created_at),
  CONSTRAINT fk_training_opportunity_audits_opportunity FOREIGN KEY (opportunity_id) REFERENCES training_opportunities(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_opportunity_audits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_selection_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  mode ENUM('legacy','shadow','active') NOT NULL DEFAULT 'shadow',
  legacy_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  selection_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  pool_size INT UNSIGNED NOT NULL DEFAULT 0,
  legacy_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  selected_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  comparison_json TEXT DEFAULT NULL,
  status ENUM('done','error') NOT NULL DEFAULT 'done',
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_training_selection_runs_user (user_id,created_at),
  CONSTRAINT fk_training_selection_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_selection_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  selection_run_id BIGINT UNSIGNED NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  rank_no SMALLINT UNSIGNED NOT NULL,
  selection_priority SMALLINT NOT NULL,
  reason_code VARCHAR(60) NOT NULL,
  reason_evidence_json TEXT DEFAULT NULL,
  due_review_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,
  focus_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recent_game_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recovery_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fatigue_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  similarity_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_selection_item_rank (selection_run_id,rank_no),
  KEY idx_training_selection_items_opportunity (opportunity_id,created_at),
  CONSTRAINT fk_training_selection_items_run FOREIGN KEY (selection_run_id) REFERENCES training_selection_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_selection_items_opportunity FOREIGN KEY (opportunity_id) REFERENCES training_opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_concept_mastery (
  user_id INT UNSIGNED NOT NULL,
  concept_code VARCHAR(60) NOT NULL,
  mastery_state ENUM('starting','learning','consolidating','stable') NOT NULL DEFAULT 'starting',
  recent_performance_state ENUM('in_form','normal','attention','priority_review') NOT NULL DEFAULT 'normal',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  opportunity_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  distinct_training_dates SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  autonomous_success_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  delayed_review_success_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  adjusted_autonomy_rate DECIMAL(5,2) DEFAULT NULL,
  last_trained_at TIMESTAMP NULL DEFAULT NULL,
  next_review_at TIMESTAMP NULL DEFAULT NULL,
  review_pending TINYINT(1) NOT NULL DEFAULT 0,
  evidence_json TEXT DEFAULT NULL,
  mastery_algorithm_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  recent_algorithm_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,concept_code),
  KEY idx_training_concept_mastery_review (user_id,review_pending,next_review_at),
  CONSTRAINT fk_training_concept_mastery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_concept_mastery_concept FOREIGN KEY (concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_mastery_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  opportunity_id BIGINT UNSIGNED DEFAULT NULL,
  concept_code VARCHAR(60) NOT NULL,
  solve_run_id BIGINT UNSIGNED DEFAULT NULL,
  scenario_run_id BIGINT UNSIGNED DEFAULT NULL,
  result_code VARCHAR(40) NOT NULL,
  previous_state VARCHAR(30) DEFAULT NULL,
  resulting_state VARCHAR(30) NOT NULL,
  evidence_json TEXT DEFAULT NULL,
  mastery_algorithm_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_mastery_event (user_id,event_key),
  KEY idx_training_mastery_events_concept (user_id,concept_code,created_at),
  CONSTRAINT fk_training_mastery_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_mastery_events_opportunity FOREIGN KEY (opportunity_id) REFERENCES training_opportunities(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_mastery_events_concept FOREIGN KEY (concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coach_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  primary_concept_code VARCHAR(60) NOT NULL,
  secondary_concept_code VARCHAR(60) DEFAULT NULL,
  confidence DECIMAL(4,3) NOT NULL,
  reason_code VARCHAR(60) NOT NULL,
  reason_text VARCHAR(255) NOT NULL,
  session_objective VARCHAR(255) NOT NULL,
  evidence_json TEXT NOT NULL,
  scores_json TEXT NOT NULL,
  reassess_after_training_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  reassess_after_games SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  reassess_after_at TIMESTAMP NULL DEFAULT NULL,
  algorithm_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  superseded_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_coach_decisions_current (user_id,is_current,created_at),
  CONSTRAINT fk_coach_decisions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_coach_decisions_primary FOREIGN KEY (primary_concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE,
  CONSTRAINT fk_coach_decisions_secondary FOREIGN KEY (secondary_concept_code) REFERENCES training_concepts(code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_foundation_backfill_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('running','done','error') NOT NULL DEFAULT 'running',
  cursor_exercise_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cursor_scenario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  processed_count INT UNSIGNED NOT NULL DEFAULT 0,
  published_count INT UNSIGNED NOT NULL DEFAULT 0,
  reserve_count INT UNSIGNED NOT NULL DEFAULT 0,
  rejected_count INT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(255) DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_training_foundation_backfill_user (user_id,status,updated_at),
  CONSTRAINT fk_training_foundation_backfill_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_exercises
  ADD COLUMN IF NOT EXISTS opportunity_id BIGINT UNSIGNED DEFAULT NULL AFTER move_analysis_id,
  ADD INDEX IF NOT EXISTS idx_training_exercises_opportunity (opportunity_id);

ALTER TABLE training_scenarios
  ADD COLUMN IF NOT EXISTS opportunity_id BIGINT UNSIGNED DEFAULT NULL AFTER move_analysis_id,
  ADD INDEX IF NOT EXISTS idx_training_scenarios_opportunity (opportunity_id);

ALTER TABLE training_session_items
  ADD COLUMN IF NOT EXISTS opportunity_id BIGINT UNSIGNED DEFAULT NULL AFTER scenario_id,
  ADD COLUMN IF NOT EXISTS selection_reason_code VARCHAR(60) DEFAULT NULL AFTER reason,
  ADD COLUMN IF NOT EXISTS selection_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER selection_reason_code,
  ADD INDEX IF NOT EXISTS idx_training_session_items_opportunity (opportunity_id);

ALTER TABLE training_solve_runs
  ADD COLUMN IF NOT EXISTS first_move_at TIMESTAMP NULL DEFAULT NULL AFTER scoring_version,
  ADD COLUMN IF NOT EXISTS time_to_first_move_ms INT UNSIGNED DEFAULT NULL AFTER first_move_at;

ALTER TABLE training_scenario_runs
  ADD COLUMN IF NOT EXISTS first_move_at TIMESTAMP NULL DEFAULT NULL AFTER scoring_version,
  ADD COLUMN IF NOT EXISTS time_to_first_move_ms INT UNSIGNED DEFAULT NULL AFTER first_move_at;

ALTER TABLE training_coach_messages
  MODIFY coach_state ENUM('welcome','neutral','idle','thinking','explaining','correct','error','hint','session_complete') NOT NULL DEFAULT 'idle';

INSERT INTO app_migrations (version, description)
VALUES ('1.6.0-training-quality-foundation', 'Taxonomy, canonical opportunities, shadow selection, mastery and Coach Decision foundation')
ON DUPLICATE KEY UPDATE description=VALUES(description);
