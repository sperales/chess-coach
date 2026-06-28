-- Chess Coach PWA v0.5.4
-- Refactor técnico: estructura separada en api/, assets/css, assets/js, config/, includes/ y sql/migrations/.
-- Elimina la carpeta legacy private/ y limpia SQL duplicado en la raíz de sql/.
-- No requiere cambios de esquema en MariaDB.
INSERT INTO app_migrations (version, description)
VALUES ('0.5.4', 'Refactored project structure and removed legacy private folder')
ON DUPLICATE KEY UPDATE version = version;
