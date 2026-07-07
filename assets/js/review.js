let reviewData = null;
let currentMoveIndex = 0;
let showingBest = false;
let boardOrientation = 'white';

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
    best: '★', excellent: '👍', good: '✓', inaccuracy: '?!', mistake: '?', blunder: '??'
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
    currentMoveIndex = 0;
    boardOrientation = data.user_side === 'b' ? 'black' : 'white';
    bindBoardControls();
    renderSummary();
    renderChart();
    renderMoveList();
    renderMove();
  } catch (e) {
    if (intro) intro.textContent = e.message;
    const headline = document.getElementById('reviewHeadline');
    if (headline) headline.textContent = 'No hay revisión disponible';
    const comment = document.getElementById('reviewComment');
    if (comment) comment.textContent = 'Asegúrate de que la partida ya está analizada.';
  }
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
  const pad = 22;
  const step = moves.length > 1 ? (w - pad*2) / (moves.length - 1) : 0;
  const points = moves.map((m,i) => ({
    x: pad + i*step,
    y: h/2 - (scoreForChart(m) / 6) * (h/2 - pad)
  }));
  ctx.beginPath();
  ctx.moveTo(points[0].x, h);
  points.forEach(point => ctx.lineTo(point.x, point.y));
  ctx.lineTo(points[points.length - 1].x, h);
  ctx.closePath();
  ctx.fillStyle = '#f7f7f3';
  ctx.fill();
  ctx.strokeStyle = '#c8c8c3';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(0,h/2); ctx.lineTo(w,h/2); ctx.stroke();
  ctx.beginPath();
  ctx.lineWidth = 3;
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#4f4d49';
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
  el.innerHTML = moves.map((m,i) => `
    <button class="move-list-item ${i===currentMoveIndex?'active':''}" onclick="goMove(${i})">
      <span>${Math.floor((Number(m.ply)-1)/2)+1}${Number(m.ply)%2===1?'.':'...'}</span>
      <strong>${rEscape(m.san || m.uci || '-')}</strong>
      <em class="${m.review_bucket}">${bucketIcon(m.review_bucket)} ${rEscape(m.review_label)}</em>
      ${moveTagsSummary(m)}
    </button>
  `).join('');
}

function moveTagsSummary(move) {
  const tags = (move.smart_tags || []).slice(0, 2);
  if (!tags.length) return '';
  return `<div class="smart-tag-list move-list-tags">${tags.map(smartTagChip).join('')}</div>`;
}

function renderMove() {
  const moves = (reviewData && reviewData.moves) || [];
  const m = moves[currentMoveIndex];
  if (!m) return;
  showingBest = false;
  const moveNo = Math.floor((Number(m.ply)-1)/2)+1;
  const side = Number(m.ply)%2===1 ? 'Blancas' : 'Negras';
  document.getElementById('moveTitle').textContent = `Movimiento ${moveNo} · ${side}`;
  const badge = document.getElementById('moveBadge');
  badge.className = `queue-status ${bucketClass(m.review_bucket)}`;
  badge.textContent = `${bucketIcon(m.review_bucket)} ${m.review_label}`;
  document.getElementById('moveSan').textContent = `${m.san || m.uci} es ${m.review_label.toLowerCase()}`;
  document.getElementById('moveEval').textContent = evalText(m);
  document.getElementById('moveExplanation').textContent = m.explanation || '';
  renderTagList(ensureTagList('moveSmartTags', 'moveExplanation', 'move-tags'), m.smart_tags || []);
  renderBoard(m.fen_after, m.uci);
  renderMoveList();
}

function renderBoard(fen, uci) {
  const board = document.getElementById('reviewBoard');
  if (!board) return;
  const [placement] = (fen || '').split(' ');
  const grid = boardGridFromPlacement(placement || '');
  const from = uci ? uci.slice(0,2) : '';
  const to = uci ? uci.slice(2,4) : '';
  let html = '';
  const ranks = boardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const files = boardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  for (const r of ranks) {
    for (const file of files) {
      html += squareHtml(r, file, '', from, to, grid[r][file] || '');
    }
  }
  board.innerHTML = html;
  board.dataset.orientation = boardOrientation;
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

function squareHtml(r,file,piece,from,to,pieceCode='') {
  const sq = String.fromCharCode(97+file) + (8-r);
  const dark = (r+file)%2===1;
  const hl = sq === from ? ' from' : sq === to ? ' to' : '';
  return `<div class="sq ${dark?'dark':'light'}${hl}" data-sq="${sq}">${pieceImageHtml(pieceCode)}</div>`;
}

function pieceImageHtml(pieceCode) {
  const file = PIECE_IMAGES[pieceCode];
  if (!file) return '';
  const colorClass = pieceCode === pieceCode.toUpperCase() ? 'white-piece' : 'black-piece';
  return `<img class="board-piece ${colorClass}" src="assets/pieces/${file}" alt="${rEscape(PIECE_LABELS[pieceCode] || 'pieza')}" draggable="false">`;
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
  explanation.textContent = `Mejor alternativa según Stockfish: ${best}. Úsalo como pista, no como una línea para memorizar.`;
}

window.addEventListener('load', loadReview);
