-- Chess Coach v1.4.16
-- Stockfish engine identity, search telemetry and strict incomplete-evaluation handling.

ALTER TABLE game_analysis
  ADD COLUMN IF NOT EXISTS engine_version VARCHAR(40) DEFAULT NULL AFTER engine_name,
  ADD COLUMN IF NOT EXISTS engine_build VARCHAR(80) DEFAULT NULL AFTER engine_version,
  ADD COLUMN IF NOT EXISTS engine_threads SMALLINT UNSIGNED DEFAULT NULL AFTER engine_depth,
  ADD COLUMN IF NOT EXISTS engine_hash_mb INT UNSIGNED DEFAULT NULL AFTER engine_threads,
  ADD COLUMN IF NOT EXISTS engine_search_mode ENUM('depth','movetime') DEFAULT NULL AFTER engine_hash_mb,
  ADD COLUMN IF NOT EXISTS engine_search_value INT UNSIGNED DEFAULT NULL AFTER engine_search_mode,
  ADD COLUMN IF NOT EXISTS engine_evaluations INT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_search_value,
  ADD COLUMN IF NOT EXISTS engine_retry_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_evaluations,
  ADD COLUMN IF NOT EXISTS engine_nodes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_retry_count,
  ADD COLUMN IF NOT EXISTS engine_time_ms BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER engine_nodes,
  ADD COLUMN IF NOT EXISTS engine_exit_code SMALLINT DEFAULT NULL AFTER engine_time_ms,
  ADD COLUMN IF NOT EXISTS engine_error_code VARCHAR(40) DEFAULT NULL AFTER engine_exit_code,
  ADD COLUMN IF NOT EXISTS engine_error_message TEXT DEFAULT NULL AFTER engine_error_code;

ALTER TABLE game_move_analysis
  ADD COLUMN IF NOT EXISTS bestmove_after VARCHAR(10) DEFAULT NULL AFTER bestmove,
  MODIFY COLUMN score_before_type ENUM('cp','mate') DEFAULT NULL,
  MODIFY COLUMN score_after_type ENUM('cp','mate') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS depth_before SMALLINT UNSIGNED DEFAULT NULL AFTER score_after_type,
  ADD COLUMN IF NOT EXISTS depth_after SMALLINT UNSIGNED DEFAULT NULL AFTER depth_before,
  ADD COLUMN IF NOT EXISTS seldepth_before SMALLINT UNSIGNED DEFAULT NULL AFTER depth_after,
  ADD COLUMN IF NOT EXISTS seldepth_after SMALLINT UNSIGNED DEFAULT NULL AFTER seldepth_before,
  ADD COLUMN IF NOT EXISTS nodes_before BIGINT UNSIGNED DEFAULT NULL AFTER seldepth_after,
  ADD COLUMN IF NOT EXISTS nodes_after BIGINT UNSIGNED DEFAULT NULL AFTER nodes_before,
  ADD COLUMN IF NOT EXISTS time_before_ms INT UNSIGNED DEFAULT NULL AFTER nodes_after,
  ADD COLUMN IF NOT EXISTS time_after_ms INT UNSIGNED DEFAULT NULL AFTER time_before_ms,
  ADD COLUMN IF NOT EXISTS nps_before BIGINT UNSIGNED DEFAULT NULL AFTER time_after_ms,
  ADD COLUMN IF NOT EXISTS nps_after BIGINT UNSIGNED DEFAULT NULL AFTER nps_before,
  ADD COLUMN IF NOT EXISTS hashfull_before SMALLINT UNSIGNED DEFAULT NULL AFTER nps_after,
  ADD COLUMN IF NOT EXISTS hashfull_after SMALLINT UNSIGNED DEFAULT NULL AFTER hashfull_before,
  ADD COLUMN IF NOT EXISTS pv_before_uci TEXT DEFAULT NULL AFTER hashfull_after,
  ADD COLUMN IF NOT EXISTS pv_after_uci TEXT DEFAULT NULL AFTER pv_before_uci;

INSERT INTO app_migrations (version, description)
VALUES ('1.4.16', 'Stockfish 18 identity, full-history analysis and engine telemetry')
ON DUPLICATE KEY UPDATE description=VALUES(description);
