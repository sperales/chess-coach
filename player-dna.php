<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__ . '/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__ . '/assets/js/layout.js');
$playerDnaJsVersion = (string)filemtime(__DIR__ . '/assets/js/player_dna.js');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ADN del jugador · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard player-dna-page">
  <section class="hero-card compact player-dna-hero">
    <div>
      <h1>ADN del jugador</h1>
      <p id="playerDnaHeroText">Leyendo tu perfil de juego...</p>
    </div>
    <div class="hero-piece">♞</div>
  </section>

  <section class="panel player-dna-summary" id="playerDnaSummary">
    <p class="muted">Cargando snapshot...</p>
  </section>

  <section class="player-dna-grid">
    <section class="panel player-dna-main-panel">
      <div class="panel-head">
        <h2>Dimensiones del perfil</h2>
        <span class="muted" id="playerDnaPeriod">Últimas partidas</span>
      </div>
      <div class="player-dna-dimensions" id="playerDnaDimensions"></div>
    </section>

    <section class="panel player-dna-side-panel">
      <h2>Fortalezas</h2>
      <div class="player-dna-list" id="playerDnaStrengths"></div>
      <hr class="soft-line">
      <h2>Debilidades</h2>
      <div class="player-dna-list" id="playerDnaWeaknesses"></div>
    </section>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Indicadores de estilo</h2>
      <span class="muted">Lectura aproximada, no etiqueta fija</span>
    </div>
    <div class="player-dna-style" id="playerDnaStyle"></div>
  </section>

  <section class="player-dna-grid compact">
    <section class="panel">
      <h2>Comparativa histórica</h2>
      <div class="player-dna-comparisons" id="playerDnaComparisons"></div>
    </section>

    <section class="panel">
      <h2>Siguiente paso</h2>
      <div class="player-dna-next" id="playerDnaNext"></div>
    </section>
  </section>
</main>
</div>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/player_dna.js?v=<?=e($playerDnaJsVersion)?>"></script>
</body>
</html>
