-- v0.8.0 - Chess Review
-- No hay cambios estructurales en base de datos.
-- Se registra la versión para mantener trazabilidad de migraciones.
INSERT INTO app_migrations (version, description)
VALUES ('0.8.0', 'Chess Review with evaluation graph and move-by-move review')
ON DUPLICATE KEY UPDATE version = version;
