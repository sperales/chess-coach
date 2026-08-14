<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
  header('Location: app.php');
  exit;
}

$err = '';
$username = '';
$assetVersion = (string)filemtime(__DIR__ . '/assets/css/app.css');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim((string)($_POST['username'] ?? ''));
  if (!csrf_token_valid(request_csrf_token())) {
    $err = 'La sesión de acceso ha caducado. Inténtalo de nuevo.';
  } elseif (login_user($username, $_POST['password'] ?? '')) {
    header('Location: app.php');
    exit;
  } else {
    $err = 'Usuario o contraseña incorrectos.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Chess Coach Login</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= e($assetVersion) ?>">
  <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
  <link rel="alternate icon" href="assets/icons/favicon.ico">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="login-page">
  <video class="login-background-video" id="loginBackgroundVideo" autoplay muted loop playsinline preload="metadata" poster="assets/images/login-nova-background-poster.jpg" aria-hidden="true" tabindex="-1">
    <source src="assets/images/login-nova-background.webm" type="video/webm">
  </video>
  <main class="login-shell" aria-label="Acceso a Chess Coach">
    <div class="login-layout">
      <header class="login-brand">
        <img src="assets/brand/logo-horizontal-dark.svg" alt="Chess Coach">
      </header>

      <section class="login-intro" aria-labelledby="loginTitle">
        <h1 id="loginTitle">Lleva tu entrenamiento al siguiente nivel</h1>
        <p>Nova ha preparado tu entrenamiento de hoy.</p>
      </section>

      <section class="login-panel" aria-label="Formulario de acceso">
        <?php if ($err): ?>
          <p class="login-error" id="loginError" role="alert"><?= e($err) ?></p>
        <?php endif; ?>

        <form class="login-form" method="post" action="index.php" autocomplete="on" accept-charset="UTF-8"<?= $err ? ' aria-describedby="loginError"' : '' ?>>
          <?= csrf_field() ?>
          <div class="login-field">
            <label class="sr-only" for="username">Usuario</label>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20 21a8 8 0 0 0-16 0"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <input id="username" name="username" type="text" value="<?= e($username) ?>" placeholder="Usuario" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="next" aria-label="Nombre de usuario" required>
          </div>

          <div class="login-field">
            <label class="sr-only" for="password">Contraseña</label>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="5" y="11" width="14" height="10" rx="2"></rect>
              <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
            </svg>
            <input id="password" name="password" type="password" placeholder="Contraseña" autocomplete="current-password" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="go" aria-label="Contraseña" required>
            <button class="login-eye" id="loginPasswordToggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" onclick="toggleLoginPassword()">
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
      </section>

      <div class="login-version" aria-label="Versión de Chess Coach">
        <small>v<?= e(app_config()['app_version']) ?></small>
      </div>
    </div>
  </main>
  <script>
    function toggleLoginPassword() {
      const input = document.getElementById('password');
      const toggle = document.getElementById('loginPasswordToggle');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (toggle) {
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      }
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const backgroundVideo = document.getElementById('loginBackgroundVideo');
    if (backgroundVideo && reduceMotion.matches) backgroundVideo.pause();
  </script>
</body>
</html>
