-- Chess Coach v0.9.2
-- Motivational quotes for the home dashboard.

CREATE TABLE IF NOT EXISTS motivational_quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_text VARCHAR(255) NOT NULL,
  author VARCHAR(120) NOT NULL,
  source_name VARCHAR(120) DEFAULT NULL,
  source_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_motivational_quote_text (quote_text),
  KEY idx_motivational_quotes_active (is_active),
  KEY idx_motivational_quotes_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO motivational_quotes (quote_text, author, source_name, source_url, is_active, display_order) VALUES
('Para mejorar, estudia el final antes que cualquier otra cosa.', 'José Raúl Capablanca', 'Wikiquote', 'https://en.wikiquote.org/wiki/Jos%C3%A9_Ra%C3%BAl_Capablanca', 1, 10),
('El buen ajedrez nace de pensar por ti mismo.', 'Emanuel Lasker', 'Wikiquote', 'https://en.wikiquote.org/wiki/Emanuel_Lasker', 1, 20),
('Recuerda métodos, no solo variantes.', 'Emanuel Lasker', 'Wikiquote', 'https://en.wikiquote.org/wiki/Emanuel_Lasker', 1, 30),
('El ajedrez, como la música, tiene poder para hacernos felices.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 40),
('Muchos llegaron a maestros; nadie ha dominado por completo el ajedrez.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 50),
('Antes del final, los dioses pusieron el medio juego.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 60),
('En torneo no basta con saber ajedrez: también hay que jugar bien.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 70),
('La desconfianza es una cualidad necesaria del ajedrecista.', 'Siegbert Tarrasch', 'Wikiquote', 'https://en.wikiquote.org/wiki/Siegbert_Tarrasch', 1, 80),
('En ajedrez no hay nada accidental.', 'Tigran Petrosian', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 90),
('El ajedrez es el juego del filósofo.', 'Paul Morphy', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 100),
('El ajedrez enseña previsión, prudencia y cautela.', 'Benjamin Franklin', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 110),
('El objetivo de la apertura es llegar a un medio juego jugable.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 120),
('La partida se gana con buenas jugadas, una detrás de otra.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 130),
('Quien arriesga puede perder; quien no arriesga siempre pierde.', 'Savielly Tartakower', 'Wikiquote', 'https://en.wikiquote.org/wiki/Savielly_Tartakower', 1, 140),
('Una mala idea es mejor que no tener ningún plan.', 'Frank Marshall', 'Wikiquote', 'https://en.wikiquote.org/wiki/Chess', 1, 150)
ON DUPLICATE KEY UPDATE
  author = VALUES(author),
  source_name = VALUES(source_name),
  source_url = VALUES(source_url),
  is_active = VALUES(is_active),
  display_order = VALUES(display_order);

INSERT INTO app_migrations (version, description)
VALUES ('0.9.2', 'Home motivational quotes catalog')
ON DUPLICATE KEY UPDATE description = VALUES(description);
