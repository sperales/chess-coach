<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; $u=require_login(); $gameId=(int)($_GET['id']??0); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Revisión de partida · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard review-page" data-game-id="<?= (int)$gameId ?>">
  <section class="hero-card compact review-hero">
    <div>
      <h1>Revisión de partida</h1>
      <p id="reviewIntro">Cargando análisis...</p>
    </div>
    <div class="hero-piece">♞</div>
  </section>

  <section class="review-summary-grid">
    <section class="panel review-summary-card">
      <div class="coach-comment">
        <div class="coach-avatar">♞</div>
        <div>
          <h2 id="reviewHeadline">Revisión de partida</h2>
          <p id="reviewComment">Preparando resumen...</p>
        </div>
      </div>
      <div class="review-kpis">
        <div><span>Accuracy</span><b id="accuracyValue">--</b></div>
        <div><span>ACPL</span><b id="acplValue">--</b></div>
        <div><span>Jugadas</span><b id="movesValue">--</b></div>
      </div>
    </section>

    <section class="panel review-counts-card">
      <h2>Resumen</h2>
      <div class="review-counts" id="reviewCounts"></div>
    </section>
  </section>

  <section class="panel review-chart-panel">
    <div class="panel-head">
      <h2>Gráfico de evaluación</h2>
      <span class="muted" id="chartHint">Evaluación normalizada a blancas · puntos críticos por color</span>
    </div>
    <canvas id="evalChart" width="1200" height="220" aria-label="Gráfico de evaluación"></canvas>
  </section>

  <section class="review-board-grid">
    <section class="panel">
      <div class="panel-head">
        <h2 id="moveTitle">Movimiento</h2>
        <span class="queue-status" id="moveBadge">--</span>
      </div>
      <div class="move-comment-card">
        <div>
          <strong id="moveSan">--</strong>
          <span id="moveEval">--</span>
        </div>
        <p id="moveExplanation">Selecciona una jugada para ver la explicación.</p>
      </div>
      <div class="board-wrap">
        <div class="chess-board" id="reviewBoard"></div>
      </div>
      <div class="review-controls">
        <button class="secondary" onclick="prevMove()">‹ Anterior</button>
        <button class="secondary" onclick="showBestMove()">Mejor</button>
        <button class="secondary" onclick="resetMove()">Reiniciar</button>
        <button onclick="nextMove()">Siguiente ›</button>
      </div>
    </section>

    <section class="panel move-list-panel">
      <div class="panel-head">
        <h2>Jugadas</h2>
        <a href="app.php#partidas">Volver</a>
      </div>
      <div class="move-list" id="moveList"></div>
    </section>
  </section>
</main>
</div>
<script>window.CHESS_REVIEW_GAME_ID = <?= (int)$gameId ?>;</script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/review.js"></script>
</body>
</html>
