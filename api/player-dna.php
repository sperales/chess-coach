<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/player_dna.php';

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
  json_response(player_dna_payload($userId));
}

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
