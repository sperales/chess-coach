<?php
require_once __DIR__ . '/db.php';

function fallback_motivational_quote(): array {
  return [
    'quote_text' => 'No se trata de jugar perfecto, sino de aprender cada día.',
    'author' => 'José Raúl Capablanca',
  ];
}

function random_motivational_quote(): array {
  try {
    $st = db()->query('SELECT quote_text, author FROM motivational_quotes WHERE is_active=1 ORDER BY RAND() LIMIT 1');
    $quote = $st ? $st->fetch() : null;
    if ($quote && !empty($quote['quote_text'])) {
      return [
        'quote_text' => (string)$quote['quote_text'],
        'author' => (string)($quote['author'] ?? ''),
      ];
    }
  } catch (Throwable $e) {
    // During deployments the code may be uploaded before the SQL migration runs.
  }

  return fallback_motivational_quote();
}
