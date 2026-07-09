<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/dashboard.php';

$u = require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_get_request();

json_response(dashboard_payload((int)$u['id'], (string)$u['username']));
