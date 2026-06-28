-- v0.8.1 - Corrección de revisión de partida
-- No hay cambios estructurales en base de datos.
-- Corrige el cálculo de evaluación para futuras partidas y registra la versión.
INSERT INTO app_migrations (version, description)
VALUES ('0.8.1', 'Review board piece colors and normalized evaluation graph')
ON DUPLICATE KEY UPDATE version = version;
