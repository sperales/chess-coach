-- Chess Coach v1.5.0
-- Adaptive Stockfish node budgets and two-pass analysis telemetry.

ALTER TABLE game_analysis
  MODIFY COLUMN engine_search_mode ENUM('depth','movetime','adaptive_nodes') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS engine_secondary_search_value INT UNSIGNED DEFAULT NULL AFTER engine_search_value,
  ADD COLUMN IF NOT EXISTS engine_deep_evaluations INT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_evaluations;

INSERT INTO app_migrations (version, description)
VALUES ('1.5.0-adaptive-analysis', 'Adaptive Stockfish node budgets and two-pass analysis telemetry')
ON DUPLICATE KEY UPDATE description=VALUES(description);
