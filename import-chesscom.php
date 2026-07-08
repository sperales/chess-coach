<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; $u=require_login(); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Importar partidas</title><link rel="stylesheet" href="assets/css/app.css"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icons/favicon.ico"></head><body class="dark-shell"><?php header_bar('Chess Coach'); ?><div class="app-area">
<main class="dashboard import-page">
  <section class="hero-card compact"><div><h1>Importar partidas</h1><p>Añade partidas pegando PGN o importándolas desde Chess.com. Las partidas nuevas se encolan automáticamente para análisis.</p></div><div class="hero-piece">⇩</div></section>
  <section class="import-grid">
    <section class="panel import-card open" id="importCard"><button class="section-toggle" type="button" onclick="toggleImport()" aria-expanded="true" aria-controls="importBody"><span class="arrow">›</span><span>Importar PGN</span></button><div class="collapsible-body" id="importBody"><textarea id="pgn" placeholder="Pega aquí uno o varios PGN..."></textarea><p class="row"><button onclick="importPgn()">Importar PGN</button><span class="muted" id="msg"></span></p></div></section>
    <section class="panel"><h2>Importar desde Chess.com</h2><p class="muted">Importa las partidas más recientes desde la API pública de Chess.com. Se evitarán duplicados y las nuevas quedarán en cola de análisis automáticamente.</p><p><label>Usuario Chess.com</label><input id="ccUser" value="<?=e($u['username'])?>" autocomplete="off"></p><p><label>Número de partidas</label><input id="ccLimit" type="number" min="1" max="100" value="20"></p><p class="row"><button onclick="importChessCom()">Importar desde Chess.com</button><a class="btn secondary" href="app.php">Volver</a><span class="muted" id="ccMsg"></span></p></section>
  </section>
</main>
</div>
<?= csrf_script() ?><script src="assets/js/layout.js"></script><script src="assets/js/app.js"></script><script src="assets/js/chesscom.js"></script></body></html>
