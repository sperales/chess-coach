<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/player_dna.php';
require_once __DIR__ . '/../includes/coach_decision.php';

$u = require_login();
$userId = (int)$u['id'];
$username = (string)($u['username'] ?? '');
$action = $_GET['action'] ?? $_POST['action'] ?? 'snapshot';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($action === 'recompute') {
  require_post_csrf();
  json_response(player_dna_recalculate($userId, $username, 'profile-page'));
}

require_get_request();

if ($action === 'snapshot' || $action === 'dashboard') {
  $payload = player_dna_payload($userId);
  try {
    $payload['coach_decision'] = coach_decision_public(coach_decision_for_user($userId));
  } catch (Throwable $e) {
    $payload['coach_decision'] = null;
  }
  json_response($payload);
}

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
