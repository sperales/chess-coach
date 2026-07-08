<?php require_once __DIR__.'/includes/auth.php'; require_once __DIR__.'/includes/helpers.php'; $u=require_login(); $layoutJsVersion=(string)filemtime(__DIR__.'/assets/js/layout.js'); $analysisJsVersion=(string)filemtime(__DIR__.'/assets/js/analysis_queue.js'); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Análisis pendientes · Chess Coach</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="icon" href="assets/icons/favicon.ico">
</head>
<body class="dark-shell">
<?php header_bar('Chess Coach'); ?>
<div class="app-area">
<main class="dashboard queue-page">
  <section class="hero-card compact">
    <div>
      <h1>Análisis pendientes</h1>
      <p>Controla la cola de análisis, comprueba el estado del worker y revisa el historial de ejecuciones en segundo plano.</p>
    </div>
    <div class="hero-piece">◷</div>
  </section>

  <section class="metric-grid queue-stats" id="queueStats"></section>

  <section class="home-grid queue-grid-top">
    <section class="panel">
      <div class="panel-head">
        <h2>Estado del worker</h2>
        <button class="secondary small" onclick="refreshQueue()">Refrescar</button>
      </div>
      <div id="workerOverview" class="worker-overview"></div>
      <div class="queue-toolbar compact-toolbar">
        <button onclick="runWorkerNow()">Ejecutar ahora</button>
        <button class="secondary" onclick="processNextJob()">Procesar siguiente</button>
        <button class="secondary" onclick="queueMissingGames()">Encolar sin análisis</button>
        <button class="secondary" onclick="retryErrors()">Reintentar errores</button>
        <button class="secondary danger" onclick="cancelWaiting()">Cancelar pendientes</button>
      </div>
      <p class="muted" id="queueMsg">Cargando datos del worker...</p>
    </section>

    <section class="panel engine-card">
      <h2>Motor de análisis</h2>
      <div id="engineStatus" class="engine-status muted">Comprobando Stockfish...</div>
      <hr class="soft-line">
      <h3>Cron del hosting</h3>
      <p class="muted">Usa el endpoint HTTP GET del worker con el token configurado para que el servidor procese la cola sin necesidad de abrir la web.</p>
      <div id="workerEndpoint" class="code-box muted">Cargando endpoint...</div>
      <p class="muted small-note">El endpoint queda protegido por token y procesa una partida por ejecución por defecto.</p>
    </section>
  </section>

  <section class="home-grid">
    <section class="panel">
      <div class="panel-head">
        <h2>Cola de análisis</h2>
        <div class="queue-toolbar inline-toolbar">
          <button onclick="startQueueWorker()" id="startWorkerBtn">Procesar cola</button>
          <button class="secondary" onclick="stopQueueWorker()" id="stopWorkerBtn" disabled>Detener</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="games queue-table">
          <thead>
            <tr>
              <th>Partida</th>
              <th>Estado</th>
              <th>Progreso</th>
              <th class="hide-sm">Creado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody id="queueRows"></tbody>
        </table>
      </div>
      <div class="pagination" id="queuePagination"></div>
      <div class="muted page-info" id="queuePageInfo"></div>
    </section>
  </section>

  <section class="home-grid">
    <section class="panel">
      <div class="panel-head">
        <h2>Historial del worker</h2>
        <span class="muted">Últimas ejecuciones</span>
      </div>
      <div class="table-scroll">
        <table class="games worker-history-table">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Resultado</th>
              <th>Duración</th>
              <th>Partida</th>
              <th class="hide-sm">Origen</th>
              <th class="hide-sm">Mensaje</th>
            </tr>
          </thead>
          <tbody id="workerHistoryRows"></tbody>
        </table>
      </div>
    </section>
  </section>
</main>
</div>
<script>window.CHESS_COACH_CONFIG = Object.assign(window.CHESS_COACH_CONFIG || {}, { analysisPerPage: <?php echo (int)(app_config()['analysis_per_page'] ?? 50); ?> }); window.CHESS_COACH_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/js/layout.js?v=<?=e($layoutJsVersion)?>"></script>
<script src="assets/js/analysis_queue.js?v=<?=e($analysisJsVersion)?>"></script>
</body>
</html>
