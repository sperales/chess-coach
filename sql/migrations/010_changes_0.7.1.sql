-- v0.7.1 - token para worker y trazabilidad de ejecuciones
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

INSERT INTO app_migrations (version, description)
VALUES ('0.7.1', 'Cron token and worker execution history')
ON DUPLICATE KEY UPDATE version = version;
