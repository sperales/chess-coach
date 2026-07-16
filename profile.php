<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/smart_tags.php';
require_once __DIR__.'/includes/training.php';
require_once __DIR__.'/includes/openings.php';
require_once __DIR__.'/includes/player_dna.php';
require_once __DIR__.'/includes/pieces.php';

$u = require_login();
$msg = '';
$err = '';
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$pendingSmartTags = smart_tag_backfill_pending_count((int)$u['id']);
$pendingTrainingExercises = training_backfill_pending_count((int)$u['id']);
$pendingTrainingContent = training_content_backfill_pending_count((int)$u['id']);
$pendingTrainingEngine = training_engine_backfill_pending_count((int)$u['id']);
$pendingOpeningProfiles = openings_profile_pending_count((int)$u['id']);
$latestPlayerDna = player_dna_latest_snapshot((int)$u['id']);
$playerDnaConfidenceLabels = ['low' => 'baja', 'medium' => 'media', 'high' => 'alta'];
$pieceSets = available_piece_sets();
$currentPieceSet = normalize_piece_set($u['piece_set'] ?? null);
$boardThemes = board_theme_options();
$currentBoardTheme = normalize_board_theme($u['board_theme'] ?? null);
$trainingGoalSettings = training_goal_settings_for_user((int)$u['id']);

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
<body class="dark-shell <?=e(board_theme_class($currentBoardTheme))?>">
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

    <section class="card training-goals-card">
      <h2>Objetivo de entrenamiento</h2>
      <p class="muted">Configura el objetivo que usará Chess Coach para calcular progreso diario, racha e hitos. Entrenar un ejercicio cuenta como actividad, pero la racha principal se mantiene al cumplir el objetivo diario.</p>
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
      <p class="muted">Procesos manuales de mantenimiento para recalcular datos derivados sin reanalizar partidas con Stockfish.</p>
      <div class="batch-row">
        <div>
          <strong>Backfill de Smart Tags</strong>
          <p class="muted">Etiqueta partidas ya analizadas que todavía no tienen Smart Tags. Procesa hasta 20 partidas por ejecución.</p>
          <p class="muted" id="smartTagBackfillPending">Pendientes: <?= (int)$pendingSmartTags ?></p>
        </div>
        <button type="button" onclick="runSmartTagBackfill()" id="smartTagBackfillBtn">Ejecutar backfill</button>
      </div>
      <p class="muted" id="smartTagBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>Backfill de ejercicios</strong>
          <p class="muted">Genera ejercicios de entrenamiento desde partidas ya analizadas. Procesa hasta 10 análisis por ejecución.</p>
          <p class="muted" id="trainingBackfillPending">Pendientes: <?= (int)$pendingTrainingExercises ?></p>
        </div>
        <button type="button" onclick="runTrainingBackfill()" id="trainingBackfillBtn">Generar ejercicios</button>
      </div>
      <p class="muted" id="trainingBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>Actualizar contenido de ejercicios</strong>
          <p class="muted">Corrige la clasificación contextual, los textos, la prioridad y los Smart Tags asociados sin borrar intentos ni progreso. Procesa hasta 200 ejercicios por ejecución.</p>
          <p class="muted" id="trainingContentBackfillPending">Pendientes: <?= (int)$pendingTrainingContent ?></p>
        </div>
        <button type="button" onclick="runTrainingContentBackfill()" id="trainingContentBackfillBtn">Actualizar ejercicios</button>
      </div>
      <p class="muted" id="trainingContentBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>Enriquecer ejercicios con Stockfish</strong>
          <p class="muted">Guarda una nueva evaluación y la variante principal para ejercicios pendientes de resolver. Procesa hasta 50 ejercicios por ejecución y conserva la solución original si la nueva bestmove es distinta.</p>
          <p class="muted" id="trainingEngineBackfillPending">Pendientes: <?= (int)$pendingTrainingEngine ?></p>
        </div>
        <button type="button" onclick="runTrainingEngineBackfill()" id="trainingEngineBackfillBtn">Mejorar ejercicios</button>
      </div>
      <p class="muted" id="trainingEngineBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>Backfill de aperturas</strong>
          <p class="muted">Genera perfiles de apertura para partidas importadas o analizadas antes del Lab de Aperturas. Procesa hasta 25 partidas por ejecucion.</p>
          <p class="muted" id="openingsBackfillPending">Pendientes: <?= (int)$pendingOpeningProfiles ?></p>
        </div>
        <button type="button" onclick="runOpeningsBackfill()" id="openingsBackfillBtn">Perfilar aperturas</button>
      </div>
      <p class="muted" id="openingsBackfillResult"></p>
      <div class="batch-row">
        <div>
          <strong>ADN del jugador</strong>
          <p class="muted">Recalcula el perfil de estilo, fortalezas, debilidades y comparativas usando las partidas ya analizadas.</p>
          <p class="muted" id="playerDnaStatus">
            <?php if ($latestPlayerDna): ?>
              Último snapshot: <?=e((string)$latestPlayerDna['generated_at'])?> · Confianza: <?=e($playerDnaConfidenceLabels[(string)$latestPlayerDna['confidence']] ?? (string)$latestPlayerDna['confidence'])?> · Partidas: <?= (int)$latestPlayerDna['analyzed_games'] ?>
            <?php else: ?>
              Sin snapshot generado todavía.
            <?php endif; ?>
          </p>
        </div>
        <button type="button" onclick="runPlayerDnaRecompute()" id="playerDnaBtn">Recalcular ADN</button>
      </div>
      <p class="muted" id="playerDnaResult"></p>
    </section>
  </main>
</div>
<?= csrf_script() ?>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script>
async function runSmartTagBackfill() {
  const btn = document.getElementById('smartTagBackfillBtn');
  const result = document.getElementById('smartTagBackfillResult');
  const pending = document.getElementById('smartTagBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Ejecutando backfill de Smart Tags...';
  try {
    const r = await fetch('api/analyze.php?action=smart_tags_backfill', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ limit: 20 })
    });
    const data = await r.json();
    if (!data.ok && Number(data.error_count || 0) > 0) throw new Error(data.message || 'Backfill completado con errores.');
    if (result) result.textContent = `${data.message || 'Backfill ejecutado.'} Procesadas: ${data.processed_games || 0}. Con tags: ${data.tagged_games || 0}. Pendientes: ${data.pending_after || 0}.`;
    if (pending) pending.textContent = `Pendientes: ${data.pending_after || 0}`;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudo ejecutar el backfill.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function runTrainingBackfill() {
  const btn = document.getElementById('trainingBackfillBtn');
  const result = document.getElementById('trainingBackfillResult');
  const pending = document.getElementById('trainingBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Generando ejercicios de entrenamiento...';
  try {
    const r = await fetch('api/analyze.php?action=training_backfill', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ limit: 10 })
    });
    const data = await r.json();
    if (!data.ok && Number(data.error_count || 0) > 0) throw new Error(data.message || 'Backfill completado con errores.');
    if (result) result.textContent = `${data.message || 'Backfill ejecutado.'} Análisis: ${data.processed_analyses || 0}. Ejercicios nuevos: ${data.created_exercises || 0}. Existentes/omitidos: ${data.skipped_existing || 0}. Pendientes: ${data.pending_after || 0}.`;
    if (pending) pending.textContent = `Pendientes: ${data.pending_after || 0}`;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudo ejecutar el backfill de ejercicios.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function runTrainingContentBackfill() {
  const btn = document.getElementById('trainingContentBackfillBtn');
  const result = document.getElementById('trainingContentBackfillResult');
  const pending = document.getElementById('trainingContentBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Actualizando el contenido de los ejercicios...';
  try {
    const r = await fetch('api/analyze.php?action=training_content_backfill', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ limit: 200 })
    });
    const data = await r.json();
    if (!data.ok && Number(data.error_count || 0) > 0) throw new Error(data.message || 'Actualización completada con errores.');
    if (result) result.textContent = `${data.message || 'Actualización ejecutada.'} Actualizados: ${data.updated || 0}. Reclasificados: ${data.retyped || 0}. Conflictos conservados: ${data.type_conflicts || 0}. Pendientes: ${data.pending_after || 0}.`;
    if (pending) pending.textContent = `Pendientes: ${data.pending_after || 0}`;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudo actualizar el contenido de los ejercicios.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function runTrainingEngineBackfill() {
  const btn = document.getElementById('trainingEngineBackfillBtn');
  const result = document.getElementById('trainingEngineBackfillResult');
  const pending = document.getElementById('trainingEngineBackfillPending');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Stockfish está enriqueciendo hasta 50 ejercicios en lotes seguros...';
  try {
    const totals = { updated: 0, mismatches: 0, accepted: 0, rejected: 0 };
    const errors = [];
    let pendingAfter = Number(pending?.textContent.match(/\d+/)?.[0] || 0);
    let message = 'Ejercicios enriquecidos con Stockfish correctamente.';

    for (let batch = 0; batch < 5; batch += 1) {
      if (result) result.textContent = `Stockfish está procesando el lote ${batch + 1}/5...`;
      const response = await fetch('api/analyze.php?action=training_engine_backfill', {
        method: 'POST',
        headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ limit: 10 })
      });
      const responseText = await response.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        const status = response.status ? ` HTTP ${response.status}` : '';
        throw new Error(`El servidor interrumpió el lote antes de devolver JSON.${status}. Prueba de nuevo; los lotes anteriores sí se han conservado.`);
      }

      if (!response.ok) throw new Error(data.error || `El servidor rechazó el lote (HTTP ${response.status}).`);
      totals.updated += Number(data.updated || 0);
      totals.mismatches += Number(data.mismatches || 0);
      totals.accepted += Number(data.alternatives_accepted || 0);
      totals.rejected += Number(data.alternatives_rejected || 0);
      pendingAfter = Number(data.pending_after || 0);
      message = data.message || message;
      errors.push(...(Array.isArray(data.errors) ? data.errors.filter(Boolean) : []));

      if (pending) pending.textContent = `Pendientes: ${pendingAfter}`;
      if (errors.length || Number(data.processed || 0) === 0 || pendingAfter === 0) break;
    }

    const summary = `${message} Mejorados: ${totals.updated}. Bestmoves distintas: ${totals.mismatches}. Alternativas válidas: ${totals.accepted}. Alternativas descartadas: ${totals.rejected}. Pendientes: ${pendingAfter}.`;
    if (result) result.textContent = errors.length ? `${summary} Errores: ${errors.join(' | ')}` : summary;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudieron enriquecer los ejercicios con Stockfish.';
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

async function runPlayerDnaRecompute() {
  const btn = document.getElementById('playerDnaBtn');
  const result = document.getElementById('playerDnaResult');
  const status = document.getElementById('playerDnaStatus');
  if (btn) btn.disabled = true;
  if (result) result.textContent = 'Recalculando ADN del jugador...';
  try {
    const r = await fetch('api/player-dna.php?action=recompute', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({})
    });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo recalcular el ADN del jugador.');
    const snapshot = data.snapshot || {};
    if (result) result.textContent = `${data.message || 'ADN recalculado.'} Partidas procesadas: ${data.processed_games || 0}.`;
    if (status) status.textContent = `Último snapshot: ${snapshot.generated_at || 'ahora'} · Confianza: ${playerDnaConfidenceLabel(snapshot.confidence || 'low')} · Partidas: ${snapshot.analyzed_games || 0}`;
  } catch (e) {
    if (result) result.textContent = e.message || 'No se pudo recalcular el ADN del jugador.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

function playerDnaConfidenceLabel(value) {
  return { low: 'baja', medium: 'media', high: 'alta' }[value] || value || 'baja';
}
</script>
</body>
</html>
