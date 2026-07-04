<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/dashboard.php';

$u = require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  json_response(['ok' => false, 'error' => 'Metodo no permitido.']);
}

json_response(dashboard_payload((int)$u['id'], (string)$u['username']));
