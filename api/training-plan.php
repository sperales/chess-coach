<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/training_plan.php';

$user = require_login();
require_post_csrf();

try {
  json_response(['ok' => true, 'plan' => training_plan_refresh((int)$user['id'])]);
} catch (Throwable $e) {
  error_log('Training plan refresh failed: ' . $e->getMessage());
  json_response(['ok' => false, 'error' => 'No se ha podido preparar el plan de entrenamiento.']);
}
