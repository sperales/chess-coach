let games = [];
let currentPage = 1;
let pagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.gamesPerPage) || 50, total: 0, pages: 1 };
let availableTags = [];
const initialGameFilters = new URLSearchParams(window.location.search);
let openingKeyFilter = (initialGameFilters.get('opening_key') || '').toString();

function selectedFilters() {
  return {
    color: document.getElementById('colorFilter')?.value || '',
    result: document.getElementById('resultFilter')?.value || '',
    tag: document.getElementById('tagFilter')?.value || '',
    opening_key: openingKeyFilter,
  };
}

function queryString(page = currentPage) {
  const params = new URLSearchParams({
    action: 'list',
    page: String(Math.max(1, Number(page) || 1)),
    per_page: String(pagination.per_page || 50),
  });
  const filters = selectedFilters();
  if (filters.color) params.set('color', filters.color);
  if (filters.result) params.set('result', filters.result);
  if (filters.tag) params.set('tag', filters.tag);
  if (filters.opening_key) params.set('opening_key', filters.opening_key);
  return params.toString();
}

async function loadGames(page = currentPage) {
  currentPage = Math.max(1, Number(page) || 1);
  const r = await fetch(`api/games.php?${queryString(currentPage)}`, { cache: 'no-store' });
  const j = await r.json();
  games = j.games || [];
  pagination = j.pagination || pagination;
  currentPage = pagination.page || currentPage;
  availableTags = (j.filters && j.filters.tags) || availableTags;
  renderTagOptions();
  renderRows();
  renderPagination();
  renderStatus();
}

function renderTagOptions() {
  const select = document.getElementById('tagFilter');
  if (!select || select.dataset.loaded === '1') return;
  const current = select.value || initialGameFilters.get('tag') || '';
  select.innerHTML = '<option value="">Todas</option>' + availableTags.map(tag => `<option value="${escapeHtml(tag.tag_code || '')}">${escapeHtml(tag.label || tag.tag_code || '')}</option>`).join('');
  select.value = current;
  select.dataset.loaded = '1';
}

function renderRows() {
  const el = document.getElementById('gameRows');
  if (!el) return;
  el.innerHTML = games.map(g => `<tr><td>${opponentCell(g)}${gameTagsCell(g)}</td><td>${resultBadge(g)}</td><td>${escapeHtml(g.event_name || rhythmFromSite(g.site) || '-')}</td><td class="hide-sm">${openingCell(g)}</td><td class="hide-sm">${g.played_at || (g.imported_at || '').slice(0,10) || '-'}</td><td>${analysisStatusCell(g)}</td><td class="game-action-cell">${reviewActionCell(g)}</td><td class="game-action-cell">${reanalyzeActionCell(g)}</td></tr>`).join('') || `<tr><td colspan="8" class="muted">No hay partidas con los filtros seleccionados.</td></tr>`;
}

function renderPagination() {
  const el = document.getElementById('gamesPagination');
  const info = document.getElementById('gamesPageInfo');
  if (!el) return;
  const total = pagination.total || 0;
  const pages = pagination.pages || 1;
  const page = pagination.page || 1;
  const perPage = pagination.per_page || 50;
  const start = total ? ((page - 1) * perPage) + 1 : 0;
  const end = Math.min(total, page * perPage);
  if (info) info.textContent = total ? `${start}-${end} de ${total}` : '0 partidas';
  if (pages <= 1) { el.innerHTML = ''; return; }
  el.innerHTML = `<button class="secondary small" ${page <= 1 ? 'disabled' : ''} onclick="goPage(${page - 1})">Anterior</button><span class="page-current">Página ${page} de ${pages}</span><button class="secondary small" ${page >= pages ? 'disabled' : ''} onclick="goPage(${page + 1})">Siguiente</button>`;
}

function renderStatus() {
  const el = document.getElementById('gamesFilterStatus');
  if (!el) return;
  const filters = selectedFilters();
  const active = Object.values(filters).filter(Boolean).length;
  el.textContent = active ? `${active} filtro${active === 1 ? '' : 's'} activo${active === 1 ? '' : 's'}` : 'Sin filtros';
}

function goPage(page) {
  if (page < 1 || page > (pagination.pages || 1) || page === currentPage) return;
  loadGames(page);
}

function clearGameFilters() {
  document.getElementById('colorFilter').value = '';
  document.getElementById('resultFilter').value = '';
  document.getElementById('tagFilter').value = '';
  openingKeyFilter = '';
  loadGames(1);
}

function bindFilters() {
  ['colorFilter', 'resultFilter', 'tagFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => loadGames(1));
  });
}

function initializeGameFiltersFromUrl() {
  const color = document.getElementById('colorFilter');
  const result = document.getElementById('resultFilter');
  if (color && ['white', 'black'].includes(initialGameFilters.get('color') || '')) color.value = initialGameFilters.get('color');
  if (result && ['win', 'loss', 'draw'].includes(initialGameFilters.get('result') || '')) result.value = initialGameFilters.get('result');
}

function opponentCell(g) {
  const me = (window.CHESS_COACH_USERNAME || '').toLowerCase();
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

function openingCell(g) {
  const eco = (g.eco_code || '').toString().trim();
  const opening = (g.opening_name || '').toString().trim();
  const ecoUrl = safeUrl(g.eco_url || '');
  const ecoLabel = ecoUrl && eco ? `<a href="${escapeHtml(ecoUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(eco)}</a>` : escapeHtml(eco);
  if (!eco && !opening) return '-';
  if (!opening) return `<span class="opening-cell"><strong>${ecoLabel}</strong></span>`;
  const ecoText = eco ? `<small>${ecoLabel}</small>` : '';
  return `<span class="opening-cell"><strong>${escapeHtml(opening)}</strong>${ecoText}</span>`;
}

function safeUrl(value) {
  const url = (value || '').toString().trim();
  return /^https?:\/\//i.test(url) ? url : '';
}

function resultBadge(g) {
  const cls = g.user_result === 'win' ? 'result-win' : g.user_result === 'loss' ? 'result-loss' : g.user_result === 'draw' ? 'result-draw' : 'result-unknown';
  return `<span class="result-badge ${cls}" title="${escapeHtml(g.user_result || '')}">${escapeHtml(g.result_raw || '-')}</span>`;
}

function analysisStatusCell(g) {
  const status = g.analysis_status || '';
  if (status === 'queued') return `<span class="queue-status queued">En cola</span>`;
  if (status === 'running') return `<span class="queue-status running">Analizando</span>`;
  if (status === 'done') return `<span class="status-mini">B:${g.blunders || 0} E:${g.mistakes || 0} I:${g.inaccuracies || 0}</span>`;
  if (status === 'error') return `<span class="queue-status error">Error</span>`;
  if (status === 'cancelled') return `<span class="queue-status cancelled">Cancelado</span>`;
  return `<span class="queue-status queued">Pendiente</span>`;
}

function reviewActionCell(g) {
  if ((g.analysis_status || '') !== 'done') return '';
  return `<a class="btn small game-review-btn" href="review.php?id=${g.id}"><span class="action-icon action-icon-eye" aria-hidden="true"></span> Revisar</a>`;
}

function reanalyzeActionCell(g) {
  const status = g.analysis_status || '';
  if (status === 'queued' || status === 'running') return '';
  const force = status === 'done' || status === 'error' || status === 'cancelled';
  const label = status === 'done' ? 'Reanalizar' : status === 'error' ? 'Reintentar' : 'Encolar';
  return `<button class="secondary small game-analyze-btn" onclick="analyzeGame(${g.id}, ${force ? 'true' : 'false'})"><span class="action-icon action-icon-target" aria-hidden="true"></span> ${label}</button>`;
}

function smartTagClass(tag) {
  const severity = tag && tag.severity ? tag.severity : 'info';
  const category = tag && tag.category ? tag.category : '';
  if (category === 'positive') return 'positive';
  return severity;
}

function smartTagChip(tag) {
  const code = tag && tag.tag_code ? tag.tag_code.toString() : '';
  const label = tag && (tag.label || tag.tag_code) ? (tag.label || tag.tag_code).toString() : '';
  const cls = smartTagClass(tag);
  if (!code) return `<span class="smart-tag ${cls}">${escapeHtml(label)}</span>`;
  return `<a class="smart-tag ${cls}" href="games.php?tag=${encodeURIComponent(code)}" title="${escapeHtml(code)}">${escapeHtml(label)}</a>`;
}

function gameTagsCell(g) {
  const tags = (g.smart_tags || []).slice(0, 3);
  if (!tags.length) return '';
  const more = (g.smart_tags || []).length > tags.length ? `<span class="smart-tag more">+${(g.smart_tags || []).length - tags.length}</span>` : '';
  return `<div class="smart-tag-list game-tags">${tags.map(smartTagChip).join('')}${more}</div>`;
}

async function analyzeGame(id, force = false) {
  const r = await fetch('api/analyze.php?action=queue', { method: 'POST', headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ id: Number(id), force }) });
  const j = await r.json();
  if (j.ok) location.href = 'analysis-pending.php';
}

function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

initializeGameFiltersFromUrl();
bindFilters();
loadGames(1);
