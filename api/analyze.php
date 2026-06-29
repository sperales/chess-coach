<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/analysis_queue.php';

$u = require_login();
$userId = (int)$u['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function queue_payload(int $userId, int $limit = 80): array {
  return [
    'ok' => true,
    'queue' => queue_stats($userId),
    'jobs' => queue_list($userId, $limit),
    'stockfish' => stockfish_available(),
    'worker' => worker_summary($userId),
    'history' => worker_history($userId, 20),
  ];
}

if ($action === 'status' || $action === 'engine_status' || $action === 'queue_status') {
  json_response([
    'ok' => true,
    'stockfish' => stockfish_available(),
    'queue' => queue_stats($userId),
    'worker' => worker_summary($userId),
  ]);
}

if ($action === 'queue_list' || $action === 'dashboard') {
  $limit = (int)($_GET['limit'] ?? 80);
  json_response(queue_payload($userId, $limit));
}

if ($action === 'queue') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $id = (int)($body['id'] ?? 0);
  $force = !empty($body['force']);
  json_response(queue_game_analysis($id, $userId, $force));
}

if ($action === 'queue_missing') {
  json_response(queue_missing_games($userId));
}

if ($action === 'retry_errors') {
  json_response(queue_retry_errors($userId));
}

if ($action === 'cancel_waiting') {
  json_response(queue_cancel_waiting($userId));
}

if ($action === 'cancel') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $analysisId = (int)($body['analysis_id'] ?? 0);
  json_response(queue_request_cancel($analysisId, $userId));
}

if ($action === 'process_next') {
  ignore_user_abort(true);
  @set_time_limit(300);
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(worker_run_batch($userId, 1, 'manual-page'));
}

if ($action === 'run_worker') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $batch = max(1, min(20, (int)($body['batch'] ?? 1)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(worker_run_batch($userId, $batch, 'manual-page'));
}

if ($action === 'smart_tags_backfill') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $limit = max(1, min(50, (int)($body['limit'] ?? 10)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(smart_tag_backfill_batch($userId, $limit, 'profile-page'));
}

if ($action === 'smart_tags_backfill_status') {
  json_response([
    'ok' => true,
    'pending' => smart_tag_backfill_pending_count($userId),
  ]);
}

if ($action === 'process') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $analysisId = (int)($body['analysis_id'] ?? 0);
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(process_analysis_job($analysisId, $userId));
}

if ($action === 'latest') {
  $id = (int)($_GET['id'] ?? 0);
  $st = db()->prepare('SELECT * FROM game_analysis WHERE game_id=? AND user_id=? ORDER BY id DESC LIMIT 1');
  $st->execute([$id, $userId]);
  $a = $st->fetch();
  if (!$a) json_response(['ok' => true, 'analysis' => null]);
  $m = db()->prepare('SELECT * FROM game_move_analysis WHERE analysis_id=? ORDER BY ply');
  $m->execute([$a['id']]);
  json_response(['ok' => true, 'analysis' => $a, 'moves' => $m->fetchAll()]);
}

json_response(['ok' => false, 'error' => 'Acción no soportada']);
