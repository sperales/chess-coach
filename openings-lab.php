<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$openingsJsVersion = (string)filemtime(__DIR__.'/assets/js/openings_lab.js');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lab de Aperturas · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard openings-page">
  <section class="hero-card compact openings-hero">
    <div>
      <h1>Lab de Aperturas</h1>
      <p>Entiende qué aperturas te están funcionando y dónde aparecen tus errores tempranos.</p>
    </div>
    <div class="hero-piece">♙</div>
  </section>

  <section class="metric-grid" id="openingsSummary"></section>

  <section class="panel games-filter-panel openings-view-panel is-collapsed" id="openingsViewPanel">
    <div class="panel-head">
      <h2>Vista</h2>
      <div class="openings-view-head-actions">
        <span class="muted" id="openingsStatus">Cargando...</span>
        <button class="btn secondary small openings-view-toggle" type="button" id="openingsViewToggle" aria-expanded="false" aria-controls="openingsViewBody">
          <span class="openings-view-toggle-label">Mostrar filtros</span>
          <span class="openings-view-toggle-icon" aria-hidden="true">▾</span>
        </button>
      </div>
    </div>
    <div class="games-filter-grid openings-view-body" id="openingsViewBody">
      <label>Mínimo de partidas
        <select id="openingsMinGames">
          <option value="1">Todas</option>
          <option value="3" selected>3 o más</option>
          <option value="5">5 o más</option>
        </select>
      </label>
    </div>
  </section>

  <div class="openings-layout">
    <section class="panel">
      <div class="panel-head">
        <h2>Aperturas detectadas</h2>
        <span class="muted" id="openingsCount"></span>
      </div>
      <div class="openings-list" id="openingsList"></div>
    </section>

    <section class="panel openings-detail-panel">
      <div class="panel-head">
        <h2>Detalle</h2>
      </div>
      <div id="openingDetail" class="opening-detail">
        <div class="empty-state compact">
          <strong>Selecciona una apertura</strong>
          <span>Verás resultados, precisión de apertura, errores tempranos y partidas ejemplo.</span>
        </div>
      </div>
    </section>
  </div>
</main>
</div>
<script>window.CHESS_COACH_USERNAME = <?=json_encode($u['username'])?>;</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/openings_lab.js?v=<?=e($openingsJsVersion)?>"></script>
</body>
</html>
