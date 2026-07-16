-- Chess Coach v1.4.8
-- Training error feedback and optional Stockfish principal-variation enrichment.

ALTER TABLE training_exercises
  ADD COLUMN IF NOT EXISTS engine_bestmove_uci VARCHAR(10) DEFAULT NULL AFTER content_version,
  ADD COLUMN IF NOT EXISTS engine_pv_uci TEXT DEFAULT NULL AFTER engine_bestmove_uci,
  ADD COLUMN IF NOT EXISTS engine_score INT DEFAULT NULL AFTER engine_pv_uci,
  ADD COLUMN IF NOT EXISTS engine_score_type ENUM('cp','mate') DEFAULT NULL AFTER engine_score,
  ADD COLUMN IF NOT EXISTS engine_depth SMALLINT UNSIGNED DEFAULT NULL AFTER engine_score_type,
  ADD COLUMN IF NOT EXISTS engine_solution_mismatch TINYINT(1) NOT NULL DEFAULT 0 AFTER engine_depth,
  ADD COLUMN IF NOT EXISTS engine_refreshed_at TIMESTAMP NULL DEFAULT NULL AFTER engine_solution_mismatch,
  ADD INDEX IF NOT EXISTS idx_training_exercises_engine_backfill (user_id, status, resolved_at, content_version, id);

INSERT INTO app_migrations (version, description)
VALUES ('1.4.8', 'Training feedback and Stockfish principal variations')
ON DUPLICATE KEY UPDATE description = VALUES(description);
