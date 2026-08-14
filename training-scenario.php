<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';

$u = require_login();
$scenarioId = max(0, (int)($_GET['id'] ?? 0));
$trainingId = max(0, (int)($_GET['training_id'] ?? 0));
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$scenarioJsVersion = (string)filemtime(__DIR__.'/assets/js/training-scenario.js');
$pieceSetAssetPath = piece_set_asset_path($u['piece_set'] ?? null);
$boardThemeClass = board_theme_class($u['board_theme'] ?? null);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Resolver escenario · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell training-exercise-page training-scenario-page <?=e($boardThemeClass)?>">
<?php header_bar('Entrenamiento', 'training.php'); ?>
<div class="app-area">
<main class="dashboard training-solve-page training-scenario-solve-page">
  <a class="training-back-link" href="training.php">← Entrenamiento</a>

  <section class="training-mobile-progress" aria-label="Progreso del plan de hoy">
    <div class="training-mobile-progress-track" id="scenarioPlanTrack"></div>
    <span id="scenarioPlanPosition">Escenario</span>
  </section>

  <section class="hero-card compact training-hero training-solver-hero training-scenario-hero">
    <div class="training-solver-hero-copy">
      <span class="training-scenario-kicker">Escenario · varias jugadas</span>
      <h1 id="scenarioTitle">Preparando escenario</h1>
      <p id="scenarioPrompt">Nova está recuperando una posición de una de tus partidas.</p>
    </div>
    <div class="training-mobile-hero-meta">
      <span><i aria-hidden="true"></i><strong id="scenarioSide">Juegan blancas</strong></span>
      <span aria-hidden="true">·</span>
      <span id="scenarioDifficulty">Intermedio</span>
      <div class="training-exercise-timer" id="scenarioTimer">00:00</div>
    </div>
  </section>

  <section class="panel training-solver-panel training-solve-workspace training-scenario-workspace">
    <div class="training-solve-board">
      <div class="board-wrap">
        <div class="chess-board training-board training-scenario-board" id="scenarioBoard" aria-label="Tablero del escenario"></div>
      </div>
    </div>

    <section class="training-mobile-coach nova-state-neutral training-scenario-coach" id="scenarioCoach" aria-live="polite">
      <span class="nova-avatar nova-avatar--neutral" id="scenarioNova" aria-hidden="true"></span>
      <div class="training-mobile-coach-copy">
        <div><strong id="scenarioCoachTitle">Objetivo</strong><span id="scenarioCoachStep">1 de 1</span></div>
        <p id="scenarioCoachText">Analiza la posición antes de elegir tu primera jugada.</p>
        <div class="training-mobile-coach-dots" id="scenarioCoachDots" aria-label="Mensajes de Nova"></div>
      </div>
    </section>

    <div class="training-scenario-draft" id="scenarioDraft">Selecciona origen y destino en el tablero.</div>
    <div class="training-mobile-actions training-scenario-primary-actions" id="scenarioActiveControls">
      <button type="button" id="scenarioSubmit" disabled>Comprobar</button>
      <button class="secondary" type="button" id="scenarioWhy">¿Por qué?</button>
      <button class="secondary" type="button" id="scenarioHint">Ayuda</button>
    </div>
    <div class="training-mobile-actions training-mobile-done" id="scenarioDoneControls" hidden>
      <button type="button" id="scenarioNext">Siguiente ejercicio</button>
    </div>

    <section class="training-mobile-summary training-scenario-summary">
      <div class="training-mobile-summary-row">
        <strong>Plan de hoy</strong>
        <span class="training-mobile-mini-progress"><i id="scenarioPlanBar"></i></span>
        <b id="scenarioPlanProgress">0 de 0</b>
      </div>
      <div class="training-mobile-summary-row">
        <strong>Decisiones</strong>
        <span id="scenarioDecisionText">0 de 0</span>
        <b id="scenarioAttempts">0 intentos</b>
      </div>
      <a class="training-mobile-summary-row" href="#" id="scenarioOriginLink">
        <strong>Partida origen</strong>
        <span id="scenarioOrigin">Cargando...</span>
        <b aria-hidden="true">›</b>
      </a>
    </section>

    <div class="training-mobile-footer-actions">
      <button class="secondary" type="button" id="scenarioFlip">Girar tablero</button>
      <button class="secondary" type="button" id="scenarioSkip">Saltar escenario</button>
    </div>
  </section>

  <details class="panel training-detail-panel" open>
    <summary>Detalles del escenario</summary>
    <div class="training-detail-grid">
      <div><span>Objetivo</span><strong id="scenarioDetailObjective">-</strong></div>
      <div><span>Tipo</span><strong id="scenarioDetailType">-</strong></div>
      <div><span>Nivel estimado</span><strong id="scenarioDetailDifficulty">-</strong></div>
      <div><span>Origen</span><strong>Partida real</strong></div>
    </div>
  </details>
</main>
</div>
<script>
window.CHESS_COACH_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_COACH_PIECE_PATH = <?= json_encode($pieceSetAssetPath, JSON_UNESCAPED_SLASHES) ?>;
window.CHESS_TRAINING_SCENARIO_ID = <?= (int)$scenarioId ?>;
window.CHESS_TRAINING_PLAN_ID = <?= (int)$trainingId ?>;
</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/training-scenario.js?v=<?=e($scenarioJsVersion)?>"></script>
</body>
</html>
