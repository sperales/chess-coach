let reviewData = null;
let currentMoveIndex = 0;
let bestMoveHighlight = '';
let boardOrientation = 'white';
const PIECE_ASSET_PATH = (window.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/').toString();
const INITIAL_REVIEW_PARAMS = new URLSearchParams(window.location.search);

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
  if (bucket === 'mistake') return 'running';
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
    bindBoardControls();
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
  const badge = document.getElementById('moveBadge');
  badge.className = `queue-status ${bucketClass(m.review_bucket)}`;
  badge.textContent = `${bucketIcon(m.review_bucket)} ${m.review_label}`;
  document.getElementById('moveSan').textContent = `${m.san || m.uci} es ${m.review_label.toLowerCase()}`;
  document.getElementById('moveEval').textContent = evalText(m);
  document.getElementById('moveExplanation').textContent = m.explanation || '';
  renderTagList(ensureTagList('moveSmartTags', 'moveExplanation', 'move-tags'), filteredMoveTags(m));
  renderBoard(m.fen_after, m.uci);
  renderMoveList();
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
  for (const r of ranks) {
    for (const file of files) {
      html += squareHtml(r, file, '', from, to, grid[r][file] || '', bestFrom, bestTo);
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

function squareHtml(r,file,piece,from,to,pieceCode='',bestFrom='',bestTo='') {
  const sq = String.fromCharCode(97+file) + (8-r);
  const dark = (r+file)%2===1;
  const hl = sq === from ? ' from' : sq === to ? ' to' : '';
  const best = sq === bestFrom ? ' best-from' : sq === bestTo ? ' best-to' : '';
  return `<div class="sq ${dark?'dark':'light'}${hl}${best}" data-sq="${sq}">${pieceImageHtml(pieceCode)}</div>`;
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
  if (!m) return;
  const explanation = document.getElementById('moveExplanation');
  const best = m.bestmove_human || 'no disponible';
  bestMoveHighlight = (m.bestmove || '').toString().toLowerCase();
  if (bestMoveHighlight.length >= 4) renderBoard(m.fen_before || m.fen_after, '', bestMoveHighlight);
  explanation.textContent = `Mejor alternativa según Stockfish: ${best}. Úsalo como pista, no como una línea para memorizar.`;
}

window.addEventListener('load', loadReview);
