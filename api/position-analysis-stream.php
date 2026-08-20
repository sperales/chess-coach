<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/chess_notation.php';
require_once __DIR__ . '/../includes/position_analysis.php';

require_login();
require_post_csrf();
$body = request_json_body();
$fen = chess_validate_strict_fen((string)($body['fen'] ?? ''));

@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
if (function_exists('session_write_close')) session_write_close();

function stream_position_event(array $event): void {
  echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
  if (ob_get_level() > 0) @ob_flush();
  flush();
}

if ($fen === null) {
  stream_position_event(['type' => 'error', 'error' => 'La posición FEN no es válida.']);
  exit;
}

try {
  position_analysis_service()->analyze($fen, function (array $analysis, bool $final): void {
    stream_position_event(['type' => $final ? 'final' : 'info', 'analysis' => $analysis]);
  });
} catch (Throwable $error) {
  error_log('[position-analysis-stream] failure: '.$error->getMessage());
  stream_position_event(['type' => 'error', 'error' => public_error_message($error, 'No se pudo analizar la posición.')]);
}
