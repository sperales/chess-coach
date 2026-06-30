<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; require_once __DIR__.'/includes/motivational_quotes.php'; $u=require_login(); $quote=random_motivational_quote(); $assetVersion=(string)filemtime(__DIR__.'/assets/css/app.css'); $appJsVersion=(string)filemtime(__DIR__.'/assets/js/app.js'); $layoutJsVersion=(string)filemtime(__DIR__.'/assets/js/layout.js'); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chess Coach</title><link rel="manifest" href="manifest.webmanifest"><link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>"><link rel="icon" href="assets/icons/favicon.ico"></head><body class="dark-shell"><?php header_bar('Chess Coach'); ?><div class="app-area">
<main class="dashboard">
  <section class="hero-card">
    <div>
      <h1>¡Buenas tardes, <?=e($u['username'])?>! 👋</h1>
      <p>Cada partida es una oportunidad para mejorar.</p>
    </div>
    <div class="hero-piece">♛</div>
  </section>

  <section class="metric-grid" id="stats"></section>

  <section class="panel quick-panel">
    <h2>Acciones rápidas</h2>
    <div class="quick-grid">
      <a class="quick-card green" href="import-chesscom.php"><span>⇩</span><strong>Importar partidas</strong><small>PGN o desde Chess.com</small></a>
      <button class="quick-card blue" type="button" onclick="analyzePendingVisible()"><span>▶</span><strong>Analizar pendientes</strong><small>Ver cola de análisis</small></button>
      <button class="quick-card purple" type="button" onclick="reviewLastGame()"><span>⌕</span><strong>Revisar última partida</strong><small>Ver análisis completo</small></button>
      <a class="quick-card amber disabled" href="#"><span>◎</span><strong>Entrenamiento</strong><small>Ejercicios personalizados</small></a>
    </div>
  </section>

  <section class="home-grid">
    <section class="panel" id="partidas">
      <div class="panel-head"><h2>Últimas partidas</h2><a id="gamesToggleLink" href="games.php">Ver todas</a></div>
      <table class="games"><thead><tr><th>Rival</th><th>Resultado</th><th>Ritmo</th><th class="hide-sm">Fecha</th><th>Análisis</th></tr></thead><tbody id="rows"></tbody></table>
      <div class="pagination" id="pagination"></div>
    </section>

    <section class="panel insight-card" id="smartTagInsight">
      <h2>Etiquetas frecuentes</h2>
      <p class="muted">Cargando patrones detectados...</p>
    </section>
  </section>

  <section class="quote-panel panel"><span>“</span><p><?=e($quote['quote_text'] ?? '')?><br><small>– <?=e($quote['author'] ?? '')?></small></p><b>♞</b></section>
</main>
</div>
<script>window.CHESS_COACH_USERNAME = <?=json_encode($u['username'])?>; window.CHESS_COACH_CONFIG = { gamesPerPage: <?php echo (int)(app_config()['games_per_page'] ?? 50); ?> };</script><script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script><script src="assets/js/app.js?v=<?=e($appJsVersion)?>"></script></body></html>
