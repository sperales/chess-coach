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

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
