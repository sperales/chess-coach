<?php
require_once __DIR__ . '/config.php';
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE && function_exists('start_app_session')) {
    start_app_session();
  }
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_script(): string {
  return '<script>window.CHESS_COACH_CSRF = '.json_encode(csrf_token(), JSON_UNESCAPED_SLASHES).';</script>';
}
function request_csrf_token(): string {
  $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (is_string($header) && $header !== '') return $header;
  $post = $_POST['csrf_token'] ?? '';
  return is_string($post) ? $post : '';
}
function csrf_token_valid(?string $token): bool {
  return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}
function csrf_failure(): void {
  http_response_code(403);
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  if (strpos($script, '/api/') !== false || strpos($accept, 'application/json') !== false) {
    json_response(['ok' => false, 'error' => 'Token CSRF no válido.']);
  }
  echo 'Token CSRF no válido.';
  exit;
}
function require_csrf_token(): void {
  if (!csrf_token_valid(request_csrf_token())) csrf_failure();
}
function require_post_request(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    json_response(['ok' => false, 'error' => 'Método no permitido.']);
  }
}
function require_get_request(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    json_response(['ok' => false, 'error' => 'Método no permitido.']);
  }
}
function require_post_csrf(): void {
  require_post_request();
  require_csrf_token();
}
function request_json_body(): array {
  static $body = null;
  if ($body !== null) return $body;

  $raw = file_get_contents('php://input');
  if (!is_string($raw) || trim($raw) === '') {
    $body = [];
    return $body;
  }

  $decoded = json_decode($raw, true);
  $body = is_array($decoded) ? $decoded : [];
  return $body;
}
function coach_menu_items(): array {
  return [
    ['app.php', '⌂', 'Inicio', false],
    ['games.php', '♟', 'Partidas', false],
    ['import-chesscom.php', '⇩', 'Importar partidas', false],
    ['analysis-pending.php', '◷', 'Análisis pendientes', false],
    ['training.php', '◎', 'Entrenamiento', false],
    ['openings-lab.php', '▤', 'Lab de Aperturas', false],
    ['player-dna.php', '◈', 'ADN del jugador', false],
    ['profile.php', '⚙', 'Ajustes / Mi Perfil', false],
  ];
}
function current_user_elo(): int {
  return (int)(app_config()['default_user_elo'] ?? 1426);
}
function header_training_streak_html(int $userId): string {
  try {
    require_once __DIR__ . '/training.php';
    $experience = training_experience_summary($userId);
    $today = $experience['today'] ?? [];
    $streak = $experience['streak'] ?? [];
    $days = (int)($streak['days'] ?? 0);
    $trainedToday = !empty($today['trained']);
    $todayMet = !empty($streak['today_goal_met']);
    $title = $todayMet
      ? 'Objetivo diario cumplido. Racha activa.'
      : ($days > 0 ? 'Completa el objetivo de hoy para mantener la racha.' : 'Completa tu objetivo diario para iniciar una racha.');
    return '<a class="streak-pill'.($trainedToday ? ' trained' : '').($todayMet ? ' achieved' : '').'" href="training.php" title="'.e($title).'" aria-label="Racha de entrenamiento: '.$days.' día(s)"><span aria-hidden="true">↗</span><strong>'.$days.'</strong></a>';
  } catch (Throwable $e) {
    return '';
  }
}
function header_bar(string $title='Chess Coach'): void {
  $v = e(app_config()['app_version']);
  $user = current_user();
  $displayName = e($user['username'] ?? 'Usuario');
  $initial = strtoupper(substr($user['username'] ?? 'U', 0, 1));
  $elo = current_user_elo();
  echo '<header class="topbar">';
  echo '<a class="brand" href="app.php" aria-label="Ir al inicio"><img class="brand-lockup" src="assets/icons/logo-approved.png" alt="Chess Coach"></a>';
  echo '<div class="spacer"></div>';
  echo '<div class="user-pill"><div class="avatar">'.e($initial).'</div><div><strong>'.$displayName.'</strong><small>ELO '.$elo.'</small></div></div>';
  if (!empty($user['id'])) echo header_training_streak_html((int)$user['id']);
  echo '<span class="version">v'.$v.'</span>';
  echo '<button class="hamb" id="menuBtn" aria-label="Menú" aria-controls="userMenu" aria-expanded="false">☰</button>';
  echo '<nav class="menu" id="userMenu">';
  foreach (coach_menu_items() as $item) {
    [$href, $icon, $label, $disabled] = $item;
    echo '<a class="'.($disabled ? 'disabled ' : '').'" href="'.e($href).'"><span class="menu-icon">'.e($icon).'</span><span>'.e($label).'</span></a>';
  }
  echo '<a class="menu-logout" href="logout.php"><span class="menu-icon">⇥</span><span>Salir</span></a>';
  echo '</nav></header>';
}
function sidebar_nav(string $active='inicio'): void {
  // Sidebar removed from v0.6.7. Navigation is fully handled by the header hamburger menu.
}
function json_response(array $data): void {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function public_error_message(Throwable $e, string $fallback = 'Se ha producido un error interno.'): string {
  $message = trim($e->getMessage());
  if ($message === '') return $fallback;

  $sensitivePatterns = [
    '/[A-Za-z]:\\\\/',
    '#/(home|var|usr|etc|tmp|opt|srv|www)/#i',
    '/config\/(database|engine|cron)\.php/i',
    '/password|token|secret|api[_-]?key/i',
    '/SQLSTATE\[[^\]]+\]/i',
  ];
  foreach ($sensitivePatterns as $pattern) {
    if (preg_match($pattern, $message)) return $fallback;
  }

  return substr($message, 0, 240);
}
