let games = [];
let currentPage = 1;
let pagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.gamesPerPage) || 50, total: 0, pages: 1 };
let stats = { global: { total: 0, wins: 0, losses: 0, draws: 0, avg_per_day: '0.00' }, recent10: { total: 0, wins: 0, losses: 0, draws: 0, avg_per_day: '0.00' } };
let analyzing = new Set();
let pollTimer = null;
let allGamesMode = false;

async function load(page = currentPage) {
  if (!document.getElementById('rows') && !document.getElementById('stats')) return;
  currentPage = Math.max(1, Number(page) || 1);
  const perPage = pagination.per_page || 50;
  const r = await fetch(`api/games.php?action=list&page=${currentPage}&per_page=${perPage}`);
  const j = await r.json();
  games = j.games || [];
  pagination = j.pagination || pagination;
  currentPage = pagination.page || currentPage;
  stats = j.stats || stats;
  render();
  schedulePollingIfNeeded();
}

function render() {
  renderStats();
  renderRows();
  renderPagination();
  updateGamesToggleLink();
}

function renderStats() {
  const el = document.getElementById('stats');
  if (!el) return;
  const st = stats.recent10 || { total: 0, wins: 0, losses: 0, draws: 0, avg_per_day: '0.00' };
  const winRate = st.total ? Math.round((st.wins || 0) * 100 / st.total) : 0;
  const pending = (stats.queue && typeof stats.queue.pending_total !== 'undefined') ? stats.queue.pending_total : games.filter(g => !g.analysis_status || g.analysis_status === 'queued' || g.analysis_status === 'running').length;
  const cards = [
    ['pulse','Partidas (10 días)', st.total || 0, '↗ actividad reciente'],
    ['target','Win Rate', `${winRate}%`, `${st.wins || 0} victorias / ${st.total || 0}`],
    ['star','Accuracy media', '--', 'se calculará con análisis'],
    ['clock','Pendientes de análisis', pending, 'Ver cola →'],
  ];
  el.innerHTML = cards.map(c => `<article class="metric-card ${c[0]}"><div class="metric-icon">${iconFor(c[0])}</div><div><span>${c[1]}</span><b>${c[2]}</b><small>${c[3]}</small></div></article>`).join('');
}

function iconFor(k){ return k==='pulse'?'⌁':k==='target'?'◎':k==='star'?'★':'◷'; }

function renderRows() {
  const el = document.getElementById('rows');
  if (!el) return;
  const list = allGamesMode ? games : games.slice(0, 5);
  el.innerHTML = list.map(g => `<tr><td>${opponentCell(g)}</td><td>${resultBadge(g)}</td><td>${escapeHtml(g.event_name || rhythmFromSite(g.site) || '-')}</td><td class="hide-sm">${g.played_at || (g.imported_at || '').slice(0,10) || '-'}</td><td>${analysisCell(g)}</td></tr>`).join('') || `<tr><td colspan="5" class="muted">Todavía no hay partidas. Empieza importando tus PGN o desde Chess.com.</td></tr>`;
}

function opponentCell(g) {
  const me = (window.CHESS_COACH_USERNAME || 'sperales').toLowerCase();
  const white = (g.white_player || '').toLowerCase();
  const opp = white === me ? g.black_player : g.white_player;
  const symbol = g.user_result === 'win' ? '★' : g.user_result === 'loss' ? '×' : '=';
  const cls = g.user_result === 'win' ? 'win-dot' : g.user_result === 'loss' ? 'loss-dot' : 'draw-dot';
  return `<span class="opponent"><i class="${cls}">${symbol}</i><span>vs. ${escapeHtml(opp || 'Rival')}</span></span>`;
}

function rhythmFromSite(site) {
  if (!site) return '';
  if (/live/i.test(site)) return 'Rapid';
  return '';
}

function renderPagination() {
  const el = document.getElementById('pagination');
  const info = document.getElementById('pageInfo');
  if (!el) return;
  const total = pagination.total || 0;
  const pages = pagination.pages || 1;
  const page = pagination.page || 1;
  const perPage = pagination.per_page || 50;
  const start = total ? ((page - 1) * perPage) + 1 : 0;
  const end = Math.min(total, page * perPage);
  if (info) info.textContent = total ? `${start}-${end} de ${total}` : '0 partidas';
  if (!allGamesMode || pages <= 1) { el.innerHTML = ''; return; }
  el.innerHTML = `<button class="secondary small" ${page <= 1 ? 'disabled' : ''} onclick="goPage(${page - 1})">‹ Anterior</button><span class="page-current">Página ${page} de ${pages}</span><button class="secondary small" ${page >= pages ? 'disabled' : ''} onclick="goPage(${page + 1})">Siguiente ›</button>`;
}

function goPage(page) {
  if (page < 1 || page > (pagination.pages || 1) || page === currentPage) return;
  allGamesMode = true;
  load(page);
}

function updateGamesToggleLink() {
  const link = document.getElementById('gamesToggleLink');
  if (!link) return;
  link.textContent = allGamesMode ? 'Ver menos' : 'Ver todas';
  link.setAttribute('aria-expanded', allGamesMode ? 'true' : 'false');
}

function showAllGames(e){
  if(e) e.preventDefault();
  allGamesMode = !allGamesMode;
  if (!allGamesMode && currentPage !== 1) {
    load(1);
    return;
  }
  render();
}

function resultBadge(g) {
  const cls = g.user_result === 'win' ? 'result-win' : g.user_result === 'loss' ? 'result-loss' : g.user_result === 'draw' ? 'result-draw' : 'result-unknown';
  return `<span class="result-badge ${cls}" title="${escapeHtml(g.user_result || '')}">${escapeHtml(g.result_raw || '-')}</span>`;
}

function analysisCell(g) {
  const localBusy = analyzing.has(Number(g.id));
  const status = g.analysis_status || '';
  if (localBusy) return `<button class="secondary small" disabled>Encolando...</button>`;
  if (status === 'queued') return `<span class="queue-status queued">En cola</span>`;
  if (status === 'running') return `<span class="queue-status running">Analizando</span>`;
  if (status === 'done') return `<span class="status-mini">B:${g.blunders || 0} E:${g.mistakes || 0} I:${g.inaccuracies || 0}</span> <a class="btn secondary small" href="review.php?id=${g.id}">Revisar</a> <button class="secondary small" onclick="analyzeGame(${g.id}, true)">Reanalizar</button>`;
  if (status === 'error') return `<button class="secondary small" onclick="analyzeGame(${g.id}, true)">Reintentar</button>`;
  if (status === 'cancelled') return `<button class="secondary small" onclick="analyzeGame(${g.id}, true)">Encolar</button>`;
  return `<button class="secondary small" onclick="analyzeGame(${g.id})">Encolar</button>`;
}

async function analyzeGame(id, force = false) {
  id = Number(id);
  if (analyzing.has(id)) return;
  analyzing.add(id); render();
  try {
    const r = await fetch('api/analyze.php?action=queue', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, force }) });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || 'No se pudo añadir a la cola.');
    location.href = 'analysis-pending.php';
  } catch (e) { analyzing.delete(id); setMessage(e.message); render(); }
}

function analyzePendingVisible(){ location.href = 'analysis-pending.php'; }
function reviewLastGame(){ const firstDone = games.find(g => g.analysis_status === 'done'); if (firstDone) location.href = `review.php?id=${firstDone.id}`; else location.href = 'analysis-pending.php'; }

function startPolling() { if (!pollTimer) pollTimer = setInterval(load, 2500); }
function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
function schedulePollingIfNeeded() {
  const busy = games.some(g => g.analysis_status === 'queued' || g.analysis_status === 'running');
  for (const g of games) if (g.analysis_status !== 'queued' && g.analysis_status !== 'running') analyzing.delete(Number(g.id));
  if (busy || analyzing.size) startPolling(); else stopPolling();
}

function escapeHtml(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
function setMessage(text){ const msg = document.getElementById('msg'); if (msg) msg.textContent = text; }

function toggleImport() {
  const card = document.getElementById('importCard'); if (!card) return;
  const btn = card.querySelector('.section-toggle');
  const expanded = card.classList.toggle('open');
  card.classList.toggle('collapsed', !expanded);
  if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

async function importPgn() {
  const pgnEl = document.getElementById('pgn'); if (!pgnEl) return;
  const pgn = pgnEl.value;
  const r = await fetch('api/games.php?action=import', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pgn }) });
  const j = await r.json();
  setMessage(j.ok ? `Importadas: ${j.added}. Duplicadas: ${j.skipped}. Encoladas automáticamente para análisis.` : j.error);
  if (j.ok) { pgnEl.value = ''; }
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(() => {});
load(1);
