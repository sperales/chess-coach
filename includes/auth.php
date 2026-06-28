<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
start_app_session();
function current_user(): ?array {
  if (empty($_SESSION['uid'])) return null;
  $st = db()->prepare('SELECT id, username, created_at, updated_at FROM users WHERE id=?');
  $st->execute([$_SESSION['uid']]);
  $u = $st->fetch();
  return $u ?: null;
}
function require_login(): array {
  $u = current_user();
  if (!$u) { header('Location: index.php'); exit; }
  return $u;
}
function login_user(string $username, string $password): bool {
  $st = db()->prepare('SELECT * FROM users WHERE username=?');
  $st->execute([$username]);
  $u = $st->fetch();
  if ($u && password_verify($password, $u['password_hash'])) {
    if (security_config()['session_regenerate_on_login'] ?? true) session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    return true;
  }
  return false;
}
function logout_user(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
