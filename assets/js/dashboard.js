let games = [];
let dashboardData = null;
let currentPage = 1;
let pagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.gamesPerPage) || 50, total: 0, pages: 1 };
let stats = { recent10: { total: 0, wins: 0, losses: 0, draws: 0 }, analysis_accuracy: { average: null, analyzed_games: 0 }, queue: { pending_total: 0 } };
let analyzing = new Set();
let pollTimer = null;
let gamesPanelMode = 'latest';

async function dashboardGet(url) {
  const response = await fetch(url, { cache: 'no-store' });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudieron cargar los datos.');
  return data;
}

async function load(page = currentPage) {
  currentPage = Math.max(1, Number(page) || 1);
  const perPage = pagination.per_page || 50;
  const [gamesPayload, trainerPayload] = await Promise.all([
    dashboardGet(`api/games.php?action=list&page=${currentPage}&per_page=${perPage}`),
    dashboardGet('api/dashboard.php')
  ]);

  games = gamesPayload.games || [];
  pagination = gamesPayload.pagination || pagination;
  currentPage = pagination.page || currentPage;
  stats = gamesPayload.stats || stats;
  dashboardData = trainerPayload;

  render();
  schedulePollingIfNeeded();
}

function render() {
  renderStats();
  renderTrainerDashboard();
  renderRows();
  renderPagination();
  renderPatterns();
  updateGamesPanelTabs();
}

function renderStats() {
  const el = document.getElementById('stats');
  if (!el) return;
  const global = stats.global || { total: 0, wins: 0, losses: 0, draws: 0 };
  const accuracy = stats.analysis_accuracy || { average: null, analyzed_games: 0 };
  const winRate = global.total ? Math.round((global.wins || 0) * 100 / global.total) : 0;
  const pending = (stats.queue && typeof stats.queue.pending_total !== 'undefined') ? stats.queue.pending_total : 0;
  const analyzedGames = Number(accuracy.analyzed_games || 0);
  const avgAccuracy = accuracy.average === null || typeof accuracy.average === 'undefined' ? null : Number(accuracy.average);
  const cards = [
    { kind: 'pulse', label: 'Partidas', value: global.total || 0, detail: 'Ver todas', href: 'games.php' },
    { kind: 'target', label: 'Win Rate', value: `${winRate}%`, detail: `${global.wins || 0} victorias / ${global.total || 0}` },
    { kind: 'star', label: 'Accuracy media', value: avgAccuracy === null ? '--' : `${avgAccuracy.toFixed(1)}%`, detail: analyzedGames ? `${analyzedGames} partidas analizadas` : 'sin partidas analizadas' },
    { kind: 'clock', label: 'Pendientes de análisis', value: pending, detail: 'Ver cola' }
  ];
  el.innerHTML = cards.map(card => {
    const detail = card.href
      ? `<a href="${escapeAttr(card.href)}">${escapeHtml(card.detail)}</a>`
      : escapeHtml(card.detail);
    return `<article class="metric-card ${card.kind}"><div class="metric-icon">${iconFor(card.kind)}</div><div><span>${escapeHtml(card.label)}</span><b>${escapeHtml(card.value)}</b><small>${detail}</small></div></article>`;
  }).join('');
}

function iconFor(kind) {
  return kind === 'pulse' ? '⌁' : kind === 'target' ? '◎' : kind === 'star' ? '★' : '▷';
}

function renderTrainerDashboard() {
  if (!dashboardData) return;
  renderHero();
  renderFocus();
  renderState();
  renderSummary();
  renderStrengths();
}

function renderHero() {
  const el = document.getElementById('trainerHeroText');
  const focus = (dashboardData.training_focus || [])[0];
  if (!el) return;
  if (!dashboardData.period || !dashboardData.period.available_games) {
    el.textContent = 'Importa y analiza partidas para construir tu primer plan de entrenamiento.';
    return;
  }
  el.textContent = focus ? `Tu foco principal ahora mismo: ${focus.title}.` : 'Cada partida es una oportunidad para mejorar.';
}

function renderFocus() {
  const list = document.getElementById('trainerFocusList');
  const period = document.getElementById('trainerPeriod');
  if (!list) return;
  const items = dashboardData.training_focus || [];
  const available = dashboardData.period ? Number(dashboardData.period.available_games || 0) : 0;
  const minimum = dashboardData.period ? Number(dashboardData.period.minimum_games_for_trend || 6) : 6;
  if (period) period.textContent = `${available}/10 analizadas`;
  if (!items.length) {
    list.innerHTML = `
      <div class="empty-state compact">
        <strong>No hay focos detectados todavía.</strong>
        <span>Analiza al menos ${minimum} partidas para que el diagnóstico sea más fiable.</span>
        <a href="analysis-pending.php">Ver cola de análisis</a>
      </div>
    `;
    return;
  }
  list.innerHTML = items.map((focus, index) => `
    <article class="trainer-focus-card">
      <div class="trainer-rank">${index + 1}</div>
      <div>
        <h3>${escapeHtml(focus.title || 'Foco')}</h3>
        <p>${escapeHtml(focus.description || '')}</p>
        ${focusEvidence(focus)}
        <strong>${escapeHtml(focus.recommended_action || '')}</strong>
        ${focus.games_url ? `<a href="${escapeAttr(focus.games_url)}">${focusLinkLabel(focus.games_url)}</a>` : ''}
      </div>
    </article>
  `).join('') + (available < minimum ? `<p class="muted small-note">Con ${minimum} partidas analizadas el diagnóstico será más fiable.</p>` : '');
}

function focusLinkLabel(url) {
  return url && url.indexOf('analysis-pending.php') !== -1 ? 'Ver cola de análisis' : 'Ver partidas relacionadas';
}

function focusEvidence(focus) {
  const evidence = (focus.evidence || []).slice(0, 3);
  if (!evidence.length) return '';
  return `<ul>${evidence.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
}

function renderState() {
  const stateEl = document.getElementById('trainerState');
  const actionEl = document.getElementById('trainerNextAction');
  const form = dashboardData.form || {};
  const focus = (dashboardData.training_focus || [])[0];
  if (stateEl) {
    stateEl.innerHTML = `
      <span class="trainer-state-badge ${escapeAttr(form.state || 'stable')}">${escapeHtml(form.label || 'Estado')}</span>
      <p>${escapeHtml(form.message || 'Sin datos suficientes.')}</p>
    `;
  }
  if (actionEl) {
    actionEl.textContent = focus && focus.recommended_action ? focus.recommended_action : 'Analiza más partidas para obtener una recomendación clara.';
  }
}

function renderSummary() {
  const summary = document.getElementById('trainerSummary');
  const kpis = document.getElementById('trainerMiniKpis');
  const overview = dashboardData.overview || {};
  if (summary) summary.textContent = dashboardData.summary_text || 'Cargando resumen...';
  if (!kpis) return;
  const values = [
    ['Win rate', typeof overview.score_rate === 'number' ? `${overview.score_rate}%` : '--'],
    ['ACPL', overview.avg_acpl === null || typeof overview.avg_acpl === 'undefined' ? '--' : Number(overview.avg_acpl).toFixed(1)],
    ['Errores', `${overview.own_blunders || 0}/${overview.own_mistakes || 0}/${overview.own_inaccuracies || 0}`],
    ['Color', colorNote(overview)]
  ];
  kpis.innerHTML = values.map(item => `<div><span>${escapeHtml(item[0])}</span><b>${escapeHtml(item[1])}</b></div>`).join('');
}

function colorNote(overview) {
  const white = overview.white || {};
  const black = overview.black || {};
  if (!white.games && !black.games) return '--';
  if (white.score_rate === null || black.score_rate === null) return white.games ? 'blancas' : 'negras';
  if (white.score_rate > black.score_rate) return 'mejor con blancas';
  if (black.score_rate > white.score_rate) return 'mejor con negras';
  return 'equilibrado';
}

function renderStrengths() {
  const el = document.getElementById('trainerStrengths');
  if (!el) return;
  const strengths = dashboardData.strengths || [];
  if (!strengths.length) {
    el.innerHTML = `
      <div class="empty-state compact">
        <strong>Todavía no hay fortalezas claras.</strong>
        <span>Cuando haya más partidas analizadas, aquí aparecerán patrones positivos recientes.</span>
      </div>
    `;
    return;
  }
  el.innerHTML = strengths.map(item => `
    <article class="trainer-strength">
      <strong>${escapeHtml(item.title || 'Fortaleza')}</strong>
      <span>${escapeHtml(item.evidence || '')}</span>
      ${item.games_url ? `<a href="${escapeAttr(item.games_url)}">Ver partidas</a>` : ''}
    </article>
  `).join('');
}

function renderRows() {
  const el = document.getElementById('rows');
  if (!el) return;
  if (gamesPanelMode === 'recommended') {
    const recommended = dashboardData ? (dashboardData.recommended_reviews || []) : [];
    el.innerHTML = recommended.map(recommendedRow).join('') || `
      <tr>
        <td colspan="5" class="muted">
          No hay recomendaciones todavía. Analiza más partidas para que el entrenador priorice revisiones.
        </td>
      </tr>`;
    return;
  }
  const list = games.slice(0, 5);
  el.innerHTML = list.map(gameRow).join('') || `<tr><td colspan="5" class="muted">Todavía no hay partidas. Empieza importando tus PGN o desde Chess.com.</td></tr>`;
}

function gameRow(game) {
  return `<tr><td>${opponentCell(game)}${gameTagsCell(game)}</td><td>${resultBadge(game)}</td><td>${escapeHtml(game.event_name || rhythmFromSite(game.site) || '-')}</td><td class="hide-sm">${game.played_at || (game.imported_at || '').slice(0,10) || '-'}</td><td>${analysisCell(game)}</td></tr>`;
}

function recommendedRow(item) {
  return `
    <tr>
      <td><a class="game-title-link" href="${escapeAttr(item.review_url || '#')}"><strong>${escapeHtml(item.title || 'Partida')}</strong></a><small class="recommend-reason">${escapeHtml(item.reason || '')}</small></td>
      <td>${resultBadge(item)}</td>
      <td>${item.accuracy === null || typeof item.accuracy === 'undefined' ? '--' : `${Number(item.accuracy).toFixed(1)}%`}</td>
      <td class="hide-sm">${escapeHtml(item.played_at || '-')}</td>
      <td><a class="btn secondary small" href="${escapeAttr(item.review_url || '#')}">Revisar</a></td>
    </tr>
  `;
}

function opponentCell(game) {
  const me = (window.CHESS_COACH_USERNAME || '').toLowerCase();
  const white = (game.white_player || '').toLowerCase();
  const opponent = white === me ? game.black_player : game.white_player;
  const symbol = game.user_result === 'win' ? '★' : game.user_result === 'loss' ? 'x' : '=';
  const cls = game.user_result === 'win' ? 'win-dot' : game.user_result === 'loss' ? 'loss-dot' : 'draw-dot';
  return `<span class="opponent"><i class="${cls}">${symbol}</i><span>vs. ${escapeHtml(opponent || 'Rival')}</span></span>`;
}

function rhythmFromSite(site) {
  if (!site) return '';
  if (/live/i.test(site)) return 'Rapid';
  return '';
}

function renderPagination() {
  const el = document.getElementById('pagination');
  if (!el || gamesPanelMode !== 'latest') {
    if (el) el.innerHTML = '';
    return;
  }
  el.innerHTML = '';
}

function resultBadge(game) {
  const cls = game.user_result === 'win' ? 'result-win' : game.user_result === 'loss' ? 'result-loss' : game.user_result === 'draw' ? 'result-draw' : 'result-unknown';
  return `<span class="result-badge ${cls}" title="${escapeHtml(game.user_result || '')}">${escapeHtml(game.result_raw || '-')}</span>`;
}

function analysisCell(game) {
  const localBusy = analyzing.has(Number(game.id));
  const status = game.analysis_status || '';
  const gameId = Number(game.id);
  if (localBusy) return `<button class="secondary small" disabled>Encolando...</button>`;
  if (status === 'queued') return `<span class="queue-status queued">En cola</span>`;
  if (status === 'running') return `<span class="queue-status running">Analizando</span>`;
  if (status === 'done') return `<span class="status-mini">B:${game.blunders || 0} E:${game.mistakes || 0} I:${game.inaccuracies || 0}</span> <a class="btn secondary small" href="review.php?id=${gameId}">Revisar</a> <button class="secondary small" onclick="analyzeGame(${gameId}, true)">Reanalizar</button>`;
  if (status === 'error') return `<button class="secondary small" onclick="analyzeGame(${gameId}, true)">Reintentar</button>`;
  if (status === 'cancelled') return `<button class="secondary small" onclick="analyzeGame(${gameId}, true)">Encolar</button>`;
  return `<button class="secondary small" onclick="analyzeGame(${gameId})">Encolar</button>`;
}

function smartTagClass(tag) {
  const severity = tag && tag.severity ? tag.severity : 'info';
  const category = tag && tag.category ? tag.category : '';
  if (category === 'positive') return 'positive';
  return ['critical', 'high', 'medium', 'low', 'info'].includes(severity) ? severity : 'info';
}

function smartTagChip(tag) {
  const code = tag && tag.tag_code ? tag.tag_code.toString() : '';
  const label = tag && (tag.label || tag.tag_code) ? (tag.label || tag.tag_code).toString() : '';
  const cls = smartTagClass(tag);
  if (!code) return `<span class="smart-tag ${cls}">${escapeHtml(label)}</span>`;
  return `<a class="smart-tag ${cls}" href="games.php?tag=${encodeURIComponent(code)}" title="${escapeHtml(code)}">${escapeHtml(label)}</a>`;
}

function gameTagsCell(game) {
  const tags = (game.smart_tags || []).slice(0, 3);
  if (!tags.length) return '';
  const more = (game.smart_tags || []).length > tags.length ? `<span class="smart-tag more">+${(game.smart_tags || []).length - tags.length}</span>` : '';
  return `<div class="smart-tag-list game-tags">${tags.map(smartTagChip).join('')}${more}</div>`;
}

function renderPatterns() {
  const card = document.getElementById('smartTagInsight');
  if (!card) return;
  const gameTags = dashboardData && dashboardData.patterns ? (dashboardData.patterns.game_tags || []) : [];
  const moveTags = dashboardData && dashboardData.patterns ? (dashboardData.patterns.move_tags || []) : [];
  const tags = [...gameTags, ...moveTags].slice(0, 6);
  if (!tags.length) {
    card.innerHTML = `
      <h2>Patrones detectados</h2>
      <div class="empty-state compact">
        <strong>Sin patrones detectados todavía.</strong>
        <span>Ejecuta Smart Tags sobre partidas analizadas para ver etiquetas frecuentes.</span>
        <a href="profile.php">Ir a procesos batch</a>
      </div>
    `;
    return;
  }
  card.innerHTML = `
    <h2>Patrones detectados</h2>
    <div class="smart-tag-summary">
      ${tags.map(tag => `<div><span>${smartTagChip(tag)}</span><strong>${Number(tag.total || 0)}</strong></div>`).join('')}
    </div>
  `;
}

function setGamesPanelMode(mode) {
  gamesPanelMode = mode === 'recommended' ? 'recommended' : 'latest';
  renderRows();
  renderPagination();
  updateGamesPanelTabs();
}

function updateGamesPanelTabs() {
  const latest = document.getElementById('latestTab');
  const recommended = document.getElementById('recommendedTab');
  const link = document.getElementById('gamesToggleLink');
  if (latest) latest.classList.toggle('active', gamesPanelMode === 'latest');
  if (recommended) recommended.classList.toggle('active', gamesPanelMode === 'recommended');
  if (link) link.style.display = gamesPanelMode === 'latest' ? '' : 'none';
}

async function analyzeGame(id, force = false) {
  id = Number(id);
  if (analyzing.has(id)) return;
  analyzing.add(id);
  renderRows();
  try {
    const response = await fetch('api/analyze.php?action=queue', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, force })
    });
    const data = await response.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo anadir a la cola.');
    location.href = 'analysis-pending.php';
  } catch (error) {
    analyzing.delete(id);
    renderRows();
  }
}

function analyzePendingVisible() {
  location.href = 'analysis-pending.php';
}

function reviewLastGame() {
  const firstDone = games.find(game => game.analysis_status === 'done');
  if (firstDone) location.href = `review.php?id=${firstDone.id}`;
  else location.href = 'analysis-pending.php';
}

function startPolling() {
  if (!pollTimer) pollTimer = setInterval(() => load(currentPage), 2500);
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
}

function schedulePollingIfNeeded() {
  const busy = games.some(game => game.analysis_status === 'queued' || game.analysis_status === 'running');
  for (const game of games) if (game.analysis_status !== 'queued' && game.analysis_status !== 'running') analyzing.delete(Number(game.id));
  if (busy || analyzing.size) startPolling();
  else stopPolling();
}

function escapeHtml(value) {
  return (value || '').toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(() => {});
load(1).catch(error => {
  const hero = document.getElementById('trainerHeroText');
  if (hero) hero.textContent = error.message || 'No se pudo cargar el dashboard.';
});
