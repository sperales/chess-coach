<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$trainingJsVersion = (string)filemtime(__DIR__.'/assets/js/training.js');
$pieceSetAssetPath = piece_set_asset_path($u['piece_set'] ?? null);
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

  <section class="panel training-session-panel" id="trainingSessionPanel">
    <div class="panel-head">
      <h2>Sesión de entrenamiento</h2>
      <div class="review-board-actions">
        <button class="secondary small" type="button" id="trainingNewSessionBtn" onclick="newTrainingSession()">Nueva sesión</button>
      </div>
    </div>
    <div class="training-session-summary" id="trainingSessionSummary">
      <span>Preparando sesión...</span>
      <strong>Tu entrenamiento quedará medido automáticamente.</strong>
    </div>
    <div class="trainer-mini-kpis training-session-kpis" id="trainingSessionKpis"></div>
  </section>

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

  <section class="panel training-solver-panel" id="trainingSolverPanel" hidden>
    <div class="panel-head">
      <h2 id="trainingSolverTitle">Resolver ejercicio</h2>
      <div class="review-board-actions">
        <button class="secondary small" type="button" onclick="flipTrainingBoard()">Girar tablero</button>
        <button class="secondary small" type="button" onclick="closeTrainingSolver()">Cerrar</button>
      </div>
    </div>
    <div class="training-solver-grid">
      <div class="board-wrap">
        <div class="chess-board training-board" id="trainingBoard" aria-label="Tablero de entrenamiento"></div>
      </div>
      <div class="training-solver-info">
        <p class="trainer-summary-text" id="trainingSolverPrompt">Selecciona un ejercicio para empezar.</p>
        <div class="smart-tag-list training-tags" id="trainingSolverTags"></div>
        <div class="training-meta" id="trainingSolverMeta"></div>
        <div class="training-exercise-timer" id="trainingExerciseTimer">00:00</div>
        <p class="muted" id="trainingMoveDraft">Selecciona origen y destino en el tablero.</p>
        <label class="training-promotion" id="trainingPromotionWrap" hidden>Promoción
          <select id="trainingPromotionPiece">
            <option value="q">Dama</option>
            <option value="r">Torre</option>
            <option value="b">Alfil</option>
            <option value="n">Caballo</option>
          </select>
        </label>
        <p id="trainingFeedback" class="training-feedback"></p>
        <div class="training-attempts" id="trainingAttempts"></div>
        <div class="review-controls training-controls" id="trainingActiveControls">
          <button type="button" onclick="submitTrainingMove()" id="trainingSubmitBtn" disabled>Comprobar</button>
          <button type="button" onclick="showTrainingHint()" id="trainingHintBtn">Pista</button>
          <button type="button" onclick="skipTrainingExercise()" id="trainingSkipBtn">Saltar</button>
        </div>
        <div class="review-controls training-controls" id="trainingDoneControls" hidden>
          <button type="button" onclick="openNextTrainingExercise()">Siguiente</button>
          <button class="secondary" type="button" onclick="closeTrainingSolver()">Cerrar</button>
          <a class="btn secondary" href="#" id="trainingReviewLink">Ver partida</a>
        </div>
      </div>
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
<script>
window.CHESS_COACH_CONFIG = { trainingPerPage: 20 };
window.CHESS_COACH_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_COACH_PIECE_PATH = <?= json_encode($pieceSetAssetPath, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/training.js?v=<?=e($trainingJsVersion)?>"></script>
</body>
</html>
