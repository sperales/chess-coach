let reviewData = null;
let currentMoveIndex = 0;
let bestMoveHighlight = '';
let boardOrientation = 'white';
const PIECE_ASSET_PATH = (window.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/').toString();
const INITIAL_REVIEW_PARAMS = new URLSearchParams(window.location.search);
const reviewVisitedPlies = new Set();
const reviewPendingPlies = new Set();
let reviewProgressTimer = null;
let reviewProgressData = null;
let reviewMobileTab = 'summary';
let reviewMobileMoveFilter = 'all';

const PIECE_IMAGES = {
  P: 'wp.png', N: 'wn.png', B: 'wb.png', R: 'wr.png', Q: 'wq.png', K: 'wk.png',
  p: 'bp.png', n: 'bn.png', b: 'bb.png', r: 'br.png', q: 'bq.png', k: 'bk.png'
};

const PIECE_LABELS = {
  P: 'peon blanco', N: 'caballo blanco', B: 'alfil blanco', R: 'torre blanca', Q: 'dama blanca', K: 'rey blanco',
  p: 'peon negro', n: 'caballo negro', b: 'alfil negro', r: 'torre negra', q: 'dama negra', k: 'rey negro'
};

function rEscape(s) {
  return (s || '').toString().replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

function bucketIcon(bucket) {
  return {
    best: '★', excellent: '↑', good: '✓', inaccuracy: '?!', mistake: '?', blunder: '??'
  }[bucket] || '•';
}

function bucketClass(bucket) {
  if (bucket === 'best' || bucket === 'excellent' || bucket === 'good') return 'done';
  if (bucket === 'inaccuracy') return 'cancelled';
  if (bucket === 'mistake') return 'mistake';
  if (bucket === 'blunder') return 'error';
  return 'queued';
}

function evalText(move) {
  if (!move) return '--';
  const cp = Number(move.eval_after_white ?? 0);
  if (move.eval_after_type === 'mate') {
    const rawMate = Number(move.eval_after_mate ?? 0);
    const mateDistance = Math.max(0, Math.round(Math.abs(rawMate)));
    return `${cp >= 0 ? '' : '-'}M${mateDistance}`;
  }
  const val = (cp / 100).toFixed(2);
  return (cp > 0 ? '+' : '') + val;
}

function scoreForChart(move) {
  if (!move) return 0;
  if (move.eval_after_type === 'mate') return Number(move.eval_after_white || 0) >= 0 ? 6 : -6;
  return Math.max(-6, Math.min(6, Number(move.eval_after_white ?? 0) / 100));
}

async function loadReview() {
  const gameId = Number(window.CHESS_REVIEW_GAME_ID || 0);
  const intro = document.getElementById('reviewIntro');
  if (!gameId) {
    if (intro) intro.textContent = 'No se ha indicado ninguna partida.';
    return;
  }
  try {
    const r = await fetch(`api/review.php?id=${gameId}`, { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo cargar la revisión.');
    reviewData = data;
    currentMoveIndex = initialReviewMoveIndex(data.moves || []);
    boardOrientation = data.user_side === 'b' ? 'black' : 'white';
    await loadReviewProgress();
    bindBoardControls();
    bindReviewMobileTabs();
    renderSummary();
    renderChart();
    renderMoveList();
    renderMove();
    renderBottomInsights();
  } catch (e) {
    if (intro) intro.textContent = e.message;
    const headline = document.getElementById('reviewHeadline');
    if (headline) headline.textContent = 'No hay revisión disponible';
    const comment = document.getElementById('reviewComment');
    if (comment) comment.textContent = 'Asegúrate de que la partida ya está analizada.';
  }
}

async function loadReviewProgress() {
  const gameId = Number(window.CHESS_REVIEW_GAME_ID || 0);
  if (!gameId) return;
  try {
    const response = await fetch(`api/review-progress.php?game_id=${gameId}`, { cache: 'no-store' });
    const data = await response.json();
    if (!response.ok || !data.ok) return;
    reviewProgressData = data.progress || null;
    (reviewProgressData?.visited_plies || []).forEach(ply => reviewVisitedPlies.add(Number(ply)));
    renderReviewProgress();
  } catch (error) {
    // Review remains usable if progress tracking is temporarily unavailable.
  }
}

function renderReviewProgress() {
  const el = document.getElementById('reviewProgressPill');
  if (!el || !reviewProgressData) return;
  const completed = Boolean(reviewProgressData.completed);
  const required = Number(reviewProgressData.required_plies || 0);
  const current = Math.min(Number(reviewProgressData.visited_plies_count || 0), required);
  el.className = `review-progress-pill${completed ? ' completed' : ''}`;
  el.textContent = completed ? 'Revisión completada' : `Revisión ${current}/${required}`;
  el.hidden = false;
}

function initialReviewMoveIndex(moves) {
  const requestedPly = Number(INITIAL_REVIEW_PARAMS.get('ply') || 0);
  if (!Number.isInteger(requestedPly) || requestedPly <= 0) return 0;
  const index = moves.findIndex(move => Number(move.ply || 0) === requestedPly);
  return index >= 0 ? index : 0;
}

function renderSummary() {
  const s = reviewData.summary || {};
  const g = reviewData.game || {};
  document.getElementById('reviewIntro').textContent = `${g.white_player || 'Blancas'} vs ${g.black_player || 'Negras'} · ${g.result_raw || '-'} · ${g.played_at || ''}`;
  const whiteRating = g.white_rating ? ` (${g.white_rating})` : '';
  const blackRating = g.black_rating ? ` (${g.black_rating})` : '';
  const mobileWhite = document.getElementById('reviewMobileWhite');
  const mobileBlack = document.getElementById('reviewMobileBlack');
  const mobileResult = document.getElementById('reviewMobileResult');
  if (mobileWhite) mobileWhite.textContent = `${g.white_player || 'Blancas'}${whiteRating}`;
  if (mobileBlack) mobileBlack.textContent = `${g.black_player || 'Negras'}${blackRating}`;
  if (mobileResult) mobileResult.textContent = g.result_raw || '-';
  document.getElementById('reviewHeadline').textContent = s.headline || 'Revisión de partida';
  document.getElementById('reviewComment').textContent = s.comment || 'Vamos a revisar los momentos importantes.';
  renderTagList(ensureTagList('reviewSmartTags', 'reviewComment', 'review-tags'), s.smart_tags || []);
  document.getElementById('accuracyValue').textContent = s.accuracy ?? '--';
  document.getElementById('acplValue').textContent = s.acpl ?? '--';
  document.getElementById('movesValue').textContent = s.moves ?? '--';
  const counts = s.counts || {};
  const labels = [
    ['best','Mejor'], ['excellent','Excelente'], ['good','Buena'],
    ['inaccuracy','Imprecisión'], ['mistake','Error'], ['blunder','Omisión grave']
  ];
  document.getElementById('reviewCounts').innerHTML = labels.map(([key,label]) => `
    <div class="review-count ${key}"><span>${bucketIcon(key)}</span><strong>${counts[key] || 0}</strong><small>${label}</small></div>
  `).join('');
}

function bindReviewMobileTabs() {
  const page = document.querySelector('.review-page');
  if (!page || page.dataset.reviewTabsBound === '1') return;
  page.dataset.reviewTabsBound = '1';
  page.querySelectorAll('[data-review-tab]').forEach(button => {
    button.addEventListener('click', () => setReviewMobileTab(button.dataset.reviewTab || 'summary', true));
  });
  page.querySelectorAll('[data-review-tab-target]').forEach(button => {
    button.addEventListener('click', () => setReviewMobileTab(button.dataset.reviewTabTarget || 'summary', true));
  });
  page.querySelectorAll('[data-review-sheet-close]').forEach(button => {
    button.addEventListener('click', closeReviewMobileSheet);
  });
  const sheetBody = page.querySelector('.review-mobile-sheet-body');
  if (sheetBody) sheetBody.addEventListener('click', handleReviewMobileSheetClick);
  const desktopMedia = window.matchMedia('(min-width: 761px)');
  const restoreOnDesktop = event => {
    if (event.matches) closeReviewMobileSheet();
  };
  if (typeof desktopMedia.addEventListener === 'function') desktopMedia.addEventListener('change', restoreOnDesktop);
  else if (typeof desktopMedia.addListener === 'function') desktopMedia.addListener(restoreOnDesktop);
  setReviewMobileTab(reviewMobileTab);
}

function closeReviewMobileSheet() {
  const page = document.querySelector('.review-page');
  const sheet = document.getElementById('reviewMobileSheet');
  if (!page || !sheet) return;
  page.dataset.reviewExpanded = 'false';
  sheet.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('review-sheet-open');
}

function setReviewMobileTab(tab, scrollToPanel = false) {
  const allowed = ['summary', 'analysis', 'moves', 'coach'];
  reviewMobileTab = allowed.includes(tab) ? tab : 'summary';
  const page = document.querySelector('.review-page');
  if (!page) return;
  page.dataset.reviewTab = reviewMobileTab;
  const mobile = window.matchMedia('(max-width: 760px)').matches;
  if (scrollToPanel && mobile) {
    const sheet = document.getElementById('reviewMobileSheet');
    const sheetBody = sheet?.querySelector('.review-mobile-sheet-body');
    if (sheet && sheetBody) {
      page.dataset.reviewExpanded = 'true';
      sheet.setAttribute('aria-hidden', 'false');
      document.body.classList.add('review-sheet-open');
      renderReviewMobileSheet();
    }
  }
  page.querySelectorAll('[data-review-tab]').forEach(button => {
    const active = button.dataset.reviewTab === reviewMobileTab;
    button.classList.toggle('active', active);
    button.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  if (!mobile) closeReviewMobileSheet();
}

function handleReviewMobileSheetClick(event) {
  const filter = event.target.closest('[data-review-move-filter]');
  if (filter) {
    reviewMobileMoveFilter = filter.dataset.reviewMoveFilter || 'all';
    renderReviewMobileSheet();
    return;
  }
  const moveButton = event.target.closest('[data-review-move-index]');
  if (moveButton) {
    goMove(Number(moveButton.dataset.reviewMoveIndex));
    closeReviewMobileSheet();
    return;
  }
  if (event.target.closest('[data-review-show-best]')) {
    showBestMove();
    closeReviewMobileSheet();
  }
}

function renderReviewMobileSheet() {
  const body = document.querySelector('.review-mobile-sheet-body');
  if (!body || !reviewData) return;
  if (reviewMobileTab === 'summary') body.innerHTML = reviewMobileSummaryHtml();
  else if (reviewMobileTab === 'analysis') body.innerHTML = reviewMobileAnalysisHtml();
  else if (reviewMobileTab === 'moves') body.innerHTML = reviewMobileMovesHtml();
  else body.innerHTML = reviewMobileCoachHtml();
  const chart = document.getElementById('reviewMobileEvalChart');
  if (chart) drawReviewChart(chart);
}

function reviewMobileCurrentMoveHtml() {
  const move = reviewData?.moves?.[currentMoveIndex];
  if (!move) return '';
  return `<div class="review-sheet-current ${rEscape(move.review_bucket || '')}">
    <span>${bucketIcon(move.review_bucket)}</span>
    <strong>Jugada ${Math.floor((Number(move.ply)-1)/2)+1} · ${rEscape(move.san || move.uci || '-')}</strong>
    <small>${rEscape(move.review_label || 'Jugada')}</small>
    <b>${rEscape(evalText(move))}</b>
  </div>`;
}

function reviewMobileSummaryHtml() {
  const summary = reviewData.summary || {};
  const counts = summary.counts || {};
  const result = reviewData.game?.result_raw || '-';
  const errors = Number(counts.mistake || 0) + Number(counts.blunder || 0);
  const good = Number(counts.best || 0) + Number(counts.excellent || 0) + Number(counts.good || 0);
  return `${reviewMobileCurrentMoveHtml()}
    <div class="review-sheet-metrics">
      <div><span>Resultado</span><b>${rEscape(result)}</b></div>
      <div><span>Accuracy</span><b>${rEscape(String(summary.accuracy ?? '--'))}</b></div>
      <div><span>ACPL</span><b>${rEscape(String(summary.acpl ?? '--'))}</b></div>
      <div><span>Errores</span><b class="danger">${errors}</b></div>
    </div>
    <p class="review-sheet-summary-text">${rEscape(summary.comment || '')}</p>
    <h3>Evaluación de la partida</h3>
    <canvas id="reviewMobileEvalChart" width="720" height="190" aria-label="Evaluación de la partida"></canvas>
    <div class="review-sheet-highlights">
      <article><strong>Lo mejor</strong><b>${good}</b><p>Jugadas sólidas en esta partida.</p></article>
      <article class="warning"><strong>A mejorar</strong><b>${errors}</b><p>Decisiones que merecen revisión.</p></article>
    </div>`;
}

function reviewMobileAnalysisHtml() {
  const move = reviewData?.moves?.[currentMoveIndex];
  if (!move) return '<p>No hay jugada seleccionada.</p>';
  const best = move.bestmove_display || move.bestmove_human || 'No disponible';
  return `${reviewMobileCurrentMoveHtml()}
    <article class="review-sheet-explanation"><p>${rEscape(move.explanation || '')}</p></article>
    <h3>Mejor línea</h3>
    <button class="review-sheet-line" type="button" data-review-show-best ${move.has_relevant_alternative ? '' : 'disabled'}>
      <span>${rEscape(best)}</span><b>›</b>
    </button>
    <h3>Etiquetas de la jugada</h3>
    <div class="smart-tag-list">${filteredMoveTags(move).map(smartTagChip).join('') || '<span class="muted">Sin etiquetas adicionales.</span>'}</div>`;
}

function reviewMobileMovesHtml() {
  const moves = reviewData.moves || [];
  const filters = [['all','Todas'],['key','Claves'],['errors','Errores'],['good','Buenas']];
  const visible = moves.map((move,index) => ({move,index})).filter(({move}) => {
    if (reviewMobileMoveFilter === 'errors') return ['inaccuracy','mistake','blunder'].includes(move.review_bucket);
    if (reviewMobileMoveFilter === 'good') return ['best','excellent','good'].includes(move.review_bucket);
    if (reviewMobileMoveFilter === 'key') return move.has_relevant_alternative || ['mistake','blunder'].includes(move.review_bucket);
    return true;
  });
  return `<div class="review-sheet-filters">${filters.map(([key,label]) => `<button type="button" class="${reviewMobileMoveFilter===key?'active':''}" data-review-move-filter="${key}">${label}</button>`).join('')}</div>
    <div class="review-sheet-move-head"><span>Jugada</span><span>Eval.</span><span>Calidad</span></div>
    <div class="review-sheet-moves">${visible.map(({move,index}) => `<button type="button" class="${index===currentMoveIndex?'active':''}" data-review-move-index="${index}"><strong>${Math.floor((Number(move.ply)-1)/2)+1}${Number(move.ply)%2===1?'.':'...'}${rEscape(move.san || move.uci || '-')}</strong><span>${rEscape(evalText(move))}</span><em class="${rEscape(move.review_bucket || '')}">${bucketIcon(move.review_bucket)} ${rEscape(move.review_label || '')}</em><b>›</b></button>`).join('') || '<p class="muted">No hay jugadas para este filtro.</p>'}</div>`;
}

function reviewMobileCoachHtml() {
  const summary = reviewData.summary || {};
  const counts = summary.counts || {};
  const good = Number(counts.best || 0) + Number(counts.excellent || 0) + Number(counts.good || 0);
  const bad = Number(counts.mistake || 0) + Number(counts.blunder || 0);
  const critical = (reviewData.moves || []).map((move,index) => ({move,index})).filter(({move}) => ['mistake','blunder'].includes(move.review_bucket)).slice(0,2);
  return `<section class="review-sheet-coach-head"><span class="nova-avatar nova-avatar--neutral" role="img" aria-label="Nova"></span><div><strong>Diagnóstico de la partida</strong><p>${rEscape(summary.comment || '')}</p></div></section>
    <article class="review-sheet-coach-note success"><strong>Lo que hiciste bien</strong><p>${good} jugadas mantuvieron o mejoraron tu posición.</p></article>
    <article class="review-sheet-coach-note warning"><strong>Patrón a mejorar</strong><p>${bad ? `${bad} decisiones críticas cambiaron el rumbo de la partida.` : 'No aparecen errores graves propios.'}</p></article>
    <h3>Momentos relacionados</h3>
    <div class="review-sheet-related">${critical.map(({move,index}) => `<button type="button" data-review-move-index="${index}">Revisar jugada ${Math.floor((Number(move.ply)-1)/2)+1} ›</button>`).join('') || '<span class="muted">Sin momentos críticos pendientes.</span>'}</div>
    <a class="review-sheet-training-link" href="training.php">Empezar entrenamiento ›</a>`;
}

function ensureTagList(id, afterId, extraClass) {
  let el = document.getElementById(id);
  if (el) return el;
  const after = document.getElementById(afterId);
  if (!after || !after.parentNode) return null;
  el = document.createElement('div');
  el.id = id;
  el.className = `smart-tag-list ${extraClass || ''}`.trim();
  after.parentNode.insertBefore(el, after.nextSibling);
  return el;
}

function smartTagClass(tag) {
  const severity = tag && tag.severity ? tag.severity : 'info';
  const category = tag && tag.category ? tag.category : '';
  if (category === 'positive') return 'positive';
  return severity;
}

function smartTagChip(tag) {
  return `<span class="smart-tag ${smartTagClass(tag)}" title="${rEscape(tag.tag_code || '')}">${rEscape(tag.label || tag.tag_code || '')}</span>`;
}

function renderTagList(el, tags, limit = 8) {
  if (!el) return;
  const visible = (tags || []).slice(0, limit);
  if (!visible.length) {
    el.innerHTML = '';
    return;
  }
  const more = (tags || []).length > visible.length ? `<span class="smart-tag more">+${(tags || []).length - visible.length}</span>` : '';
  el.innerHTML = visible.map(smartTagChip).join('') + more;
}

function normalizeTagText(value) {
  return (value || '').toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

function isEvaluationDuplicateTag(tag, move) {
  const bucket = (move && move.review_bucket ? move.review_bucket : '').toString();
  const label = normalizeTagText(move && move.review_label);
  const code = normalizeTagText(tag && tag.tag_code);
  const tagLabel = normalizeTagText(tag && tag.label);
  const aliases = {
    inaccuracy: ['imprecision', 'inaccuracy'],
    mistake: ['error', 'error importante', 'mistake'],
    blunder: ['omision grave', 'blunder'],
    excellent: ['excelente'],
    good: ['buena'],
    best: ['mejor']
  };
  const values = new Set([label, ...(aliases[bucket] || [])].filter(Boolean));
  return values.has(tagLabel) || values.has(code);
}

function filteredMoveTags(move) {
  return (move.smart_tags || []).filter(tag => !isEvaluationDuplicateTag(tag, move));
}

function renderBottomInsights() {
  const tip = document.getElementById('reviewCoachTip');
  const insights = document.getElementById('reviewInsights');
  if (!reviewData || !insights) return;
  const summary = reviewData.summary || {};
  const counts = summary.counts || {};
  const smartTags = summary.smart_tags || [];
  const weaknessCount = Number(counts.mistake || 0) + Number(counts.blunder || 0);
  const strengthCount = Number(counts.best || 0) + Number(counts.excellent || 0) + Number(counts.good || 0);
  const opportunityCount = Number(counts.inaccuracy || 0);
  const endgameTag = smartTags.find(tag => normalizeTagText(tag.label || tag.tag_code).includes('final'));
  const focusLabel = endgameTag ? 'Finales' : weaknessCount > 0 ? 'Revisión' : opportunityCount > 0 ? 'Precisión' : 'Consolidar';
  const focusDetail = endgameTag ? 'Errores en las últimas jugadas' : weaknessCount > 0 ? 'Omisiones graves detectadas' : opportunityCount > 0 ? 'Evita pequeñas pérdidas repetidas' : 'Mantén el plan de mejora';

  if (tip) tip.textContent = coachTipText(summary, counts, smartTags);
  insights.innerHTML = [
    insightCard('strength', 'Fortalezas', strengthCount, strengthCount > 0 ? 'Jugadas sólidas encontradas' : 'Sin fortalezas claras aún', topPositiveTag(smartTags)),
    insightCard('opportunity', 'Oportunidades', opportunityCount, opportunityCount > 0 ? 'Evita errores de precisión' : 'No hay imprecisiones relevantes', 'Desarrolla con más ritmo'),
    insightCard('review', 'A revisar', weaknessCount, weaknessCount > 0 ? 'Errores e imprecisiones' : 'Sin errores graves detectados', weaknessCount > 0 ? 'Omisiones graves detectadas' : 'Partida limpia en lo crítico'),
    insightCard('focus', 'Enfoque', focusLabel, focusDetail, endgameTag ? 'Finales' : 'Siguiente revisión recomendada')
  ].join('');
}

function coachTipText(summary, counts, smartTags) {
  const hasEndgame = smartTags.some(tag => normalizeTagText(tag.label || tag.tag_code).includes('final'));
  if (Number(counts.blunder || 0) > 0) return 'Empieza por las omisiones graves: suelen explicar dónde cambió la partida.';
  if (Number(counts.mistake || 0) > 0) return 'Reduce primero los errores importantes antes de buscar jugadas brillantes.';
  if (hasEndgame) return 'Revisa los finales: es donde tu ventaja o resistencia necesita más precisión.';
  if (Number(summary.accuracy || 0) >= 80) return 'Buen trabajo: revisa tus mejores decisiones y conviértelas en hábito.';
  return 'Controlar el centro desde el inicio te da más opciones y limita las piezas del rival.';
}

function topPositiveTag(tags) {
  const positive = (tags || []).find(tag => tag.category === 'positive');
  return positive ? (positive.label || positive.tag_code || 'Buen recurso') : 'Buen control del centro';
}

function insightCard(type, title, value, line, note) {
  if (type === 'focus') {
    return `
      <article class="review-insight-card ${type}">
        <div>
          <strong>${rEscape(title)}</strong>
          <p class="review-insight-focus-value"><b>${rEscape(String(value))}</b></p>
          <small>${rEscape(line)}</small>
        </div>
        <span class="review-insight-icon" aria-hidden="true">${reviewInsightIcon(type)}</span>
      </article>
    `;
  }
  return `
    <article class="review-insight-card ${type}">
      <div>
        <strong>${rEscape(title)}</strong>
        <p><b>${rEscape(String(value))}</b> ${rEscape(line)}</p>
        <small>${rEscape(note)}</small>
      </div>
      <span class="review-insight-icon" aria-hidden="true">${reviewInsightIcon(type)}</span>
    </article>
  `;
}

function reviewInsightIcon(type) {
  return {
    strength: '↑',
    opportunity: '◎',
    review: '!',
    focus: '♜'
  }[type] || '•';
}

function renderChart() {
  const canvas = document.getElementById('evalChart');
  drawReviewChart(canvas);
}

function drawReviewChart(canvas) {
  const moves = (reviewData && reviewData.moves) || [];
  if (!canvas || !moves.length) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width;
  const h = canvas.height;
  ctx.clearRect(0,0,w,h);
  ctx.fillStyle = '#0d171d';
  ctx.fillRect(0,0,w,h);
  const verticalPad = 22;
  const step = moves.length > 1 ? w / (moves.length - 1) : 0;
  const points = moves.map((m,i) => ({
    x: i*step,
    y: h/2 - (scoreForChart(m) / 6) * (h/2 - verticalPad)
  }));
  ctx.beginPath();
  ctx.moveTo(points[0].x, h);
  points.forEach(point => ctx.lineTo(point.x, point.y));
  ctx.lineTo(points[points.length - 1].x, h);
  ctx.closePath();
  ctx.fillStyle = 'rgba(47,202,90,.26)';
  ctx.fill();
  ctx.strokeStyle = '#c8c8c3';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(0,h/2); ctx.lineTo(w,h/2); ctx.stroke();
  ctx.beginPath();
  ctx.lineWidth = 3;
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#ffffff';
  points.forEach((point,i) => {
    if (i === 0) ctx.moveTo(point.x,point.y); else ctx.lineTo(point.x,point.y);
  });
  ctx.stroke();
  moves.forEach((m,i) => {
    const { x, y } = points[i];
    const bucket = m.review_bucket;
    if (!['inaccuracy','mistake','blunder'].includes(bucket)) return;
    ctx.beginPath();
    ctx.fillStyle = bucket === 'blunder' ? '#ef5350' : bucket === 'mistake' ? '#ff9f43' : '#f5b942';
    ctx.arc(x,y,6,0,Math.PI*2); ctx.fill();
  });
}

function renderMoveList() {
  const el = document.getElementById('moveList');
  const moves = (reviewData && reviewData.moves) || [];
  if (!el) return;
  const rows = [];
  moves.forEach((move, index) => {
    const moveNo = Math.floor((Number(move.ply) - 1) / 2) + 1;
    if (!rows[moveNo - 1]) rows[moveNo - 1] = { moveNo, white: null, black: null };
    if (Number(move.ply) % 2 === 1) {
      rows[moveNo - 1].white = { move, index };
    } else {
      rows[moveNo - 1].black = { move, index };
    }
  });

  el.innerHTML = rows.map(row => `
    <div class="move-list-row">
      <span class="move-list-number">${row.moveNo}.</span>
      ${moveListCell(row.white, 'white')}
      ${moveListCell(row.black, 'black')}
    </div>
  `).join('');
}

function moveListCell(item, side) {
  if (!item) return `<div class="move-list-cell ${side} empty" aria-hidden="true"></div>`;
  const m = item.move;
  return `
    <button class="move-list-cell ${side} ${item.index===currentMoveIndex?'active':''}" onclick="goMove(${item.index})">
      <strong>${rEscape(m.san || m.uci || '-')}</strong>
      <em class="${m.review_bucket}">${bucketIcon(m.review_bucket)} ${rEscape(m.review_label)}</em>
      ${moveTagsSummary(m)}
    </button>
  `;
}

function moveTagsSummary(move) {
  const tags = filteredMoveTags(move).slice(0, 2);
  if (!tags.length) return '';
  return `<div class="smart-tag-list move-list-tags">${tags.map(smartTagChip).join('')}</div>`;
}

function renderMove() {
  const moves = (reviewData && reviewData.moves) || [];
  const m = moves[currentMoveIndex];
  if (!m) return;
  bestMoveHighlight = '';
  const moveNo = Math.floor((Number(m.ply)-1)/2)+1;
  const side = Number(m.ply)%2===1 ? 'Blancas' : 'Negras';
  document.getElementById('moveTitle').textContent = `Movimiento ${moveNo} · ${side}`;
  const mobileCurrent = document.getElementById('reviewMobileCurrent');
  if (mobileCurrent) mobileCurrent.textContent = `Jugada ${moveNo} · ${side}`;
  const badge = document.getElementById('moveBadge');
  badge.className = `queue-status ${bucketClass(m.review_bucket)}`;
  badge.textContent = `${bucketIcon(m.review_bucket)} ${m.review_label}`;
  const mobileMoveIcon = document.getElementById('reviewMobileMoveIcon');
  const mobileMoveSan = document.getElementById('reviewMobileMoveSan');
  const mobileMoveLabel = document.getElementById('reviewMobileMoveLabel');
  const mobileMoveEval = document.getElementById('reviewMobileMoveEval');
  const mobileFeedbackTitle = document.getElementById('reviewMobileFeedbackTitle');
  const mobileFeedbackText = document.getElementById('reviewMobileFeedbackText');
  if (mobileMoveIcon) {
    mobileMoveIcon.className = `review-mobile-move-icon ${m.review_bucket}`;
    mobileMoveIcon.textContent = bucketIcon(m.review_bucket);
  }
  if (mobileMoveSan) mobileMoveSan.textContent = m.san || m.uci || '-';
  if (mobileMoveLabel) mobileMoveLabel.textContent = m.review_label || 'Jugada';
  if (mobileMoveEval) mobileMoveEval.textContent = evalText(m);
  if (mobileFeedbackTitle) mobileFeedbackTitle.textContent = `${m.san || m.uci || 'La jugada'} es ${String(m.review_label || 'jugada').toLowerCase()}`;
  if (mobileFeedbackText) mobileFeedbackText.textContent = m.explanation || 'Revisa la posición y compárala con la mejor alternativa.';
  document.getElementById('moveSan').textContent = `${m.san || m.uci} es ${m.review_label.toLowerCase()}`;
  document.getElementById('moveEval').textContent = evalText(m);
  document.getElementById('moveExplanation').textContent = m.explanation || '';
  const bestMoveBtn = document.getElementById('bestMoveBtn');
  if (bestMoveBtn) bestMoveBtn.hidden = !m.has_relevant_alternative;
  renderTagList(ensureTagList('moveSmartTags', 'moveExplanation', 'move-tags'), filteredMoveTags(m));
  renderBoard(m.fen_after, m.uci);
  renderMoveList();
  if (document.querySelector('.review-page')?.dataset.reviewExpanded === 'true') renderReviewMobileSheet();
  queueReviewProgress(Number(m.ply || 0));
}

function queueReviewProgress(ply) {
  if (!Number.isInteger(ply) || ply <= 0 || reviewVisitedPlies.has(ply)) return;
  reviewVisitedPlies.add(ply);
  reviewPendingPlies.add(ply);
  window.clearTimeout(reviewProgressTimer);
  reviewProgressTimer = window.setTimeout(() => flushReviewProgress(false), 650);
}

async function flushReviewProgress(keepalive) {
  const gameId = Number(window.CHESS_REVIEW_GAME_ID || 0);
  const plies = Array.from(reviewPendingPlies);
  if (!gameId || !plies.length) return;
  plies.forEach(ply => reviewPendingPlies.delete(ply));
  try {
    const response = await fetch('api/review-progress.php', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ game_id: gameId, plies }),
      keepalive: Boolean(keepalive)
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar el progreso de revisión.');
    reviewProgressData = data.progress || reviewProgressData;
    renderReviewProgress();
  } catch (error) {
    plies.forEach(ply => reviewPendingPlies.add(ply));
  }
}

function renderBoard(fen, uci, bestUci = '') {
  const board = document.getElementById('reviewBoard');
  if (!board) return;
  const [placement] = (fen || '').split(' ');
  const grid = boardGridFromPlacement(placement || '');
  const from = uci ? uci.slice(0,2) : '';
  const to = uci ? uci.slice(2,4) : '';
  const bestFrom = bestUci ? bestUci.slice(0,2) : '';
  const bestTo = bestUci ? bestUci.slice(2,4) : '';
  let html = '';
  const ranks = boardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const files = boardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  for (let rankIndex = 0; rankIndex < ranks.length; rankIndex++) {
    const r = ranks[rankIndex];
    for (let fileIndex = 0; fileIndex < files.length; fileIndex++) {
      const file = files[fileIndex];
      const rankLabel = fileIndex === 0 ? String(8 - r) : '';
      const fileLabel = rankIndex === ranks.length - 1 ? String.fromCharCode(97 + file) : '';
      html += squareHtml(r, file, '', from, to, grid[r][file] || '', bestFrom, bestTo, rankLabel, fileLabel);
    }
  }
  board.innerHTML = html;
  board.dataset.orientation = boardOrientation;
  renderBoardCoordinates();
}

function renderBoardCoordinates() {
  const ranksEl = document.getElementById('reviewBoardRanks');
  const filesEl = document.getElementById('reviewBoardFiles');
  const frame = document.getElementById('reviewBoardFrame');
  if (!ranksEl || !filesEl) return;
  const ranks = boardOrientation === 'black' ? [1,2,3,4,5,6,7,8] : [8,7,6,5,4,3,2,1];
  const files = boardOrientation === 'black' ? ['h','g','f','e','d','c','b','a'] : ['a','b','c','d','e','f','g','h'];
  ranksEl.innerHTML = ranks.map(rank => `<span>${rank}</span>`).join('');
  filesEl.innerHTML = files.map(file => `<span>${file}</span>`).join('');
  if (frame) frame.dataset.orientation = boardOrientation;
}

function boardGridFromPlacement(placement) {
  const rows = placement.split('/');
  const grid = Array.from({ length: 8 }, () => Array(8).fill(''));
  for (let r = 0; r < 8; r++) {
    let file = 0;
    for (const ch of rows[r] || '') {
      if (/\d/.test(ch)) {
        file += Number(ch);
      } else if (file < 8) {
        grid[r][file++] = ch;
      }
    }
  }
  return grid;
}

function squareHtml(r,file,piece,from,to,pieceCode='',bestFrom='',bestTo='',rankLabel='',fileLabel='') {
  const sq = String.fromCharCode(97+file) + (8-r);
  const dark = (r+file)%2===1;
  const hl = sq === from ? ' from' : sq === to ? ' to' : '';
  const best = sq === bestFrom ? ' best-from' : sq === bestTo ? ' best-to' : '';
  const coordinates = `${rankLabel ? `<span class="sq-coordinate sq-coordinate-rank">${rankLabel}</span>` : ''}${fileLabel ? `<span class="sq-coordinate sq-coordinate-file">${fileLabel}</span>` : ''}`;
  return `<div class="sq ${dark?'dark':'light'}${hl}${best}" data-sq="${sq}">${pieceImageHtml(pieceCode)}${coordinates}</div>`;
}

function pieceImageHtml(pieceCode) {
  const file = PIECE_IMAGES[pieceCode];
  if (!file) return '';
  const colorClass = pieceCode === pieceCode.toUpperCase() ? 'white-piece' : 'black-piece';
  return `<img class="board-piece ${colorClass}" src="${PIECE_ASSET_PATH}${file}" alt="${rEscape(PIECE_LABELS[pieceCode] || 'pieza')}" draggable="false">`;
}

function bindBoardControls() {
  const btn = document.getElementById('flipBoardBtn');
  if (!btn || btn.dataset.bound === '1') return;
  btn.dataset.bound = '1';
  btn.addEventListener('click', () => {
    boardOrientation = boardOrientation === 'white' ? 'black' : 'white';
    renderMove();
  });
}

function goMove(i) {
  const moves = (reviewData && reviewData.moves) || [];
  if (i < 0 || i >= moves.length) return;
  currentMoveIndex = i;
  renderMove();
}
function prevMove(){ goMove(currentMoveIndex - 1); }
function nextMove(){ goMove(currentMoveIndex + 1); }
function resetMove(){ goMove(0); }
function showBestMove(){
  const moves = (reviewData && reviewData.moves) || [];
  const m = moves[currentMoveIndex];
  if (!m || !m.has_relevant_alternative) return;
  const explanation = document.getElementById('moveExplanation');
  const best = m.bestmove_display || m.bestmove_human || 'no disponible';
  bestMoveHighlight = (m.bestmove || '').toString().toLowerCase();
  if (bestMoveHighlight.length >= 4) renderBoard(m.fen_before || m.fen_after, '', bestMoveHighlight);
  explanation.textContent = `Mejor alternativa según Stockfish: ${best}. Úsalo como pista, no como una línea para memorizar.`;
}

window.addEventListener('load', loadReview);
window.addEventListener('pagehide', () => flushReviewProgress(true));
