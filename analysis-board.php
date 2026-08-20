<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/pieces.php';
$u = require_login();
$assetVersion = (string)filemtime(__DIR__.'/assets/css/app.css');
$layoutVersion = (string)filemtime(__DIR__.'/assets/js/layout.js');
$sharedVersion = (string)filemtime(__DIR__.'/assets/js/interactive-position.js');
$boardVersion = (string)filemtime(__DIR__.'/assets/js/analysis-board.js');
$piecePath = piece_set_asset_path($u['piece_set'] ?? null);
$themeClass = board_theme_class($u['board_theme'] ?? null);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tablero de análisis · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell analysis-board-page <?=e($themeClass)?>">
<?php header_bar('Tablero de análisis'); ?>
<div class="app-area"><main class="dashboard analysis-board-dashboard">
  <section class="hero-card compact analysis-board-hero">
    <div><h1>Tablero de análisis</h1><p>Explora una posición, prueba ideas y compara variantes sin modificar tus partidas.</p></div>
  </section>
  <section class="analysis-board-workspace">
    <section class="panel analysis-board-stage">
      <header class="analysis-board-mobile-head">
        <button class="secondary" id="analysisClose" type="button" aria-label="Cerrar tablero de análisis">×</button>
        <strong>Tablero de análisis</strong>
        <button class="secondary" id="analysisMobileFlip" type="button" aria-label="Girar tablero">↻</button>
      </header>
      <section class="analysis-live-panel" aria-live="polite">
        <div class="analysis-live-eval"><strong id="analysisLiveEval">--</strong><span id="analysisLiveDepth">Esperando motor</span></div>
        <div class="analysis-live-lines" id="analysisLiveLines"></div>
      </section>
      <div class="chess-board analysis-position-board" id="analysisBoard" aria-label="Tablero de análisis"></div>
      <div class="analysis-board-nav">
        <button class="secondary" id="analysisBack" type="button" title="Posición anterior">‹</button>
        <button class="secondary" id="analysisForward" type="button" title="Posición siguiente">›</button>
        <button class="secondary" id="analysisReset" type="button">Reiniciar</button>
        <button class="secondary" id="analysisFlip" type="button">Girar</button>
      </div>
    </section>
    <aside class="panel analysis-board-sidebar">
      <div class="analysis-status-row"><span id="analysisStatus">Preparando posición...</span><strong id="analysisEval">--</strong></div>
      <div><small>Mejor jugada</small><h2 id="analysisBest">--</h2></div>
      <div><small>Variación principal</small><p id="analysisPv">--</p></div>
      <div class="analysis-tree-panel"><small>Historial y ramas</small><div id="analysisHistory" class="analysis-history"></div>
        <div id="analysisBranches" class="analysis-branches" aria-label="Árbol de variantes"></div>
      </div>
      <label for="analysisFen">FEN</label>
      <textarea id="analysisFen" rows="3" spellcheck="false"></textarea>
      <div class="analysis-board-actions">
        <button id="analysisLoad" type="button">Cargar FEN</button>
        <button class="secondary" id="analysisCopy" type="button">Copiar FEN</button>
        <button class="secondary" id="analysisStart" type="button">Posición inicial</button>
      </div>
      <a class="analysis-return-review" id="analysisReturnReview" href="review.php" hidden>Volver a Review</a>
    </aside>
  </section>
</main></div>
<script>
window.CHESS_COACH_CSRF=<?=json_encode(csrf_token(), JSON_UNESCAPED_SLASHES)?>;
window.CHESS_COACH_PIECE_PATH=<?=json_encode($piecePath, JSON_UNESCAPED_SLASHES)?>;
</script>
<script src="assets/js/layout.js?v=<?=e($layoutVersion)?>"></script>
<script src="assets/js/interactive-position.js?v=<?=e($sharedVersion)?>"></script>
<script src="assets/js/analysis-board.js?v=<?=e($boardVersion)?>"></script>
</body></html>
