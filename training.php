<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';
require_once __DIR__.'/includes/training.php';

$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$trainingJsVersion = (string)filemtime(__DIR__.'/assets/js/training.js');
$pieceSetAssetPath = piece_set_asset_path($u['piece_set'] ?? null);
$boardThemeClass = board_theme_class($u['board_theme'] ?? null);
$trainingPreferences = training_goal_settings_for_user((int)$u['id']);
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
<body class="dark-shell <?=e($boardThemeClass)?>">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard training-page training-mobile-first">
  <section class="hero-card compact training-hero training-mobile-intro">
    <div>
      <h1>Entrenamiento</h1>
      <p>Elige cómo quieres entrenar hoy.</p>
    </div>
    <div class="hero-piece">◎</div>
  </section>

  <section class="metric-grid training-legacy-summary" id="trainingStats"></section>

  <section class="panel training-experience-panel training-legacy-summary" id="trainingExperiencePanel">
    <div class="panel-head">
      <h2>Entrenamiento de hoy</h2>
      <div class="review-board-actions">
        <a class="btn secondary small" href="profile.php">Cambiar objetivo</a>
      </div>
    </div>
    <div class="training-experience-summary" id="trainingExperienceSummary">
      <span>Preparando entrenamiento...</span>
      <strong>Chess Coach medirá tu progreso automáticamente.</strong>
    </div>
    <div class="training-experience-grid" id="trainingExperienceGrid"></div>
    <div class="training-repeat-list" id="trainingRepeatList"></div>
  </section>

  <section class="panel games-filter-panel training-focus-panel">
    <div class="panel-head">
      <h2>Qué entrenar</h2>
      <button class="secondary small" type="button" onclick="clearTrainingFilters()">Limpiar</button>
    </div>
    <div class="games-filter-grid">
      <label>Plan de Nova
        <select id="trainingTypeFilter">
          <option value="recommended">Recomendado para mí</option>
        </select>
      </label>
      <label>Estado
        <select id="trainingStatusFilter">
          <option value="pending">Pendientes</option>
          <option value="failed">Fallados</option>
          <option value="resolved">Resueltos</option>
          <option value="all">Todos</option>
        </select>
      </label>
    </div>
  </section>

  <section class="training-nova-proposal" aria-labelledby="trainingNovaTitle">
    <div class="training-nova-copy">
      <h2 id="trainingNovaTitle">Nova te propone tu entrenamiento</h2>
      <p id="trainingNovaMessage">Estoy preparando un plan basado en tu progreso reciente.</p>
      <span class="training-nova-badge">★ Recomendado para ti</span>
      <div class="training-nova-metrics" id="trainingNovaMetrics"></div>
    </div>
    <img src="assets/nova/nova-coach-pointing.png" alt="Nova, tu coach" class="training-nova-image">
    <p class="training-nova-speech" id="trainingNovaSpeech">¡Vamos a entrenar!</p>
    <a class="btn training-nova-start" id="trainingNovaStart" href="#trainingContinue">▶ <span>Empezar entrenamiento</span></a>
  </section>

  <section class="training-category-section" aria-labelledby="trainingCategoryTitle">
    <h2 id="trainingCategoryTitle">Entrena por categoría</h2>
    <p>Elige el tipo de entrenamiento que mejor se adapta a tu objetivo de hoy.</p>
    <div class="training-category-list" id="trainingCategoryList"></div>
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
        <div class="training-hints-panel" id="trainingHintsPanel" hidden aria-live="polite">
          <div class="training-hints-head">
            <strong>Pistas progresivas</strong>
            <span id="trainingHintsProgress">0/3</span>
          </div>
          <div class="training-hints-list" id="trainingHintsList"></div>
        </div>
        <div class="review-controls training-controls" id="trainingActiveControls">
          <button type="button" onclick="submitTrainingMove()" id="trainingSubmitBtn" disabled>Comprobar</button>
          <button type="button" onclick="showTrainingHint()" id="trainingHintBtn">Pista 1/3</button>
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

  <section class="panel training-continue-section" id="trainingContinue">
    <div class="panel-head training-section-head">
      <div>
        <h2>Ejercicios disponibles</h2>
        <p id="trainingAvailableDescription">Elige una posición para entrenar.</p>
      </div>
      <div class="training-available-actions">
        <a class="btn secondary small" id="trainingOpeningsLabLink" href="openings-lab.php" hidden>Abrir Lab de Aperturas</a>
        <span class="muted" id="trainingFilterStatus">Cargando...</span>
      </div>
    </div>
    <div class="training-list" id="trainingExerciseList"></div>
    <div class="pagination" id="trainingPagination"></div>
    <div class="muted page-info" id="trainingPageInfo"></div>
  </section>

  <section class="training-coach-tip">
    <span class="training-coach-tip-icon" aria-hidden="true">♙</span>
    <div>
      <h2>Consejo del Coach</h2>
      <p id="trainingCoachTip">Entrena con calma y busca la idea antes de mover.</p>
    </div>
    <a href="player-dna.php">Ver mi ADN <span aria-hidden="true">›</span></a>
  </section>
</main>
</div>
<script>
window.CHESS_COACH_CONFIG = { trainingPerPage: 20 };
window.CHESS_COACH_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_COACH_PIECE_PATH = <?= json_encode($pieceSetAssetPath, JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_TRAINING_PREFERENCES = <?= json_encode([
  'showLegalMoves' => !empty($trainingPreferences['show_legal_moves']),
  'autoSubmitMove' => !empty($trainingPreferences['auto_submit_move']),
], JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_TRAINING_INITIAL_PLAN_ID = 0;
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/training.js?v=<?=e($trainingJsVersion)?>"></script>
</body>
</html>
