-- Chess Coach v0.8.3
INSERT INTO app_migrations (version, description, applied_at)
VALUES ('0.8.3', 'Chess.com style SVG piece set integrated into review board', NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
