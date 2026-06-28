<?php
require_once __DIR__ . '/config.php';
function db_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $path = __DIR__ . '/../config/database.php';
  if (!file_exists($path)) $path = __DIR__ . '/../config/database.example.php';
  return $cfg = require $path;
}
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $c = db_config();
  $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
  $pdo = new PDO($dsn, $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
