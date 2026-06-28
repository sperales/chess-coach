<?php
function app_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $cfg = require __DIR__ . '/../config/app.php';
  require_once __DIR__ . '/../config/version.php';
  $cfg['app_version'] = defined('APP_VERSION') ? APP_VERSION : 'unknown';
  if (!empty($cfg['default_timezone'])) date_default_timezone_set($cfg['default_timezone']);
  return $cfg;
}
function security_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  return $cfg = require __DIR__ . '/../config/security.php';
}

function engine_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $path = __DIR__ . '/../config/engine.php';
  if (!file_exists($path)) $path = __DIR__ . '/../config/engine.example.php';
  return $cfg = require $path;
}

function cron_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $path = __DIR__ . '/../config/cron.php';
  if (!file_exists($path)) {
    return $cfg = ['worker_token' => '', 'expected_interval_minutes' => 360];
  }
  return $cfg = require $path;
}
