<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/training.php';

$u = require_login();
$userId = (int)$u['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$mutatingActions = ['session_start', 'session_end', 'attempt', 'skip'];
if (in_array($action, $mutatingActions, true)) {
  require_post_csrf();
}

if ($action === 'list' || $action === 'dashboard') {
  $type = (string)($_GET['type'] ?? 'recommended');
  $status = (string)($_GET['status'] ?? 'pending');
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
  $list = training_list_exercises($userId, $type, $status, $page, $perPage);
  $session = training_active_session($userId, false);

  json_response([
    'ok' => true,
    'types' => training_exercise_types(),
    'type_counts' => training_type_counts_for_user($userId),
    'stats' => training_stats_for_user($userId),
    'session' => $session,
    'exercises' => $list['items'],
    'pagination' => $list['pagination'],
    'filters' => $list['filters'],
  ]);
}

if ($action === 'stats') {
  $session = training_active_session($userId, false);
  json_response([
    'ok' => true,
    'types' => training_exercise_types(),
    'type_counts' => training_type_counts_for_user($userId),
    'stats' => training_stats_for_user($userId),
    'session' => $session,
  ]);
}

if ($action === 'session_start') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $type = (string)($body['type'] ?? 'recommended');
  $forceNew = !empty($body['force_new']);
  json_response($forceNew
    ? training_new_session($userId, $type, 'manual')
    : training_ensure_active_session($userId, $type, 'manual'));
}

if ($action === 'session_end') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $sessionId = (int)($body['session_id'] ?? 0);
  $status = (string)($body['status'] ?? 'completed');
  json_response($sessionId > 0
    ? training_end_session($userId, $sessionId, $status)
    : ['ok' => false, 'error' => 'Sesión no indicada.']);
}

if ($action === 'get') {
  $id = (int)($_GET['id'] ?? 0);
  $exercise = $id > 0 ? training_exercise_for_user($id, $userId) : null;
  if (!$exercise) json_response(['ok' => false, 'error' => 'Ejercicio no encontrado.']);
  json_response([
    'ok' => true,
    'exercise' => training_public_exercise($exercise, !empty($exercise['resolved_at'])),
  ]);
}

if ($action === 'attempt') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $id = (int)($body['id'] ?? 0);
  $moves = is_array($body['moves'] ?? null) ? $body['moves'] : [];
  $durationMs = (int)($body['duration_ms'] ?? 0);
  $usedHint = !empty($body['used_hint']);
  $sessionId = (int)($body['session_id'] ?? 0);
  $result = $id > 0
    ? training_record_attempt($userId, $id, $moves, $durationMs, $usedHint, $sessionId ?: null)
    : ['ok' => false, 'error' => 'Ejercicio no indicado.'];
  if (!empty($result['exercise']) && is_array($result['exercise'])) {
    $result['exercise'] = training_public_exercise($result['exercise'], !empty($result['solved']) || !empty($result['solution_uci']));
  }
  json_response($result);
}

if ($action === 'skip') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $id = (int)($body['id'] ?? 0);
  $sessionId = (int)($body['session_id'] ?? 0);
  $result = $id > 0
    ? training_record_skip($userId, $id, $sessionId ?: null)
    : ['ok' => false, 'error' => 'Ejercicio no indicado.'];
  if (!empty($result['exercise']) && is_array($result['exercise'])) {
    $result['exercise'] = training_public_exercise($result['exercise'], !empty($result['exercise']['resolved_at']));
  }
  json_response($result);
}

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
