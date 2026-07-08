<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/motivational_quotes.php';

$u = require_login();
$quote = random_motivational_quote();
$assetVersion = (string)filemtime(__DIR__ . '/assets/css/app.css');
$dashboardJsVersion = (string)filemtime(__DIR__ . '/assets/js/dashboard.js');
$layoutJsVersion = (string)filemtime(__DIR__ . '/assets/js/layout.js');
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Buenos días' : ($hour < 21 ? 'Buenas tardes' : 'Buenas noches');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard trainer-dashboard">
  <section class="hero-card trainer-hero">
    <div>
      <h1><?=e($greeting)?>, <?=e($u['username'])?></h1>
      <p id="trainerHeroText">Preparando tu panel de entrenamiento...</p>
    </div>
    <div class="hero-piece">♛</div>
  </section>

  <section class="metric-grid" id="stats"></section>

  <section class="panel trainer-summary-panel">
    <h2>Resumen de últimas partidas</h2>
    <p class="trainer-summary-text" id="trainerSummary">Cargando resumen...</p>
    <div class="trainer-mini-kpis" id="trainerMiniKpis"></div>
  </section>

  <section class="trainer-grid">
    <section class="panel trainer-focus-panel">
      <div class="panel-head">
        <h2>Top 3 focos de entrenamiento</h2>
        <span class="muted" id="trainerPeriod">Últimas partidas</span>
      </div>
      <div class="trainer-focus-list" id="trainerFocusList"></div>
    </section>

    <section class="panel trainer-state-panel">
      <h2>Estado actual</h2>
      <div class="trainer-state" id="trainerState"></div>
      <hr class="soft-line">
      <h3>Siguiente acción</h3>
      <p class="muted" id="trainerNextAction">Cargando...</p>
      <hr class="soft-line">
      <h2>Fortalezas recientes</h2>
      <div class="trainer-strengths" id="trainerStrengths"></div>
    </section>
  </section>

  <section class="home-grid">
    <section class="panel" id="partidas">
      <div class="panel-head">
        <h2>Partidas</h2>
        <div class="panel-actions">
          <button class="secondary small active" type="button" id="latestTab" onclick="setGamesPanelMode('latest')">Últimas</button>
          <button class="secondary small" type="button" id="recommendedTab" onclick="setGamesPanelMode('recommended')">Recomendadas</button>
          <a id="gamesToggleLink" href="games.php">Ver todas</a>
        </div>
      </div>
      <table class="games">
        <thead>
          <tr>
            <th>Rival</th>
            <th>Resultado</th>
            <th id="gamesThirdColumnHeader">Ritmo</th>
            <th class="hide-sm">Fecha</th>
            <th>Análisis</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
      <div class="pagination" id="pagination"></div>
    </section>

    <section class="panel insight-card" id="smartTagInsight">
      <h2>Patrones detectados</h2>
      <p class="muted">Cargando patrones detectados...</p>
    </section>
  </section>

  <section class="quick-panel-wrap">
    <section class="panel quick-panel">
      <h2>Acciones rápidas</h2>
      <div class="quick-grid">
        <a class="quick-card green" href="import-chesscom.php"><span>⇩</span><strong>Importar partidas</strong><small>PGN o desde Chess.com</small></a>
        <button class="quick-card blue" type="button" onclick="analyzePendingVisible()"><span>▶</span><strong>Analizar pendientes</strong><small>Ver cola de análisis</small></button>
        <button class="quick-card purple" type="button" onclick="reviewLastGame()"><span>⌕</span><strong>Revisar última partida</strong><small>Ver análisis completo</small></button>
        <a class="quick-card amber" href="training.php"><span>◎</span><strong>Entrenamiento</strong><small>Ejercicios personalizados</small></a>
      </div>
    </section>
  </section>

  <section class="quote-panel panel">
    <span>"</span>
    <p><?=e($quote['quote_text'] ?? '')?><br><small>- <?=e($quote['author'] ?? '')?></small></p>
    <b>♞</b>
  </section>
</main>
</div>
<script>
window.CHESS_COACH_USERNAME = <?=json_encode($u['username'])?>;
window.CHESS_COACH_CONFIG = { gamesPerPage: <?php echo (int)(app_config()['games_per_page'] ?? 50); ?> };
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/dashboard.js?v=<?=e($dashboardJsVersion)?>"></script>
</body>
</html>
