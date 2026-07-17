<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/review_progress.php';

$user = require_login();
$userId = (int)$user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  $gameId = (int)($_GET['game_id'] ?? 0);
  $progress = $gameId > 0 ? review_progress_for_game($userId, $gameId) : null;
  json_response($progress
    ? ['ok' => true, 'progress' => $progress]
    : ['ok' => false, 'error' => 'Partida analizada no encontrada.']);
}

require_post_csrf();
$body = request_json_body();
$gameId = (int)($body['game_id'] ?? 0);
$plies = is_array($body['plies'] ?? null) ? $body['plies'] : [];
try {
  $progress = $gameId > 0 ? review_progress_record($userId, $gameId, $plies) : null;
  json_response($progress
    ? ['ok' => true, 'progress' => $progress]
    : ['ok' => false, 'error' => 'Partida no indicada.']);
} catch (RuntimeException|InvalidArgumentException $e) {
  json_response(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log('Review progress failed: ' . $e->getMessage());
  json_response(['ok' => false, 'error' => 'No se ha podido guardar el progreso de revisión.']);
}
