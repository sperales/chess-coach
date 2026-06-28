-- Chess Coach v0.8.2
-- Correcciones de revisión: mate 0, ACPL capado y evaluación final de mate.
INSERT INTO app_migrations (version, description, applied_at)
VALUES ('0.8.2', 'Review fixes for mate scores, ACPL and final evaluation', NOW())
ON DUPLICATE KEY UPDATE version = version;
