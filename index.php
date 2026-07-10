<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
  header('Location: app.php');
  exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
    header('Location: app.php');
    exit;
  }
  $err = 'Usuario o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Chess Coach Login</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="login-page">
  <main class="login-shell" aria-label="Acceso a Chess Coach">
    <section class="login-panel" aria-labelledby="loginTitle">
      <div class="login-brand">
        <img src="assets/icons/logo-approved.png" alt="Chess Coach">
        <p>Juega. Aprende. Mejora.</p>
      </div>

      <div class="login-divider" aria-hidden="true"><span></span></div>

      <h1 id="loginTitle">Entrar</h1>

      <?php if ($err): ?>
        <p class="login-error"><?= e($err) ?></p>
      <?php endif; ?>

      <form class="login-form" method="post">
        <div class="login-field">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 21a8 8 0 0 0-16 0"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <input name="username" placeholder="Usuario" autocomplete="username" aria-label="Usuario" required>
        </div>

        <div class="login-field">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="5" y="11" width="14" height="10" rx="2"></rect>
            <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
          </svg>
          <input id="loginPassword" name="password" type="password" placeholder="Contraseña" autocomplete="current-password" aria-label="Contraseña" required>
          <button class="login-eye" type="button" aria-label="Mostrar contraseña" onclick="toggleLoginPassword()">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>

        <button class="login-submit" type="submit">
          <span>Entrar</span>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m9 18 6-6-6-6"></path>
          </svg>
        </button>
      </form>

      <div class="login-version" aria-label="Versión de Chess Coach">
        <span aria-hidden="true">♘</span>
        <small>v<?= e(app_config()['app_version']) ?></small>
      </div>
    </section>
  </main>
  <script>
    function toggleLoginPassword() {
      const input = document.getElementById('loginPassword');
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
