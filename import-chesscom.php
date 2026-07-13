<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$appJsVersion = (string)filemtime(__DIR__.'/assets/js/app.js');
$chesscomJsVersion = (string)filemtime(__DIR__.'/assets/js/chesscom.js');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Importar partidas · Chess Coach</title>
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard import-page">
  <section class="hero-card compact import-hero">
    <div>
      <h1>Importar partidas</h1>
      <p>Añade partidas pegando PGN o importándolas desde Chess.com. Las nuevas partidas quedarán en cola de análisis automáticamente.</p>
    </div>
    <div class="hero-piece">⇩</div>
  </section>

  <section class="import-grid import-workspace">
    <section class="panel import-panel import-card">
      <div class="import-panel-head">
        <span class="import-panel-icon" aria-hidden="true">📄</span>
        <span>Importar PGN</span>
      </div>
      <div class="import-body">
        <p class="import-panel-copy">Pega aquí uno o varios PGN para añadir tus partidas.</p>
        <textarea id="pgn" maxlength="200000" placeholder="Pega aquí uno o varios PGN..."></textarea>
        <div class="import-card-footer">
          <button onclick="importPgn()">Importar PGN</button>
          <span class="import-help">Máx. recomendado: 5 MB de texto.<br>Separa partidas con una línea en blanco.</span>
          <span class="muted" id="msg"></span>
        </div>
      </div>
    </section>

    <section class="panel import-panel">
      <div class="import-panel-title">
        <span class="import-panel-icon" aria-hidden="true">☁↓</span>
        <h2>Importar desde Chess.com</h2>
      </div>
      <p class="import-panel-copy">Importa las partidas más recientes desde la API pública de Chess.com. Se evitarán duplicados y las nuevas quedarán en cola de análisis automáticamente.</p>
      <div class="import-field">
        <label>Usuario Chess.com</label>
        <input id="ccUser" value="<?=e($u['username'])?>" autocomplete="off">
      </div>
      <div class="import-field">
        <label>Número de partidas</label>
        <input id="ccLimit" type="number" min="1" max="100" value="20">
        <small class="muted">Puedes importar entre 1 y 100 partidas por solicitud.</small>
      </div>
      <div class="import-card-footer import-card-footer-stacked">
        <button onclick="importChessCom()">Importar desde Chess.com</button>
        <span class="muted" id="ccMsg"></span>
      </div>
    </section>
  </section>

  <section class="panel import-info-panel">
    <span class="import-info-icon" aria-hidden="true">!</span>
    <div>
      <h2>¿Cómo funciona?</h2>
      <p>Las partidas importadas se añaden a tu cola de análisis. En unos minutos estarán disponibles para revisar, aprender y mejorar.</p>
    </div>
  </section>
</main>
</div>
<?= csrf_script() ?>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/app.js?v=<?=e($appJsVersion)?>"></script>
<script src="assets/js/chesscom.js?v=<?=e($chesscomJsVersion)?>"></script>
</body>
</html>
