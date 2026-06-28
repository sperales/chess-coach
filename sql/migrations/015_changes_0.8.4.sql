-- Chess Coach v0.8.4
-- Corregida en v0.8.5: sin columna applied_at para evitar errores si la tabla usa DEFAULT CURRENT_TIMESTAMP.
INSERT INTO app_migrations (version, description)
VALUES ('0.8.4', 'Replace chess piece assets with PNG set')
ON DUPLICATE KEY UPDATE description = VALUES(description);
