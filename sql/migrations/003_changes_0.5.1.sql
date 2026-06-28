-- Chess Coach PWA v0.5.1 - cambios incrementales
-- Esta versión no requiere cambios estructurales en tablas.
-- Registra la corrección de configuración de base de datos y versión de la app.

INSERT INTO app_migrations (version, description)
VALUES ('0.5.1', 'Database name configuration documented and app version updated')
ON DUPLICATE KEY UPDATE version = version;
