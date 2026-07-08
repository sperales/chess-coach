<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/smart_tags.php';
require_once __DIR__.'/includes/training.php';
require_once __DIR__.'/includes/pieces.php';

$u = require_login();
$msg = '';
$err = '';
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutJsVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$pendingSmartTags = smart_tag_backfill_pending_count((int)$u['id']);
$pendingTrainingExercises = training_backfill_pending_count((int)$u['id']);
$pieceSets = available_piece_sets();
$currentPieceSet = normalize_piece_set($u['piece_set'] ?? null);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'change_piece_set') {
  $selectedPieceSet = normalize_piece_set($_POST['piece_set'] ?? '');
  if (!$selectedPieceSet) {
    $err = 'No hay sets de piezas disponibles.';
  } else {
    $st = db()->prepare('UPDATE users SET piece_set=?, updated_at=NOW() WHERE id=?');
    $st->execute([$selectedPieceSet, $u['id']]);
    $u['piece_set'] = $selectedPieceSet;
    $currentPieceSet = $selectedPieceSet;
    $msg = 'Set de piezas actualizado.';
  }
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
<body class="dark-shell">
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
        <p><input type="password" name="current_password" placeholder="Contraseña actual" required></p>
        <p><input type="password" name="new_password" placeholder="Nueva contraseña" required></p>
        <p><input type="password" name="new_password2" placeholder="Repetir nueva contraseña" required></p>
        <button>Cambiar contraseña</button>
        <a class="btn secondary" href="app.php">Volver</a>
      </form>
    </section>

    <section class="card">
      <h2>Tablero</h2>
      <p class="muted">Elige el set de piezas que se usará en revisión y entrenamiento.</p>
      <form method="post">
        <input type="hidden" name="profile_action" value="change_piece_set">
        <div class="piece-set-options">
          <?php foreach ($pieceSets as $set): ?>
            <label class="piece-set-option">
              <input type="radio" name="piece_set" value="<?=e($set)?>" <?= $set === $currentPieceSet ? 'checked' : '' ?>>
              <span>
                <strong><?=e($set)?></strong>
                <img src="<?=e(piece_set_asset_path($set))?>wk.png" alt="<?=e($set)?>" draggable="false">
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <button>Guardar set de piezas</button>
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
    </section>
  </main>
</div>
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
      headers: { 'Content-Type': 'application/json' },
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
      headers: { 'Content-Type': 'application/json' },
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
</script>
</body>
</html>
