<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';

$u = require_login();
$gameId = (int)($_GET['id'] ?? 0);
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$reviewJsVersion = (string)filemtime(__DIR__.'/assets/js/review.js');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$pieceSetAssetPath = piece_set_asset_path($u['piece_set'] ?? null);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Revisión de partida · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
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
          <div class="smart-tag-list review-tags" id="reviewSmartTags"></div>
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
        <div class="review-board-actions">
          <button class="secondary small" id="flipBoardBtn" type="button">Girar tablero</button>
          <span class="queue-status" id="moveBadge">--</span>
        </div>
      </div>
      <div class="move-comment-card">
        <div>
          <strong id="moveSan">--</strong>
          <span id="moveEval">--</span>
        </div>
        <p id="moveExplanation">Selecciona una jugada para ver la explicación.</p>
        <div class="smart-tag-list move-tags" id="moveSmartTags"></div>
      </div>
      <div class="board-wrap">
        <div class="board-coordinate-frame" id="reviewBoardFrame">
          <div class="board-rank-labels" id="reviewBoardRanks" aria-hidden="true"></div>
          <div class="chess-board" id="reviewBoard"></div>
          <div class="board-file-labels" id="reviewBoardFiles" aria-hidden="true"></div>
        </div>
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

  <section class="review-bottom-panel">
    <section class="panel review-coach-tip">
      <div class="review-tip-icon" aria-hidden="true">!</div>
      <div>
        <h2>Consejo del coach</h2>
        <p id="reviewCoachTip">Cargando consejo...</p>
      </div>
    </section>

    <section class="review-insights-panel">
      <div class="review-insights-heading">
        <h2>Insights rápidos</h2>
      </div>
      <div class="review-insights-grid" id="reviewInsights"></div>
    </section>
  </section>
</main>
</div>
<script>
window.CHESS_REVIEW_GAME_ID = <?= (int)$gameId ?>;
window.CHESS_COACH_PIECE_PATH = <?= json_encode($pieceSetAssetPath, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/review.js?v=<?=e($reviewJsVersion)?>"></script>
</body>
</html>
