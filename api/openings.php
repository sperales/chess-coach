<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/openings.php';

$u = require_login();
$userId = (int)$u['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($action === 'backfill') {
  require_post_csrf();
  $body = request_json_body();
  $limit = (int)($body['limit'] ?? ($_POST['limit'] ?? 25));
  json_response(openings_backfill_batch($userId, $limit, 'profile-page'));
}

require_get_request();

if ($action === 'dashboard' || $action === 'list') {
  $limit = (int)($_GET['limit'] ?? 50);
  $minGames = (int)($_GET['min_games'] ?? 1);
  json_response(openings_lab_payload($userId, $limit, $minGames));
}

if ($action === 'detail') {
  $openingKey = (string)($_GET['opening_key'] ?? '');
  json_response(openings_lab_detail_payload($userId, $openingKey));
}

if ($action === 'pending_count') {
  json_response([
    'ok' => true,
    'pending_profiles' => openings_profile_pending_count($userId),
  ]);
}

json_response(['ok' => false, 'error' => 'Acción no soportada.']);
