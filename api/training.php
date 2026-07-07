<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/training.php';

$u = require_login();
$userId = (int)$u['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list' || $action === 'dashboard') {
  $type = (string)($_GET['type'] ?? 'recommended');
  $status = (string)($_GET['status'] ?? 'pending');
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
  $list = training_list_exercises($userId, $type, $status, $page, $perPage);

  json_response([
    'ok' => true,
    'types' => training_exercise_types(),
    'type_counts' => training_type_counts_for_user($userId),
    'stats' => training_stats_for_user($userId),
    'exercises' => $list['items'],
    'pagination' => $list['pagination'],
    'filters' => $list['filters'],
  ]);
}

if ($action === 'stats') {
  json_response([
    'ok' => true,
    'types' => training_exercise_types(),
    'type_counts' => training_type_counts_for_user($userId),
    'stats' => training_stats_for_user($userId),
  ]);
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
  $result = $id > 0
    ? training_record_attempt($userId, $id, $moves, $durationMs, $usedHint)
    : ['ok' => false, 'error' => 'Ejercicio no indicado.'];
  if (!empty($result['exercise']) && is_array($result['exercise'])) {
    $result['exercise'] = training_public_exercise($result['exercise'], !empty($result['solved']) || !empty($result['solution_uci']));
  }
  json_response($result);
}

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
