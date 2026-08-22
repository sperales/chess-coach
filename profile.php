<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/training.php';
require_once __DIR__.'/includes/openings.php';
require_once __DIR__.'/includes/player_progress.php';
require_once __DIR__.'/includes/pieces.php';
require_once __DIR__.'/includes/training_opportunities.php';

$u = require_login();
$msg = '';
$err = '';
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$pendingTrainingFoundation = training_opportunity_backfill_pending_count((int)$u['id']);
$pendingOpeningProfiles = openings_profile_pending_count((int)$u['id']);
$pieceSets = available_piece_sets();
$currentPieceSet = normalize_piece_set($u['piece_set'] ?? null);
$boardThemes = board_theme_options();
$currentBoardTheme = normalize_board_theme($u['board_theme'] ?? null);
$trainingGoalSettings = training_goal_settings_for_user((int)$u['id']);
$playerProgress = player_progress_latest((int)$u['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf_token();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'change_password') {
  $cur = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $new2 = $_POST['new_password2'] ?? '';
  $st = db()->prepare('SELECT password_hash FROM users WHERE id=?');
  $st->execute([$u['id']]);
  $hash = $st->fetchColumn();
  if (!password_verify($cur, $hash)) {
    $err = 'La contraseña actual no es correcta.';
  } elseif (strlen($new) < (security_config()['min_password_length'] ?? 10)) {
    $err = 'La nueva contraseña debe tener al menos 10 caracteres.';
  } elseif ($new !== $new2) {
    $err = 'Las nuevas contraseñas no coinciden.';
  } else {
    $st = db()->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
    $st->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    $msg = 'Contraseña actualizada.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['profile_action'] ?? ''), ['change_piece_set', 'save_board_preferences'], true)) {
  $selectedPieceSet = normalize_piece_set($_POST['piece_set'] ?? '');
  $selectedBoardTheme = normalize_board_theme($_POST['board_theme'] ?? $currentBoardTheme);
  if (!$selectedPieceSet) {
    $err = 'No hay sets de piezas disponibles.';
  } else {
    $st = db()->prepare('UPDATE users SET piece_set=?, board_theme=?, updated_at=NOW() WHERE id=?');
    $st->execute([$selectedPieceSet, $selectedBoardTheme, $u['id']]);
    $u['piece_set'] = $selectedPieceSet;
    $u['board_theme'] = $selectedBoardTheme;
    $currentPieceSet = $selectedPieceSet;
    $currentBoardTheme = $selectedBoardTheme;
    $msg = 'Preferencias del tablero actualizadas.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'save_training_goals') {
  $trainingGoalSettings = training_save_goal_settings((int)$u['id'], [
    'daily_goal_mode' => $_POST['daily_goal_mode'] ?? '',
    'daily_exercise_goal' => $_POST['daily_exercise_goal'] ?? null,
    'daily_minutes_goal' => $_POST['daily_minutes_goal'] ?? null,
    'weekly_training_days_goal' => $_POST['weekly_training_days_goal'] ?? null,
    'weekly_exercise_goal' => $_POST['weekly_exercise_goal'] ?? null,
  ]);
  $msg = 'Objetivo de entrenamiento actualizado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'save_training_preferences') {
  $trainingGoalSettings = training_save_preferences((int)$u['id'], [
    'show_legal_moves' => isset($_POST['show_legal_moves']),
    'auto_submit_move' => isset($_POST['auto_submit_move']),
  ]);
  $msg = 'Preferencias de entrenamiento actualizadas.';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mi Perfil</title>
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell profile-page <?=e(board_theme_class($currentBoardTheme))?>">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
  <main class="wrap">
    <section class="card">
      <h2>Mi Perfil</h2>
      <p class="muted">Usuario: <?=e($u['username'])?></p>
      <?php if ($msg): ?><p class="ok"><?=e($msg)?></p><?php endif; ?>
      <?php if ($err): ?><p class="error"><?=e($err)?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="profile_action" value="change_password">
        <?= csrf_field() ?>
        <p><input type="password" name="current_password" placeholder="Contraseña actual" required></p>
        <p><input type="password" name="new_password" placeholder="Nueva contraseña" required></p>
        <p><input type="password" name="new_password2" placeholder="Repetir nueva contraseña" required></p>
        <button>Cambiar contraseña</button>
        <a class="btn secondary" href="app.php">Volver</a>
      </form>
    </section>

    <section class="card">
      <h2>Tablero</h2>
      <p class="muted">Elige el set de piezas y el color del tablero que se usarán en revisión y entrenamiento.</p>
      <form method="post">
        <input type="hidden" name="profile_action" value="save_board_preferences">
        <?= csrf_field() ?>
        <h3 class="board-preference-heading">Set de piezas</h3>
        <div class="piece-set-options">
          <?php foreach ($pieceSets as $set): ?>
            <label class="piece-set-option">
              <input type="radio" name="piece_set" value="<?=e($set)?>" <?= $set === $currentPieceSet ? 'checked' : '' ?>>
              <span>
                <strong><?=e($set)?></strong>
                <span class="piece-set-preview" aria-hidden="true">
                  <?php foreach (['wp','wn','wb','wr','wq','wk'] as $piece): ?>
                    <img src="<?=e(piece_set_asset_path($set))?><?=e($piece)?>.png" alt="" draggable="false">
                  <?php endforeach; ?>
                </span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <h3 class="board-preference-heading">Color del tablero</h3>
        <div class="board-theme-options">
          <?php foreach ($boardThemes as $theme => $label): ?>
            <label class="board-theme-option <?=e(board_theme_class($theme))?>">
              <input type="radio" name="board_theme" value="<?=e($theme)?>" <?= $theme === $currentBoardTheme ? 'checked' : '' ?>>
              <span>
                <span class="board-theme-preview" aria-hidden="true">
                  <?php for ($square = 0; $square < 8; $square++): ?>
                    <i class="<?= (($square + intdiv($square, 4)) % 2) === 0 ? 'light' : 'dark' ?>"></i>
                  <?php endfor; ?>
                </span>
                <strong><?=e($label)?></strong>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <button>Guardar preferencias</button>
      </form>
    </section>

    <section class="card training-preferences-card">
      <h2>Entrenamiento</h2>
      <p class="muted">Configura cómo quieres seleccionar y comprobar las jugadas en los ejercicios.</p>
      <form method="post">
        <input type="hidden" name="profile_action" value="save_training_preferences">
        <?= csrf_field() ?>
        <div class="training-preferences-grid">
          <label class="training-preference-option">
            <input type="checkbox" name="show_legal_moves" value="1" <?= !empty($trainingGoalSettings['show_legal_moves']) ? 'checked' : '' ?>>
            <span>
              <span><strong>Mostrar movimientos permitidos</strong><small>Marca los destinos legales al seleccionar una pieza.</small></span>
              <i aria-hidden="true"></i>
            </span>
          </label>
          <label class="training-preference-option">
            <input type="checkbox" name="auto_submit_move" value="1" <?= !empty($trainingGoalSettings['auto_submit_move']) ? 'checked' : '' ?>>
            <span>
              <span><strong>Comprobar automáticamente</strong><small>Comprueba la jugada al seleccionar la casilla de destino.</small></span>
              <i aria-hidden="true"></i>
            </span>
          </label>
        </div>
        <button>Guardar preferencias</button>
      </form>
    </section>

    <section class="card player-progress-card">
      <div class="panel-head">
        <div>
          <h2>Progreso del jugador</h2>
          <p class="muted">Una medida móvil basada en tus ejercicios y partidas recientes. No son monedas: puede subir o bajar según cómo juegas y entrenas.</p>
        </div>
        <a class="btn secondary small" href="training.php">Ver plan</a>
      </div>
      <?php $autonomy = $playerProgress['autonomy'] ?? []; ?>
      <div class="training-plan-overview">
        <article class="training-progress-metric">
          <div><span>Índice de rendimiento</span><strong><?= !empty($playerProgress['available']) ? (int)$playerProgress['score'].'/1000' : '--' ?></strong></div>
          <p><?= !empty($playerProgress['available']) ? '60% ejercicios · 40% partidas' : 'Se calculará al abrir tu plan de entrenamiento.' ?></p>
          <div class="home-training-progress"><i style="width:<?= !empty($playerProgress['available']) ? max(0, min(100, (int)round((int)$playerProgress['score'] / 10))) : 0 ?>%"></i></div>
        </article>
        <article class="training-progress-metric">
          <div><span>Autonomía</span><strong><?= isset($autonomy['score']) && $autonomy['score'] !== null ? (int)round((float)$autonomy['score']).'%' : '--' ?></strong></div>
          <p><?= !empty($autonomy['calibrated']) ? 'Capacidad de resolver sin ayudas.' : 'Calibrando '.(int)($autonomy['samples'] ?? 0).'/'.(int)($autonomy['minimum_samples'] ?? 6).' ejercicios.' ?></p>
          <div class="home-training-progress"><i style="width:<?= isset($autonomy['score']) && $autonomy['score'] !== null ? max(0, min(100, (int)round((float)$autonomy['score']))) : 0 ?>%"></i></div>
        </article>
      </div>
    </section>

    <section class="card training-goals-card">
      <h2>Objetivo de entrenamiento</h2>
      <p class="muted">Configura el objetivo que usará Chess Coach para calcular el progreso diario, la racha y tu plan personal. Entrenar un ejercicio cuenta como actividad, pero la racha principal se mantiene al cumplir el objetivo diario.</p>
      <form method="post">
        <input type="hidden" name="profile_action" value="save_training_goals">
        <?= csrf_field() ?>
        <div class="training-goal-grid">
          <label class="training-goal-field">
            <span>Objetivo diario</span>
            <select name="daily_goal_mode">
              <option value="exercises" <?= $trainingGoalSettings['daily_goal_mode'] === 'exercises' ? 'selected' : '' ?>>Ejercicios</option>
              <option value="minutes" <?= $trainingGoalSettings['daily_goal_mode'] === 'minutes' ? 'selected' : '' ?>>Minutos</option>
              <option value="both" <?= $trainingGoalSettings['daily_goal_mode'] === 'both' ? 'selected' : '' ?>>Ejercicios y minutos</option>
            </select>
          </label>
          <label class="training-goal-field">
            <span>Ejercicios al día</span>
            <input type="number" name="daily_exercise_goal" min="1" max="100" step="1" value="<?= (int)$trainingGoalSettings['daily_exercise_goal'] ?>">
          </label>
          <label class="training-goal-field">
            <span>Minutos al día</span>
            <input type="number" name="daily_minutes_goal" min="1" max="240" step="1" value="<?= (int)$trainingGoalSettings['daily_minutes_goal'] ?>">
          </label>
          <label class="training-goal-field">
            <span>Días por semana</span>
            <input type="number" name="weekly_training_days_goal" min="1" max="7" step="1" value="<?= (int)$trainingGoalSettings['weekly_training_days_goal'] ?>">
          </label>
          <label class="training-goal-field">
            <span>Ejercicios por semana</span>
            <input type="number" name="weekly_exercise_goal" min="1" max="500" step="1" value="<?= (int)$trainingGoalSettings['weekly_exercise_goal'] ?>">
          </label>
        </div>
        <div class="training-goal-summary">
          <strong>Configuración actual</strong>
          <span>
            <?= (int)$trainingGoalSettings['daily_exercise_goal'] ?> ejercicio(s),
            <?= (int)$trainingGoalSettings['daily_minutes_goal'] ?> minuto(s),
            <?= (int)$trainingGoalSettings['weekly_training_days_goal'] ?> día(s) por semana.
          </span>
        </div>
        <button>Guardar objetivo</button>
        <a class="btn secondary" href="training.php">Ir a entrenamiento</a>
      </form>
    </section>

    <section class="card batch-card">
      <h2>Procesos batch</h2>
      <p class="muted">Procesos manuales que siguen siendo necesarios para completar datos históricos o auditar la selección de entrenamiento.</p>
      <div class="batch-row">
        <div>
          <strong>Calidad y conceptos de entrenamiento</strong>
          <p class="muted">Clasifica ejercicios y escenarios existentes, elimina duplicados lógicos y calcula su valor pedagógico. Es reanudable y no modifica intentos ni progreso.</p>
          <p class="muted" id="trainingFoundationBackfillPending">Pendientes: <?= (int)$pendingTrainingFoundation ?></p>
        </div>
        <button type="button" onclick="runTrainingFoundationBackfill()" id="trainingFoundationBackfillBtn">Procesar lote</button>
      </div>
      <p class="muted" id="trainingFoundationBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>Backfill de aperturas</strong>
          <p class="muted">Genera perfiles de apertura para partidas importadas o analizadas antes del Lab de Aperturas. Procesa hasta 25 partidas por ejecucion.</p>
          <p class="muted" id="openingsBackfillPending">Pendientes: <?= (int)$pendingOpeningProfiles ?></p>
        </div>
        <button type="button" onclick="runOpeningsBackfill()" id="openingsBackfillBtn">Perfilar aperturas</button>
      </div>
      <p class="muted" id="openingsBackfillResult"></p>
    </section>
  </main>
</div>
<?= csrf_script() ?>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script>
async function runTrainingFoundationBackfill() {
  const btn = document.getElementById('trainingFoundationBackfillBtn');
  const result = document.getElementById('trainingFoundationBackfillResult');
  const pending = document.getElementById('trainingFoundationBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Clasificando oportunidades de entrenamiento...';
  try {
    const response = await fetch('api/analyze.php?action=training_foundation_backfill', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ limit: <?= (int)(app_config()['training_foundation_backfill_batch_size'] ?? 100) ?> })
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'El servidor no pudo procesar el lote.');
    const shadow = data.metrics?.shadow || null;
    const shadowText = shadow && Number(shadow.runs || 0) > 0
      ? ` Shadow: ${shadow.runs} comparaciones, calidad media ${shadow.average_quality ?? 'n/d'}, coincidencia ${shadow.overlap_rate ?? 'n/d'}%.`
      : '';
    const errors = Array.isArray(data.errors) ? data.errors.filter(Boolean) : [];
    const errorText = errors.length ? ` Errores: ${errors.slice(0, 3).join(' | ')}` : '';
    if (result) result.textContent = `Procesados: ${data.processed || 0}. Publicados: ${data.published || 0}. Reserva: ${data.reserve || 0}. Rechazados: ${data.rejected || 0}. Duplicados evitados: ${data.duplicates || 0}. Conceptos recalculados: ${data.mastery_recalculated || 0}. Pendientes: ${data.pending || 0}.${shadowText}${errorText}`;
    if (pending) pending.textContent = `Pendientes: ${data.pending || 0}`;
  } catch (error) {
    if (result) result.textContent = error.message || 'No se pudo procesar el lote.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function runOpeningsBackfill() {
  const btn = document.getElementById('openingsBackfillBtn');
  const result = document.getElementById('openingsBackfillResult');
  const pending = document.getElementById('openingsBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Generando perfiles de apertura...';
  try {
    const r = await fetch('api/openings.php?action=backfill', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ limit: 25 })
    });
    const data = await r.json();
    if (!data.ok && Number(data.error_count || 0) > 0) throw new Error(data.message || 'Backfill completado con errores.');
    if (result) result.textContent = `${data.message || 'Backfill ejecutado.'} Partidas: ${data.processed_games || 0}. Perfiles actualizados: ${data.updated_profiles || 0}. Pendientes: ${data.pending_after || 0}.`;
    if (pending) pending.textContent = `Pendientes: ${data.pending_after || 0}`;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudo ejecutar el backfill de aperturas.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

</script>
</body>
</html>
