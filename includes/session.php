<?php
require_once __DIR__ . '/config.php';
function start_app_session(): void {
  $cfg = app_config();
  if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['session_name'] ?? 'chess_coach_session');
    session_start();
  }
}
