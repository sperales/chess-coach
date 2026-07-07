<?php
require_once __DIR__ . '/config.php';
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function coach_menu_items(): array {
  return [
    ['app.php', '⌂', 'Inicio', false],
    ['games.php', '♟', 'Partidas', false],
    ['import-chesscom.php', '⇩', 'Importar partidas', false],
    ['analysis-pending.php', '◷', 'Análisis pendientes', false],
    ['training.php', '◎', 'Entrenamiento', false],
    ['#', '▤', 'Aperturas', true],
    ['#', '▥', 'Estadísticas', true],
    ['#', '◇', 'Etiquetas', true],
    ['profile.php', '⚙', 'Ajustes / Mi Perfil', false],
  ];
}
function current_user_elo(): int {
  return (int)(app_config()['default_user_elo'] ?? 1426);
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
