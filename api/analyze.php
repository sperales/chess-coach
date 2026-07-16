<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/analysis_queue.php';

$u = require_login();
$userId = (int)$u['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$mutatingActions = [
  'queue',
  'queue_missing',
  'retry_errors',
  'cancel_waiting',
  'cancel',
  'process_next',
  'run_worker',
  'smart_tags_backfill',
  'training_backfill',
  'training_content_backfill',
  'training_engine_backfill',
  'process',
];
if (in_array($action, $mutatingActions, true)) {
  require_post_csrf();
}

function queue_payload(int $userId, int $page = 1, int $perPage = 50): array {
  $perPage = max(1, min(200, $perPage));
  $total = queue_total_count($userId);
  $pages = max(1, (int)ceil($total / $perPage));
  $page = max(1, min($pages, $page));
  $offset = ($page - 1) * $perPage;
  return [
    'ok' => true,
    'queue' => queue_stats($userId),
    'jobs' => queue_list($userId, $perPage, $offset),
    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages],
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
  $cfg = app_config();
  $defaultPerPage = max(1, (int)($cfg['analysis_per_page'] ?? 50));
  $perPage = (int)($_GET['per_page'] ?? ($_GET['limit'] ?? $defaultPerPage));
  $page = max(1, (int)($_GET['page'] ?? 1));
  json_response(queue_payload($userId, $page, $perPage));
}

if ($action === 'queue') {
  $body = request_json_body();
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
  $body = request_json_body();
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
  $body = request_json_body();
  $batch = max(1, min(20, (int)($body['batch'] ?? 1)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(worker_run_batch($userId, $batch, 'manual-page'));
}

if ($action === 'smart_tags_backfill') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = request_json_body();
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

if ($action === 'training_backfill') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = request_json_body();
  $limit = max(1, min(50, (int)($body['limit'] ?? 10)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(training_backfill_batch($userId, $limit, 'profile-page'));
}

if ($action === 'training_backfill_status') {
  json_response([
    'ok' => true,
    'pending' => training_backfill_pending_count($userId),
    'stats' => training_stats_for_user($userId),
  ]);
}

if ($action === 'training_content_backfill') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = request_json_body();
  $limit = max(1, min(500, (int)($body['limit'] ?? 200)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(training_content_backfill_batch($userId, $limit));
}

if ($action === 'training_engine_backfill') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = request_json_body();
  $limit = max(1, min(20, (int)($body['limit'] ?? 20)));
  if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
  json_response(training_engine_backfill_batch($userId, $limit));
}

if ($action === 'process') {
  ignore_user_abort(true);
  @set_time_limit(300);
  $body = request_json_body();
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
