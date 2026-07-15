let queueAuto = false;
let queueBusy = false;
let queuePoll = null;
let queuePage = 1;
let queuePagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.analysisPerPage) || 50, total: 0, pages: 1 };

async function apiGet(url) {
  const r = await fetch(url, { cache: 'no-store' });
  return await r.json();
}

async function apiPost(url, body = {}) {
  const r = await fetch(url, {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(body)
  });
  return await r.json();
}

function qEscape(s) {
  return (s || '').toString().replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

function statusLabel(status) {
  const map = {
    queued: ['En cola', 'queued'],
    running: ['Analizando', 'running'],
    done: ['Analizada', 'done'],
    error: ['Error', 'error'],
    cancelled: ['Cancelada', 'cancelled']
  };
  const v = map[status] || [status || 'Sin estado', 'unknown'];
  return `<span class="queue-status ${v[1]}">${v[0]}</span>`;
}

function workerRunLabel(row) {
  if ((row.error_count || 0) > 0 && (row.success_count || 0) === 0) return '<span class="queue-status error">Error</span>';
  if ((row.processed_count || 0) > 0) return '<span class="queue-status done">OK</span>';
  return '<span class="queue-status queued">Sin trabajo</span>';
}

function formatDateTime(value) {
  if (!value) return '—';
  return value.replace('T', ' ').slice(0, 19);
}

function secondsLabel(value) {
  if (value === null || value === undefined || value === '') return '—';
  return `${value}s`;
}

function intervalLabel(minutes) {
  const m = Number(minutes || 0);
  if (!m) return 'sin intervalo configurado';
  if (m % 1440 === 0) return `cada ${m / 1440} día${m === 1440 ? '' : 's'}`;
  if (m % 60 === 0) return `cada ${m / 60} hora${m === 60 ? '' : 's'}`;
  return `cada ${m} min`;
}


function progressText(job) {
  const total = Number(job.total_ply || 0);
  const current = Number(job.current_ply || 0);
  if (job.status === 'done') return '100%';
  if (!total) return job.status === 'queued' ? 'Esperando' : 'Preparando';
  const pct = Math.min(100, Math.round(current * 100 / total));
  return `<div class="progress"><i style="width:${pct}%"></i></div><span class="muted">${current}/${total} · ${pct}%</span>`;
}

function gameTitle(job) {
  const white = job.white_player || 'Blancas';
  const black = job.black_player || 'Negras';
  const date = job.played_at || (job.created_at || '').slice(0, 10);
  return `<strong>${qEscape(white)} vs ${qEscape(black)}</strong><small>${qEscape(job.result_raw || '-')} · ${qEscape(date || '-')}</small>`;
}

function actionButtons(job) {
  if (job.status === 'queued') return `<button class="secondary small" onclick="cancelJob(${job.analysis_id})">Cancelar</button>`;
  if (job.status === 'running') return `<button class="secondary small" onclick="cancelJob(${job.analysis_id})">Pedir cancelación</button>`;
  if (job.status === 'error' || job.status === 'cancelled') return `<button class="secondary small" onclick="requeueGame(${job.game_id})">Reintentar</button>`;
  if (job.status === 'done') return `<button class="secondary small" onclick="requeueGame(${job.game_id}, true)">Reanalizar</button>`;
  return '';
}

function renderStats(data) {
  const q = data.queue || {};
  const el = document.getElementById('queueStats');
  if (!el) return;
  const cards = [
    ['clock', 'En cola', q.queued || 0, 'pendientes'],
    ['target', 'Analizando', q.running || 0, 'en proceso'],
    ['done', 'Analizadas', q.done || 0, 'completadas'],
    ['kpi-error', 'Errores', q.errors || 0, 'requieren revisión'],
  ];
  el.innerHTML = cards.map(c => `<article class="metric-card compact-metric-card ${c[0]}"><div class="metric-icon">${iconForQueue(c[0])}</div><span>${c[1]}</span><b>${c[2]}</b><small>${c[3]}</small></article>`).join('');
}

function iconForQueue(k) {
  return k === 'clock' ? '◷' : k === 'target' ? '▶' : k === 'done' ? '✓' : '!';
}

function renderEngine(data) {
  const el = document.getElementById('engineStatus');
  if (!el) return;
  const s = data.stockfish || {};
  el.innerHTML = `
    <div class="engine-line"><strong>Stockfish</strong> ${s.ok ? '<span class="ok-mini">Disponible</span>' : '<span class="bad-mini">No disponible</span>'}</div>
    <div class="muted">proc_open: ${s.proc_open ? 'sí' : 'no'}</div>
    <div class="muted engine-path">${s.path_configured ? 'Ruta configurada' : 'Sin ruta configurada'}</div>
  `;
}

function renderWorkerSummary(data) {
  const worker = data.worker || {};
  const last = worker.last_run || {};
  const el = document.getElementById('workerOverview');
  if (el) {
    const items = [
      ['Protección', worker.cron_protected ? 'Token activo' : 'Sin token', worker.cron_protected ? `Token: ${qEscape(worker.masked_token || '')}` : 'Configura el token del worker'],
      ['Última ejecución', formatDateTime(last.created_at), last.message || 'Sin ejecuciones registradas'],
      ['Próxima ejecución', formatDateTime(worker.next_run_estimated_at), `Intervalo esperado: ${intervalLabel(worker.expected_interval_minutes || 360)}`],
      ['Tiempo medio', secondsLabel(worker.avg_seconds_per_game), 'por partida analizada'],
      ['Errores seguidos', worker.consecutive_errors || 0, 'ejecuciones consecutivas fallidas'],
      ['Cola total', (worker.queue && worker.queue.pending_total) || 0, 'pendientes o en curso'],
    ];
    el.innerHTML = items.map(item => `<div class="worker-kpi"><span>${item[0]}</span><b>${item[1]}</b><small>${qEscape(item[2])}</small></div>`).join('');
  }
  const endpoint = document.getElementById('workerEndpoint');
  if (endpoint) {
    const origin = window.location.origin + window.location.pathname.replace(/analysis-pending\.php.*$/, '');
    endpoint.textContent = `${origin}${worker.worker_path || 'worker/analyze_queue.php'}?token=***`;
  }
}

function renderQueueRows(jobs) {
  const el = document.getElementById('queueRows');
  if (!el) return;
  el.innerHTML = (jobs || []).map(job => `
    <tr>
      <td>${gameTitle(job)}${job.error_message ? `<div class="queue-error">${qEscape(job.error_message)}</div>` : ''}</td>
      <td>${statusLabel(job.status)}</td>
      <td>${progressText(job)}</td>
      <td class="hide-sm">${qEscape(job.created_at || '-')}</td>
      <td>${actionButtons(job)}</td>
    </tr>
  `).join('') || `<tr><td colspan="5" class="muted">No hay análisis en la cola.</td></tr>`;
}

function renderQueuePagination(pagination) {
  queuePagination = pagination || queuePagination;
  queuePage = queuePagination.page || queuePage;

  const controls = document.getElementById('queuePagination');
  const info = document.getElementById('queuePageInfo');
  const total = Number(queuePagination.total || 0);
  const page = Number(queuePagination.page || 1);
  const pages = Number(queuePagination.pages || 1);
  const perPage = Number(queuePagination.per_page || 50);
  const start = total ? ((page - 1) * perPage) + 1 : 0;
  const end = Math.min(total, page * perPage);

  if (info) info.textContent = total ? `${start}-${end} de ${total}` : '0 análisis';
  if (!controls) return;
  if (pages <= 1) { controls.innerHTML = ''; return; }
  controls.innerHTML = `<button class="secondary small" ${page <= 1 ? 'disabled' : ''} onclick="goQueuePage(${page - 1})">Anterior</button><span class="page-current">Página ${page} de ${pages}</span><button class="secondary small" ${page >= pages ? 'disabled' : ''} onclick="goQueuePage(${page + 1})">Siguiente</button>`;
}

function renderHistory(rows) {
  const el = document.getElementById('workerHistoryRows');
  if (!el) return;
  el.innerHTML = (rows || []).map(row => {
    const game = row.white_player ? `${qEscape(row.white_player)} vs ${qEscape(row.black_player || '')}` : '—';
    const detail = row.result_raw ? `<small>${qEscape(row.result_raw)} · ${qEscape(row.played_at || '')}</small>` : '';
    return `
      <tr>
        <td>${qEscape(formatDateTime(row.created_at))}</td>
        <td>${workerRunLabel(row)}</td>
        <td>${Math.max(0, Math.round((Number(row.duration_ms || 0) / 100)) / 10)} s</td>
        <td><strong>${game}</strong>${detail}</td>
        <td class="hide-sm">${qEscape(row.trigger_source || '-')}</td>
        <td class="hide-sm">${qEscape(row.message || '-')}</td>
      </tr>
    `;
  }).join('') || `<tr><td colspan="6" class="muted">Todavía no hay ejecuciones registradas.</td></tr>`;
}

async function refreshQueue(silent = false, page = queuePage) {
  const msg = document.getElementById('queueMsg');
  try {
    const perPage = queuePagination.per_page || ((window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.analysisPerPage) || 50);
    const requestedPage = Math.max(1, Number(page) || 1);
    const data = await apiGet(`api/analyze.php?action=dashboard&page=${requestedPage}&per_page=${perPage}`);
    renderStats(data);
    renderEngine(data);
    renderWorkerSummary(data);
    renderQueueRows(data.jobs || []);
    renderQueuePagination(data.pagination || {});
    renderHistory(data.history || []);
    if (msg && !silent) msg.textContent = 'Panel actualizado.';
    const q = data.queue || {};
    if (q.pending_total > 0 && !queuePoll) queuePoll = setInterval(() => refreshQueue(true), 4000);
    if ((!q.pending_total || q.pending_total === 0) && queuePoll && !queueAuto) { clearInterval(queuePoll); queuePoll = null; }
  } catch (e) {
    if (msg) msg.textContent = 'Error actualizando la cola: ' + e.message;
  }
}

function goQueuePage(page) {
  if (page < 1 || page > (queuePagination.pages || 1) || page === queuePage) return;
  refreshQueue(false, page);
}

async function processNextJob() {
  if (queueBusy) return;
  queueBusy = true;
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Procesando siguiente partida...';
  try {
    const data = await apiPost('api/analyze.php?action=process_next');
    if (msg) msg.textContent = data.message || (data.processed ? 'Proceso completado.' : 'No hay análisis pendientes.');
    await refreshQueue(true);
  } catch (e) {
    if (msg) msg.textContent = 'Error procesando: ' + e.message;
  } finally {
    queueBusy = false;
  }
}

async function runWorkerNow() {
  if (queueBusy) return;
  queueBusy = true;
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Ejecutando worker...';
  try {
    const data = await apiPost('api/analyze.php?action=run_worker', { batch: 1 });
    if (msg) msg.textContent = data.message || 'Worker ejecutado.';
    await refreshQueue(true);
  } catch (e) {
    if (msg) msg.textContent = 'Error ejecutando worker: ' + e.message;
  } finally {
    queueBusy = false;
  }
}

async function startQueueWorker() {
  if (queueAuto) return;
  queueAuto = true;
  document.getElementById('startWorkerBtn').disabled = true;
  document.getElementById('stopWorkerBtn').disabled = false;
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Worker iniciado. La cola se procesará partida a partida.';
  if (!queuePoll) queuePoll = setInterval(() => refreshQueue(true), 4000);
  while (queueAuto) {
    const before = await apiGet('api/analyze.php?action=queue_status');
    const q = before.queue || {};
    if (!q.queued) {
      if (msg) msg.textContent = q.running ? 'Hay un análisis en curso...' : 'Cola completada.';
      if (!q.running) break;
      await new Promise(r => setTimeout(r, 3000));
      continue;
    }
    await processNextJob();
    await new Promise(r => setTimeout(r, 500));
  }
  stopQueueWorker(false);
  await refreshQueue(true);
}

function stopQueueWorker(showMessage = true) {
  queueAuto = false;
  const start = document.getElementById('startWorkerBtn');
  const stop = document.getElementById('stopWorkerBtn');
  if (start) start.disabled = false;
  if (stop) stop.disabled = true;
  if (showMessage) {
    const msg = document.getElementById('queueMsg');
    if (msg) msg.textContent = 'Worker detenido. Si hay una partida en ejecución, terminará o aceptará cancelación.';
  }
}

async function queueMissingGames() {
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Encolando partidas sin análisis...';
  const data = await apiPost('api/analyze.php?action=queue_missing');
  if (msg) msg.textContent = data.ok ? `Añadidas a la cola: ${data.added}. Ya existentes: ${data.existing}.` : (data.error || 'No se pudo encolar.');
  await refreshQueue(true);
}

async function retryErrors() {
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Reintentando errores/canceladas...';
  const data = await apiPost('api/analyze.php?action=retry_errors');
  if (msg) msg.textContent = data.ok ? `Análisis preparados para reintento: ${data.updated}.` : (data.error || 'No se pudo reintentar.');
  await refreshQueue(true);
}

async function cancelWaiting() {
  if (!confirm('¿Cancelar todos los análisis en cola?')) return;
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Cancelando cola pendiente...';
  const data = await apiPost('api/analyze.php?action=cancel_waiting');
  if (msg) msg.textContent = data.ok ? `Cancelados: ${data.updated}.` : (data.error || 'No se pudo cancelar.');
  await refreshQueue(true);
}

async function cancelJob(analysisId) {
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = 'Enviando cancelación...';
  const data = await apiPost('api/analyze.php?action=cancel', { analysis_id: analysisId });
  if (msg) msg.textContent = data.ok ? 'Cancelación solicitada.' : (data.error || 'No se pudo cancelar.');
  await refreshQueue(true);
}

async function requeueGame(gameId, force = false) {
  const msg = document.getElementById('queueMsg');
  if (msg) msg.textContent = force ? 'Creando reanálisis...' : 'Encolando partida...';
  const data = await apiPost('api/analyze.php?action=queue', { id: gameId, force });
  if (msg) msg.textContent = data.ok ? (data.existing ? 'La partida ya estaba en la cola.' : 'Partida encolada correctamente.') : (data.error || 'No se pudo encolar.');
  await refreshQueue(true);
}

window.addEventListener('load', () => refreshQueue());
