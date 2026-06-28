<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/analysis_queue.php';

@set_time_limit(0);
$engine = engine_config();
$batch = max(1, (int)($engine['worker_batch_size'] ?? 1));
$cron = cron_config();

$isCli = (PHP_SAPI === 'cli');
$trigger = $isCli ? 'cli-cron' : 'http-cron';

if (!$isCli) {
  $token = (string)($_GET['token'] ?? '');
  if ($token === '' || !hash_equals((string)($cron['worker_token'] ?? ''), $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
  }
}

$result = worker_run_batch(null, $batch, $trigger);

if ($isCli) {
  echo "Chess Coach analysis worker\n";
  echo "Batch size: {$batch}\n";
  echo "Procesadas: " . (int)($result['processed_count'] ?? 0) . "\n";
  echo "Correctas: " . (int)($result['success_count'] ?? 0) . "\n";
  echo "Errores: " . (int)($result['error_count'] ?? 0) . "\n";
  echo "Duración: " . (int)($result['duration_ms'] ?? 0) . " ms\n";
  echo ($result['message'] ?? 'OK') . "\n";
  exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => !empty($result['ok']),
  'processed' => !empty($result['processed']),
  'processed_count' => (int)($result['processed_count'] ?? 0),
  'success_count' => (int)($result['success_count'] ?? 0),
  'error_count' => (int)($result['error_count'] ?? 0),
  'duration_ms' => (int)($result['duration_ms'] ?? 0),
  'message' => $result['message'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
