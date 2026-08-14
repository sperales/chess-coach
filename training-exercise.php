<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';
require_once __DIR__.'/includes/training.php';

$u = require_login();
$exerciseId = max(0, (int)($_GET['id'] ?? $_GET['exercise_id'] ?? 0));
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
  <title>Resolver ejercicio · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell training-exercise-page <?=e($boardThemeClass)?>">
<?php header_bar('Entrenamiento', 'training.php'); ?>
<div class="app-area">
<main class="dashboard training-solve-page <?=!empty($trainingPreferences['auto_submit_move']) ? 'training-auto-submit' : 'training-manual-submit'?>">
  <a class="training-back-link" href="training.php">← Entrenamiento</a>

  <section class="training-mobile-progress" aria-label="Progreso del entrenamiento">
    <div class="training-mobile-progress-track" id="trainingMobileExerciseTrack"></div>
    <span id="trainingMobileExercisePosition">Ejercicio 1 de 1</span>
  </section>

  <section class="hero-card compact training-hero training-solver-hero">
    <div class="training-solver-hero-copy">
      <div class="coach-comment">
        <div class="coach-avatar">♞</div>
        <div>
          <h1 id="trainingSolverHeroTitle">Cargando ejercicio</h1>
          <p id="trainingSolverHeroPrompt">Preparando la posición de entrenamiento.</p>
          <strong id="trainingSolverHeroSide">Juegan blancas</strong>
        </div>
      </div>
    </div>
    <div class="training-mobile-hero-meta">
      <span><i aria-hidden="true"></i><strong id="trainingMobileSide">Juegan blancas</strong></span>
      <span aria-hidden="true">·</span>
      <span id="trainingMobileDifficulty">Intermedio</span>
      <div class="training-exercise-timer" id="trainingMobileExerciseTimer">00:00</div>
    </div>
    <div class="hero-piece">◎</div>
  </section>

  <section class="panel training-experience-panel training-solve-experience" id="trainingExperiencePanel">
    <div class="training-session-summary" id="trainingSessionSummary">
      <span>Preparando sesión...</span>
      <strong>Tu entrenamiento quedará medido automáticamente.</strong>
    </div>
    <div class="trainer-mini-kpis training-session-kpis" id="trainingSessionKpis"></div>
  </section>

  <section class="panel training-solver-panel training-solve-workspace" id="trainingSolverPanel">
    <div class="training-solve-board">
      <div class="board-wrap">
        <div class="board-coordinate-frame training-board-frame" id="trainingBoardFrame">
          <div class="board-rank-labels" id="trainingBoardRanks" aria-hidden="true"></div>
          <div class="chess-board training-board" id="trainingBoard" aria-label="Tablero de entrenamiento"></div>
          <div class="board-file-labels" id="trainingBoardFiles" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <section class="training-mobile-coach nova-state-objective" id="trainingMobileCoach" aria-live="polite">
      <span class="nova-avatar nova-avatar--neutral" id="trainingMobileNova" aria-hidden="true"></span>
      <div class="training-mobile-coach-copy">
        <div><strong id="trainingMobileCoachTitle">Objetivo</strong><span id="trainingMobileCoachStep">1 de 1</span></div>
        <p id="trainingMobileCoachText">Encuentra la mejor jugada en esta posición.</p>
        <div class="training-mobile-coach-dots" id="trainingMobileCoachDots" aria-label="Mensajes de Nova"><button type="button" class="active" aria-label="Mensaje 1 de 2"></button><button type="button" aria-label="Mensaje 2 de 2"></button></div>
      </div>
    </section>

    <div class="training-mobile-actions" id="trainingMobileActiveControls">
      <button class="training-mobile-check" type="button" onclick="submitTrainingMove()" id="trainingMobileSubmitBtn" disabled>Comprobar</button>
      <button class="secondary" type="button" onclick="showTrainingExplanation()">¿Por qué?</button>
      <button class="secondary" type="button" onclick="showTrainingHint()" id="trainingMobileHintBtn">Ayuda</button>
    </div>
    <div class="training-mobile-actions training-mobile-done" id="trainingMobileDoneControls" hidden>
      <button type="button" onclick="openNextTrainingExercise()">Siguiente ejercicio</button>
    </div>

    <section class="training-mobile-summary">
      <div class="training-mobile-summary-row">
        <strong>Progreso del módulo</strong>
        <span class="training-mobile-mini-progress"><i id="trainingMobileModuleBar"></i></span>
        <b id="trainingMobileModuleProgress">0 de 0</b>
      </div>
      <div class="training-mobile-summary-row">
        <strong>Objetivos de hoy</strong>
        <span class="training-mobile-mini-progress"><i id="trainingMobileTodayBar"></i></span>
        <b id="trainingMobileTodayProgress">0 de 0</b>
      </div>
      <a class="training-mobile-summary-row" href="#" id="trainingMobileOriginLink">
        <strong>Partida origen</strong>
        <span id="trainingMobileOpening">Apertura sin identificar</span>
        <b aria-hidden="true">›</b>
      </a>
    </section>

    <div class="training-mobile-footer-actions">
      <button class="secondary" type="button" onclick="clearTrainingSelection()" aria-label="Reiniciar selección">↺</button>
      <button class="secondary" type="button" onclick="skipTrainingExercise()">Saltar ejercicio</button>
    </div>

    <aside class="training-solve-sidebar">
      <div class="training-side-objective" id="trainingSideObjective">Selecciona un ejercicio para empezar.</div>
      <div class="training-solve-topline">
        <span class="queue-status queued" id="trainingSolverStatus">Pendiente</span>
        <div class="training-exercise-timer" id="trainingExerciseTimer">00:00</div>
      </div>

      <div class="training-side-kpis">
        <div><span>Intentos</span><b id="trainingAttemptsCount">0/5</b></div>
        <div><span>Dificultad</span><b class="training-difficulty-bars" id="trainingDifficultyBars"></b></div>
        <div><span>Prioridad</span><b id="trainingPriorityValue">0</b></div>
      </div>

      <div class="training-source-box">
        <span>Partida origen</span>
        <strong id="trainingSourceGame">-</strong>
        <small id="trainingSourceDate"></small>
        <small id="trainingSourceMove"></small>
        <div class="smart-tag-list training-tags" id="trainingSolverTags"></div>
      </div>

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

      <div class="review-controls training-controls training-solve-controls" id="trainingActiveControls">
        <button type="button" onclick="submitTrainingMove()" id="trainingSubmitBtn" disabled>Comprobar</button>
        <button class="secondary" type="button" onclick="showTrainingHint()" id="trainingHintBtn">Pista 1/3</button>
        <button class="secondary" type="button" onclick="skipTrainingExercise()" id="trainingSkipBtn">Saltar</button>
      </div>
      <div class="review-controls training-controls training-solve-controls" id="trainingDoneControls" hidden>
        <button type="button" onclick="openNextTrainingExercise()">Siguiente</button>
        <button class="secondary" type="button" onclick="closeTrainingSolver()">Cerrar</button>
        <a class="btn secondary" href="#" id="trainingReviewLink">Ver partida</a>
      </div>
    </aside>
  </section>

  <section class="panel training-solve-toolbar">
    <button class="secondary small" type="button" onclick="clearTrainingSelection()">↺</button>
    <button class="secondary small" type="button" onclick="flipTrainingBoard()">⛶</button>
    <div>Movimiento correcto <strong id="trainingCorrectMove">-</strong></div>
    <a class="btn secondary small" href="training.php">Volver al listado</a>
  </section>

  <details class="panel training-detail-panel" id="trainingExerciseDetails" open>
    <summary>Detalles del ejercicio</summary>
    <div class="training-detail-grid">
      <div><span>Objetivo</span><strong id="trainingDetailsObjective">-</strong></div>
      <div><span>Tema</span><strong id="trainingDetailsTheme">-</strong></div>
      <div><span>Nivel estimado</span><strong id="trainingDetailsLevel">-</strong></div>
      <div><span>Prioridad</span><strong id="trainingDetailsPriority">-</strong></div>
    </div>
  </details>

  <details class="panel training-detail-panel" id="trainingOriginDetails">
    <summary>Partida origen</summary>
    <div class="training-origin-review-grid" id="trainingOriginReviewGrid">
      <div class="empty-state compact">
        <strong>Cargando partida origen...</strong>
        <span>Preparando el resumen del análisis.</span>
      </div>
    </div>
  </details>

  <details class="panel training-detail-panel">
    <summary>Historial de intentos</summary>
    <div class="training-attempts" id="trainingAttemptHistory"></div>
  </details>

  <section class="panel training-solve-tip">
    <strong>Consejo</strong>
    <span>Analiza los recursos del rival y busca la defensa más precisa.</span>
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
window.CHESS_TRAINING_SOLVER_MODE = true;
window.CHESS_TRAINING_INITIAL_EXERCISE_ID = <?= (int)$exerciseId ?>;
if (window.matchMedia('(max-width: 760px)').matches) {
  document.getElementById('trainingExerciseDetails')?.removeAttribute('open');
}
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/training.js?v=<?=e($trainingJsVersion)?>"></script>
</body>
</html>
