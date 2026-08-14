<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_once __DIR__ . '/../includes/coach.php';

$u = require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_get_request();

$payload = dashboard_payload((int)$u['id'], (string)$u['username']);
$payload['coach_plan'] = coach_recommendation_for_user((int)$u['id'], $payload['training_focus'] ?? []);
json_response($payload);
