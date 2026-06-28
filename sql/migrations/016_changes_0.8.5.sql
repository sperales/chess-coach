-- Chess Coach v0.8.5
-- Vuelta temporal a piezas Unicode y corrección de migración SQL.
INSERT INTO app_migrations (version, description)
VALUES ('0.8.5', 'Return to Unicode pieces and fix migration SQL')
ON DUPLICATE KEY UPDATE description = VALUES(description);
