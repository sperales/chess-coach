let reviewData = null;
let currentMoveIndex = 0;
let showingBest = false;

const PIECES = {
  P:'♙', N:'♘', B:'♗', R:'♖', Q:'♕', K:'♔',
  p:'♟', n:'♞', b:'♝', r:'♜', q:'♛', k:'♚'
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

function renderChart() {
  const canvas = document.getElementById('evalChart');
  const moves = (reviewData && reviewData.moves) || [];
  if (!canvas || !moves.length) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width;
  const h = canvas.height;
  ctx.clearRect(0,0,w,h);
  ctx.fillStyle = '#f7f7f3';
  ctx.fillRect(0,0,w,h);
  ctx.strokeStyle = '#c8c8c3';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(0,h/2); ctx.lineTo(w,h/2); ctx.stroke();
  const pad = 22;
  const step = moves.length > 1 ? (w - pad*2) / (moves.length - 1) : 0;
  ctx.beginPath();
  ctx.lineWidth = 3;
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#4f4d49';
  moves.forEach((m,i) => {
    const x = pad + i*step;
    const y = h/2 - (scoreForChart(m) / 6) * (h/2 - pad);
    if (i === 0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.stroke();
  moves.forEach((m,i) => {
    const x = pad + i*step;
    const y = h/2 - (scoreForChart(m) / 6) * (h/2 - pad);
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
    </button>
  `).join('');
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
  renderBoard(m.fen_after, m.uci);
  renderMoveList();
}

function renderBoard(fen, uci) {
  const board = document.getElementById('reviewBoard');
  if (!board) return;
  const [placement] = (fen || '').split(' ');
  const rows = placement.split('/');
  const from = uci ? uci.slice(0,2) : '';
  const to = uci ? uci.slice(2,4) : '';
  let html = '';
  for (let r=0; r<8; r++) {
    let file = 0;
    for (const ch of rows[r] || '') {
      if (/\d/.test(ch)) {
        const n = Number(ch);
        for (let k=0; k<n; k++) html += squareHtml(r,file++,'',from,to,'');
      } else {
        html += squareHtml(r,file++,PIECES[ch] || '',from,to,ch);
      }
    }
  }
  board.innerHTML = html;
}

function squareHtml(r,file,piece,from,to,pieceCode='') {
  const sq = String.fromCharCode(97+file) + (8-r);
  const dark = (r+file)%2===1;
  const hl = sq === from ? ' from' : sq === to ? ' to' : '';
  const pieceClass = pieceCode ? (pieceCode === pieceCode.toUpperCase() ? ' white-piece' : ' black-piece') : '';
  return `<div class="sq ${dark?'dark':'light'}${hl}" data-sq="${sq}"><span class="${pieceClass}">${piece}</span></div>`;
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
  const best = m.bestmove || 'no disponible';
  explanation.textContent = `Mejor alternativa según Stockfish: ${best}. Úsalo como pista, no como una línea para memorizar.`;
}

window.addEventListener('load', loadReview);
