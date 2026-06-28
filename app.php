<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; $u=require_login(); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chess Coach</title><link rel="manifest" href="manifest.webmanifest"><link rel="stylesheet" href="assets/css/app.css"><link rel="icon" href="assets/icons/favicon.ico"></head><body class="dark-shell"><?php header_bar('Chess Coach'); ?><div class="app-area">
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
      <div class="panel-head"><h2>Últimas partidas</h2><a id="gamesToggleLink" href="#partidas" onclick="showAllGames(event)">Ver todas</a></div>
      <table class="games"><thead><tr><th>Rival</th><th>Resultado</th><th>Ritmo</th><th class="hide-sm">Fecha</th><th>Análisis</th></tr></thead><tbody id="rows"></tbody></table>
      <div class="pagination" id="pagination"></div><div class="muted page-info" id="pageInfo"></div>
    </section>

    <section class="panel insight-card">
      <h2><span>💡</span> Insight del día</h2>
      <p><strong>Tu precisión en las aperturas</strong> ha mejorado en la última semana. Sigue así.</p>
      <div class="mini-chart"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
      <a class="btn full" href="#">Ver estadísticas completas</a>
    </section>
  </section>

  <section class="quote-panel panel"><span>“</span><p>No se trata de jugar perfecto, sino de aprender cada día.<br><small>– José Raúl Capablanca</small></p><b>♞</b></section>
</main>
</div>
<script>window.CHESS_COACH_USERNAME = <?=json_encode($u['username'])?>; window.CHESS_COACH_CONFIG = { gamesPerPage: <?php echo (int)(app_config()['games_per_page'] ?? 50); ?> };</script><script src="assets/js/layout.js"></script><script src="assets/js/app.js"></script></body></html>
