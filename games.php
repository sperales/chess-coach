<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; $u=require_login(); $assetVersion=(string)filemtime(__DIR__.'/assets/css/app.css'); $layoutJsVersion=(string)filemtime(__DIR__.'/assets/js/layout.js'); $gamesJsVersion=(string)filemtime(__DIR__.'/assets/js/games.js'); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Partidas · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css?v=<?=e($assetVersion)?>">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard games-page">
  <section class="hero-card compact">
    <div>
      <h1>Partidas</h1>
      <p>Explora tu historial, filtra por color, resultado y etiquetas, y vuelve a revisar cualquier análisis.</p>
    </div>
    <div class="hero-piece">♟</div>
  </section>

  <section class="panel games-filter-panel">
    <div class="panel-head">
      <h2>Filtros</h2>
      <button class="secondary small" type="button" onclick="clearGameFilters()">Limpiar</button>
    </div>
    <div class="games-filter-grid">
      <label>Color
        <select id="colorFilter">
          <option value="">Todos</option>
          <option value="white">Blancas</option>
          <option value="black">Negras</option>
        </select>
      </label>
      <label>Resultado
        <select id="resultFilter">
          <option value="">Todos</option>
          <option value="win">Ganadas</option>
          <option value="loss">Perdidas</option>
          <option value="draw">Tablas</option>
        </select>
      </label>
      <label>Etiqueta
        <select id="tagFilter">
          <option value="">Todas</option>
        </select>
      </label>
    </div>
  </section>

  <section class="panel" id="partidas">
    <div class="panel-head">
      <h2>Listado de partidas</h2>
      <span class="muted" id="gamesFilterStatus">Cargando...</span>
    </div>
    <div class="table-scroll">
      <table class="games">
        <thead>
          <tr>
            <th>Rival</th>
            <th>Resultado</th>
            <th>Ritmo</th>
            <th class="hide-sm">Apertura</th>
            <th class="hide-sm">Fecha</th>
            <th>Análisis</th>
          </tr>
        </thead>
        <tbody id="gameRows"></tbody>
      </table>
    </div>
    <div class="pagination" id="gamesPagination"></div>
    <div class="muted page-info" id="gamesPageInfo"></div>
  </section>
</main>
</div>
<script>window.CHESS_COACH_USERNAME = <?=json_encode($u['username'])?>; window.CHESS_COACH_CONFIG = { gamesPerPage: <?php echo (int)(app_config()['games_per_page'] ?? 50); ?> }; window.CHESS_COACH_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/games.js?v=<?=e($gamesJsVersion)?>"></script>
</body>
</html>
