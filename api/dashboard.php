<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_once __DIR__ . '/../includes/coach.php';

$u = require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_get_request();

$payload = dashboard_payload((int)$u['id'], (string)$u['username']);
$currentTraining = coach_current_plan_for_user((int)$u['id'], $payload['training_focus'] ?? []);
$payload['coach_plan'] = $currentTraining['plan'];
$payload['active_training'] = $currentTraining['training'];
json_response($payload);
