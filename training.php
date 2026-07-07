<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$trainingJsVersion = (string)filemtime(__DIR__.'/assets/js/training.js');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Entrenamiento · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard training-page">
  <section class="hero-card compact">
    <div>
      <h1>Entrenamiento</h1>
      <p>Practica con posiciones reales generadas desde tus propias partidas analizadas.</p>
    </div>
    <div class="hero-piece">◎</div>
  </section>

  <section class="metric-grid" id="trainingStats"></section>

  <section class="panel games-filter-panel">
    <div class="panel-head">
      <h2>Qué entrenar</h2>
      <button class="secondary small" type="button" onclick="clearTrainingFilters()">Limpiar</button>
    </div>
    <div class="games-filter-grid">
      <label>Tipo
        <select id="trainingTypeFilter">
          <option value="recommended">Recomendado para mí</option>
        </select>
      </label>
      <label>Estado
        <select id="trainingStatusFilter">
          <option value="pending">Pendientes</option>
          <option value="resolved">Resueltos</option>
          <option value="all">Todos</option>
        </select>
      </label>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Ejercicios</h2>
      <span class="muted" id="trainingFilterStatus">Cargando...</span>
    </div>
    <div class="training-list" id="trainingExerciseList"></div>
    <div class="pagination" id="trainingPagination"></div>
    <div class="muted page-info" id="trainingPageInfo"></div>
  </section>
</main>
</div>
<script>window.CHESS_COACH_CONFIG = { trainingPerPage: 20 };</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/training.js?v=<?=e($trainingJsVersion)?>"></script>
</body>
</html>
