let trainingExercises = [];
let trainingTypes = {};
let trainingTypeCounts = {};
let trainingStats = {};
let activeTrainingSession = null;
let trainingPagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.trainingPerPage) || 20, total: 0, pages: 1 };
let currentTrainingPage = 1;
let activeExercise = null;
let trainingBoardOrientation = 'white';
let selectedTrainingSquare = '';
let attemptedTrainingMoves = [];
let trainingStartedAt = 0;
let trainingTimerInterval = null;
let trainingUsedHint = false;
let trainingHintFrom = '';
let revealedTrainingSolution = '';
const TRAINING_PIECE_ASSET_PATH = (window.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/').toString();

const TRAINING_PIECE_IMAGES = {
  P: 'wp.png', N: 'wn.png', B: 'wb.png', R: 'wr.png', Q: 'wq.png', K: 'wk.png',
  p: 'bp.png', n: 'bn.png', b: 'bb.png', r: 'br.png', q: 'bq.png', k: 'bk.png'
};

const TRAINING_PIECE_LABELS = {
  P: 'peon blanco', N: 'caballo blanco', B: 'alfil blanco', R: 'torre blanca', Q: 'dama blanca', K: 'rey blanco',
  p: 'peon negro', n: 'caballo negro', b: 'alfil negro', r: 'torre negra', q: 'dama negra', k: 'rey negro'
};

function selectedTrainingFilters() {
  return {
    type: document.getElementById('trainingTypeFilter')?.value || 'recommended',
    status: document.getElementById('trainingStatusFilter')?.value || 'pending',
  };
}

function trainingQueryString(page = currentTrainingPage) {
  const filters = selectedTrainingFilters();
  const params = new URLSearchParams({
    action: 'list',
    page: String(Math.max(1, Number(page) || 1)),
    per_page: String(trainingPagination.per_page || 20),
    type: filters.type,
    status: filters.status,
  });
  return params.toString();
}

async function loadTraining(page = currentTrainingPage) {
  currentTrainingPage = Math.max(1, Number(page) || 1);
  const response = await fetch(`api/training.php?${trainingQueryString(currentTrainingPage)}`, { cache: 'no-store' });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudieron cargar los ejercicios.');
  trainingExercises = data.exercises || [];
  trainingTypes = data.types || {};
  trainingTypeCounts = data.type_counts || {};
  trainingStats = data.stats || {};
  activeTrainingSession = data.session || null;
  trainingPagination = data.pagination || trainingPagination;
  currentTrainingPage = trainingPagination.page || currentTrainingPage;
  renderTrainingTypeOptions();
  renderTrainingStats();
  renderTrainingSession();
  renderTrainingExercises();
  renderTrainingPagination();
  renderTrainingStatus();
}

function renderTrainingTypeOptions() {
  const select = document.getElementById('trainingTypeFilter');
  if (!select || select.dataset.loaded === '1') return;
  const current = select.value || 'recommended';
  const ordered = ['recommended', 'find_best_move', 'avoid_blunder', 'find_mate', 'spot_threat', 'find_tactic', 'defend_position', 'convert_advantage', 'other'];
  select.innerHTML = ordered
    .filter(type => trainingTypes[type])
    .map(type => {
      const count = trainingTypeCounts[type] ? Number(trainingTypeCounts[type].pending || 0) : 0;
      const suffix = type === 'recommended' ? '' : ` (${count})`;
      return `<option value="${escapeAttr(type)}">${escapeHtml(trainingTypes[type].label || type)}${suffix}</option>`;
    })
    .join('');
  select.value = trainingTypes[current] ? current : 'recommended';
  select.dataset.loaded = '1';
}

function renderTrainingStats() {
  const el = document.getElementById('trainingStats');
  if (!el) return;
  const attempts = trainingStats.attempts || {};
  const totalAttempts = Number(attempts.total || 0);
  const solvedAttempts = Number(attempts.solved || 0);
  const solveRate = totalAttempts ? Math.round((solvedAttempts / totalAttempts) * 100) : 0;
  const avgSeconds = attempts.avg_duration_ms ? Math.round(Number(attempts.avg_duration_ms) / 1000) : null;
  const cards = [
    ['pulse', 'Pendientes', Number(trainingStats.pending || 0), 'por resolver'],
    ['target', 'Resueltos', Number(trainingStats.resolved || 0), 'completados'],
    ['star', 'Acierto', totalAttempts ? `${solveRate}%` : '--', totalAttempts ? `${solvedAttempts}/${totalAttempts} intentos` : 'sin intentos'],
    ['clock', 'Tiempo medio', avgSeconds === null ? '--' : `${avgSeconds}s`, 'por intento'],
  ];
  el.innerHTML = cards.map(card => `<article class="metric-card ${card[0]}"><div class="metric-icon">${trainingIconFor(card[0])}</div><div><span>${escapeHtml(card[1])}</span><b>${escapeHtml(card[2])}</b><small>${escapeHtml(card[3])}</small></div></article>`).join('');
}

function renderTrainingSession() {
  const summary = document.getElementById('trainingSessionSummary');
  const kpis = document.getElementById('trainingSessionKpis');
  if (!summary || !kpis) return;

  if (!activeTrainingSession) {
    summary.innerHTML = '<span>Preparando sesión...</span><strong>Tu entrenamiento quedará medido automáticamente.</strong>';
    kpis.innerHTML = '';
    return;
  }

  const started = activeTrainingSession.started_at ? activeTrainingSession.started_at.toString().slice(0, 16) : '';
  summary.innerHTML = `<span>Sesión activa${started ? ` · ${escapeHtml(started)}` : ''}</span><strong>${escapeHtml(trainingSessionTypeLabel(activeTrainingSession.selected_type))}</strong>`;
  const attempts = Number(activeTrainingSession.total_attempts || 0);
  const solved = Number(activeTrainingSession.solved_count || 0);
  const exerciseCount = Number(activeTrainingSession.exercise_count || 0);
  const avgSeconds = activeTrainingSession.avg_time_ms === null || typeof activeTrainingSession.avg_time_ms === 'undefined'
    ? '--'
    : `${Math.round(Number(activeTrainingSession.avg_time_ms) / 1000)}s`;
  kpis.innerHTML = [
    ['Ejercicios', exerciseCount],
    ['Resueltos', solved],
    ['Fallados/saltados', `${Number(activeTrainingSession.failed_count || 0)}/${Number(activeTrainingSession.skipped_count || 0)}`],
    ['Intentos', attempts],
    ['Tiempo medio', avgSeconds],
  ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><b>${escapeHtml(value)}</b></div>`).join('');
}

function trainingSessionTypeLabel(type) {
  return trainingTypes[type] ? trainingTypes[type].label : 'Recomendado para mí';
}

function trainingIconFor(kind) {
  return kind === 'pulse' ? '◎' : kind === 'target' ? '●' : kind === 'star' ? '★' : '▷';
}

function renderTrainingExercises() {
  const el = document.getElementById('trainingExerciseList');
  if (!el) return;
  if (!trainingExercises.length) {
    const hasAnyExercises = Number(trainingStats.total || 0) > 0;
    el.innerHTML = `
      <div class="empty-state">
        <strong>${hasAnyExercises ? 'No hay ejercicios con estos filtros.' : 'Todavía no hay ejercicios.'}</strong>
        <span>${hasAnyExercises ? 'Prueba otro tipo de ejercicio o cambia el estado del filtro.' : 'Genera ejercicios desde Ajustes / Mi Perfil o analiza nuevas partidas para alimentar el Training Center.'}</span>
        ${hasAnyExercises ? '<button class="secondary small" type="button" onclick="clearTrainingFilters()">Limpiar filtros</button>' : '<a href="profile.php">Ir a procesos batch</a>'}
      </div>
    `;
    return;
  }
  el.innerHTML = trainingExercises.map(trainingExerciseCard).join('');
}

function trainingExerciseCard(item) {
  const source = item.source_side === 'opponent' ? 'Rival' : 'Propia';
  const status = item.resolved_at ? 'Resuelto' : 'Pendiente';
  const moveNo = Math.floor((Number(item.ply || 1) - 1) / 2) + 1;
  const side = Number(item.ply || 1) % 2 === 1 ? 'blancas' : 'negras';
  const gameTitle = `${item.white_player || 'Blancas'} vs ${item.black_player || 'Negras'}`;
  const date = item.played_at || '';
  const primaryAction = item.resolved_at
    ? `<a class="btn secondary small" href="${escapeAttr(item.review_url || '#')}">Ver partida</a>`
    : `<button class="secondary small" type="button" onclick="openTrainingExercise(${Number(item.id || 0)})">Entrenar</button>`;
  return `
    <article class="training-card">
      <div class="training-card-main">
        <div class="training-card-title">
          <span class="queue-status ${item.resolved_at ? 'done' : 'queued'}">${escapeHtml(status)}</span>
          <h3>${escapeHtml(item.type_label || item.exercise_type || 'Ejercicio')}</h3>
        </div>
        <p>${escapeHtml(item.prompt || 'Encuentra la mejor jugada.')}</p>
        <div class="training-meta">
          <span>${escapeHtml(source)}</span>
          <span>Movimiento ${moveNo} · ${escapeHtml(side)}</span>
          <span>${escapeHtml(item.difficulty || 'medium')}</span>
          <span>Prioridad ${Number(item.priority_score || 0)}</span>
        </div>
        ${trainingTags(item)}
      </div>
      <div class="training-card-side">
        <strong>${escapeHtml(gameTitle)}</strong>
        <small>${escapeHtml(date || item.result_raw || '')}</small>
        <small>Intentos: ${Number(item.attempt_count || 0)}</small>
        ${primaryAction}
      </div>
    </article>
  `;
}

function trainingTags(item) {
  const tags = (item.smart_tags || []).slice(0, 5);
  if (!tags.length) return '';
  return `<div class="smart-tag-list training-tags">${tags.map(smartTagChip).join('')}</div>`;
}

function renderTrainingPagination() {
  const el = document.getElementById('trainingPagination');
  const info = document.getElementById('trainingPageInfo');
  if (!el) return;
  const total = trainingPagination.total || 0;
  const pages = trainingPagination.pages || 1;
  const page = trainingPagination.page || 1;
  const perPage = trainingPagination.per_page || 20;
  const start = total ? ((page - 1) * perPage) + 1 : 0;
  const end = Math.min(total, page * perPage);
  if (info) info.textContent = total ? `${start}-${end} de ${total}` : '0 ejercicios';
  if (pages <= 1) {
    el.innerHTML = '';
    return;
  }
  el.innerHTML = `<button class="secondary small" ${page <= 1 ? 'disabled' : ''} onclick="goTrainingPage(${page - 1})">Anterior</button><span class="page-current">Página ${page} de ${pages}</span><button class="secondary small" ${page >= pages ? 'disabled' : ''} onclick="goTrainingPage(${page + 1})">Siguiente</button>`;
}

function renderTrainingStatus() {
  const el = document.getElementById('trainingFilterStatus');
  if (!el) return;
  const filters = selectedTrainingFilters();
  const typeLabel = trainingTypes[filters.type] ? trainingTypes[filters.type].label : 'Recomendado para mí';
  const statusLabel = filters.status === 'resolved' ? 'resueltos' : filters.status === 'all' ? 'todos' : 'pendientes';
  el.textContent = `${typeLabel} · ${statusLabel}`;
}

function goTrainingPage(page) {
  if (page < 1 || page > (trainingPagination.pages || 1) || page === currentTrainingPage) return;
  loadTraining(page).catch(showTrainingError);
}

function clearTrainingFilters() {
  const type = document.getElementById('trainingTypeFilter');
  const status = document.getElementById('trainingStatusFilter');
  if (type) type.value = 'recommended';
  if (status) status.value = 'pending';
  loadTraining(1).catch(showTrainingError);
}

async function openTrainingExercise(id) {
  await ensureTrainingSession();
  const response = await fetch(`api/training.php?action=get&id=${Number(id)}`, { cache: 'no-store' });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudo cargar el ejercicio.');
  activeExercise = data.exercise;
  attemptedTrainingMoves = [];
  selectedTrainingSquare = '';
  revealedTrainingSolution = '';
  trainingHintFrom = '';
  trainingUsedHint = false;
  trainingStartedAt = Date.now();
  startTrainingExerciseTimer();
  trainingBoardOrientation = trainingFenSideToMove(activeExercise.fen) === 'b' ? 'black' : 'white';
  renderTrainingSolver();
  const panel = document.getElementById('trainingSolverPanel');
  if (panel) {
    panel.hidden = false;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

async function startTrainingSession() {
  const filters = selectedTrainingFilters();
  const response = await fetch('api/training.php?action=session_start', {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ type: filters.type || 'recommended' })
  });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudo iniciar la sesión.');
  activeTrainingSession = data.session || null;
  renderTrainingSession();
  return activeTrainingSession;
}

async function newTrainingSession() {
  const button = document.getElementById('trainingNewSessionBtn');
  if (button) button.disabled = true;
  try {
    const filters = selectedTrainingFilters();
    const response = await fetch('api/training.php?action=session_start', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ type: filters.type || 'recommended', force_new: true })
    });
    const data = await response.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo crear una nueva sesión.');
    activeTrainingSession = data.session || null;
    closeTrainingSolver();
    await loadTraining(currentTrainingPage);
  } finally {
    if (button) button.disabled = false;
  }
}

async function ensureTrainingSession() {
  if (activeTrainingSession) return activeTrainingSession;
  return startTrainingSession();
}

async function endTrainingSession(status = 'completed') {
  if (!activeTrainingSession) return;
  const response = await fetch('api/training.php?action=session_end', {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ session_id: activeTrainingSession.id, status })
  });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudo cerrar la sesión.');
  activeTrainingSession = null;
  closeTrainingSolver();
  await loadTraining(currentTrainingPage);
}

function closeTrainingSolver() {
  const panel = document.getElementById('trainingSolverPanel');
  if (panel) panel.hidden = true;
  stopTrainingExerciseTimer();
  activeExercise = null;
  attemptedTrainingMoves = [];
  selectedTrainingSquare = '';
  trainingHintFrom = '';
  revealedTrainingSolution = '';
}

function renderTrainingSolver() {
  if (!activeExercise) return;
  const title = document.getElementById('trainingSolverTitle');
  const prompt = document.getElementById('trainingSolverPrompt');
  const meta = document.getElementById('trainingSolverMeta');
  const tags = document.getElementById('trainingSolverTags');
  const review = document.getElementById('trainingReviewLink');
  const feedback = document.getElementById('trainingFeedback');
  if (title) title.textContent = activeExercise.type_label || 'Resolver ejercicio';
  if (prompt) prompt.innerHTML = trainingPromptHtml(activeExercise.prompt || 'Encuentra la mejor jugada.');
  if (meta) {
    const source = activeExercise.source_side === 'opponent' ? 'Rival' : 'Propia';
    const previousMove = activeExercise.previous_san || activeExercise.previous_uci || '';
    meta.innerHTML = `
      <span>${escapeHtml(source)}</span>
      <span>${escapeHtml(activeExercise.difficulty || 'medium')}</span>
      <span>Intentos ${attemptedTrainingMoves.length}/5</span>
      ${previousMove ? `<span>Última jugada: ${escapeHtml(previousMove)}</span>` : ''}
    `;
  }
  if (tags) tags.innerHTML = (activeExercise.smart_tags || []).slice(0, 5).map(smartTagChip).join('');
  if (review) review.href = activeExercise.review_url || '#';
  if (feedback) {
    feedback.textContent = '';
    feedback.className = 'training-feedback';
  }
  updateTrainingExerciseTimer();
  renderTrainingAttempts();
  renderTrainingBoard();
  updateTrainingDraft();
  renderTrainingControls();
}

function startTrainingExerciseTimer() {
  stopTrainingExerciseTimer();
  updateTrainingExerciseTimer();
  trainingTimerInterval = window.setInterval(updateTrainingExerciseTimer, 1000);
}

function stopTrainingExerciseTimer() {
  if (trainingTimerInterval) {
    window.clearInterval(trainingTimerInterval);
    trainingTimerInterval = null;
  }
}

function updateTrainingExerciseTimer() {
  const el = document.getElementById('trainingExerciseTimer');
  if (!el) return;
  const elapsedSeconds = trainingStartedAt ? Math.max(0, Math.floor((Date.now() - trainingStartedAt) / 1000)) : 0;
  const minutes = Math.floor(elapsedSeconds / 60);
  const seconds = elapsedSeconds % 60;
  el.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function trainingPromptHtml(text) {
  const value = (text || '').toString();
  const match = value.match(/^(.*?)(Juegan\s+(blancas|negras)\.?)/i);
  if (!match) return escapeHtml(value);
  const intro = match[1].trim();
  const turnText = match[2].replace(/\.$/, '');
  const side = match[3].toLowerCase() === 'negras' ? 'black' : 'white';
  const pieceFile = side === 'black' ? 'bp.png' : 'wp.png';
  const pieceAlt = side === 'black' ? 'peón negro' : 'peón blanco';
  return `${intro ? `${escapeHtml(intro)} ` : ''}<strong class="training-side-to-move"><img src="${TRAINING_PIECE_ASSET_PATH}${pieceFile}" alt="${escapeAttr(pieceAlt)}" draggable="false">${escapeHtml(turnText)}.</strong>`;
}

function renderTrainingBoard() {
  const board = document.getElementById('trainingBoard');
  if (!board || !activeExercise) return;
  const [placement] = (activeExercise.fen || '').split(' ');
  const grid = trainingBoardGridFromPlacement(placement || '');
  const displayGrid = trainingPreviewGrid(grid);
  const legalTargets = trainingLegalTargetSet(grid);
  const previousMove = (activeExercise.previous_uci || '').toString().toLowerCase();
  const previousFrom = previousMove.length >= 4 ? previousMove.slice(0, 2) : '';
  const previousTo = previousMove.length >= 4 ? previousMove.slice(2, 4) : '';
  const solutionMove = (revealedTrainingSolution || '').toString().toLowerCase();
  const solutionFrom = solutionMove.length >= 4 ? solutionMove.slice(0, 2) : '';
  const solutionTo = solutionMove.length >= 4 ? solutionMove.slice(2, 4) : '';
  let html = '';
  const ranks = trainingBoardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const files = trainingBoardOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  for (const r of ranks) {
    for (const file of files) {
      const sq = String.fromCharCode(97 + file) + (8 - r);
      const dark = (r + file) % 2 === 1;
      const selectedFrom = selectedTrainingSquare.slice(0, 2);
      const selectedTo = selectedTrainingSquare.slice(2, 4);
      const selected = sq === selectedFrom || sq === selectedTo ? ' selected' : '';
      const previous = sq === previousFrom ? ' from' : sq === previousTo ? ' to' : '';
      const solution = sq === solutionFrom || sq === solutionTo ? ' solution' : '';
      const legal = legalTargets.has(sq) ? ' legal-target' : '';
      const hint = sq === trainingHintFrom ? ' hint' : '';
      html += `<button class="sq ${dark ? 'dark' : 'light'}${previous}${selected}${solution}${legal}${hint}" type="button" data-sq="${sq}" onclick="selectTrainingSquare('${sq}')">${trainingPieceImageHtml(displayGrid[r][file] || '')}</button>`;
    }
  }
  board.innerHTML = html;
  board.dataset.orientation = trainingBoardOrientation;
}

function trainingPreviewGrid(grid) {
  const preview = grid.map(row => row.slice());
  if (!selectedTrainingSquare || selectedTrainingSquare.length < 4) return preview;
  const from = selectedTrainingSquare.slice(0, 2);
  const to = selectedTrainingSquare.slice(2, 4);
  const fromCoords = trainingSquareToGrid(from);
  const toCoords = trainingSquareToGrid(to);
  if (!fromCoords || !toCoords) return preview;
  const piece = preview[fromCoords.row][fromCoords.file];
  if (!piece) return preview;
  preview[fromCoords.row][fromCoords.file] = '';
  preview[toCoords.row][toCoords.file] = piece;
  return preview;
}

function trainingSquareToGrid(square) {
  if (!square || square.length < 2) return null;
  const file = square.charCodeAt(0) - 97;
  const rank = Number(square[1]);
  const row = 8 - rank;
  if (row < 0 || row > 7 || file < 0 || file > 7) return null;
  return { row, file };
}

function trainingBoardGridFromPlacement(placement) {
  const rows = placement.split('/');
  const grid = Array.from({ length: 8 }, () => Array(8).fill(''));
  for (let r = 0; r < 8; r++) {
    let file = 0;
    for (const ch of rows[r] || '') {
      if (/\d/.test(ch)) file += Number(ch);
      else if (file < 8) grid[r][file++] = ch;
    }
  }
  return grid;
}

function trainingLegalTargetSet(grid) {
  const targets = new Set();
  if (!activeExercise || selectedTrainingSquare.length !== 2) return targets;
  if (attemptedTrainingMoves.length >= 5 || activeExercise.resolved_at) return targets;

  const state = trainingFenState(activeExercise.fen);
  const from = trainingSquareToGrid(selectedTrainingSquare);
  if (!from) return targets;
  const piece = grid[from.row][from.file] || '';
  if (!piece || trainingPieceColor(piece) !== state.turn) return targets;

  trainingLegalMovesForState({ ...state, grid }, selectedTrainingSquare).forEach(move => targets.add(move.to));
  return targets;
}

function trainingFenState(fen) {
  const parts = (fen || '').trim().split(/\s+/);
  return {
    placement: parts[0] || '',
    turn: parts[1] === 'b' ? 'b' : 'w',
    castling: parts[2] || '-',
    ep: parts[3] || '-',
  };
}

function trainingLegalMovesForState(state, onlyFrom = '') {
  const pseudoMoves = trainingPseudoMovesForState(state, onlyFrom);
  return pseudoMoves.filter(move => {
    const next = trainingApplyMove(state, move);
    return !trainingKingInCheck(next, state.turn);
  });
}

function trainingPseudoMovesForState(state, onlyFrom = '') {
  const moves = [];
  for (let row = 0; row < 8; row++) {
    for (let file = 0; file < 8; file++) {
      const piece = state.grid[row][file] || '';
      if (!piece || trainingPieceColor(piece) !== state.turn) continue;
      const from = trainingGridToSquare(row, file);
      if (onlyFrom && from !== onlyFrom) continue;
      trainingPiecePseudoMoves(state, row, file, piece).forEach(move => moves.push(move));
    }
  }
  return moves;
}

function trainingPiecePseudoMoves(state, row, file, piece) {
  const moves = [];
  const color = trainingPieceColor(piece);
  const kind = piece.toLowerCase();
  const from = trainingGridToSquare(row, file);
  const add = (toRow, toFile, extra = {}) => {
    if (!trainingInBounds(toRow, toFile)) return;
    const target = state.grid[toRow][toFile] || '';
    if (target && trainingPieceColor(target) === color) return;
    moves.push({ from, to: trainingGridToSquare(toRow, toFile), piece, ...extra });
  };

  if (kind === 'p') {
    const dir = color === 'w' ? -1 : 1;
    const startRow = color === 'w' ? 6 : 1;
    const promotionRow = color === 'w' ? 0 : 7;
    const forwardRow = row + dir;
    if (trainingInBounds(forwardRow, file) && !state.grid[forwardRow][file]) {
      add(forwardRow, file, forwardRow === promotionRow ? { promotion: color === 'w' ? 'Q' : 'q' } : {});
      const doubleRow = row + dir * 2;
      if (row === startRow && trainingInBounds(doubleRow, file) && !state.grid[doubleRow][file]) {
        add(doubleRow, file);
      }
    }
    [-1, 1].forEach(fileOffset => {
      const toRow = row + dir;
      const toFile = file + fileOffset;
      if (!trainingInBounds(toRow, toFile)) return;
      const target = state.grid[toRow][toFile] || '';
      const to = trainingGridToSquare(toRow, toFile);
      const isEp = state.ep !== '-' && to === state.ep;
      if ((target && trainingPieceColor(target) !== color) || isEp) {
        add(toRow, toFile, {
          ...(toRow === promotionRow ? { promotion: color === 'w' ? 'Q' : 'q' } : {}),
          ...(isEp ? { ep: true } : {}),
        });
      }
    });
    return moves;
  }

  if (kind === 'n') {
    [[1,2],[2,1],[-1,2],[-2,1],[1,-2],[2,-1],[-1,-2],[-2,-1]].forEach(([dr, df]) => add(row + dr, file + df));
    return moves;
  }

  if (['b', 'r', 'q'].includes(kind)) {
    const dirs = [];
    if (kind !== 'r') dirs.push([1,1], [1,-1], [-1,1], [-1,-1]);
    if (kind !== 'b') dirs.push([1,0], [-1,0], [0,1], [0,-1]);
    dirs.forEach(([dr, df]) => {
      let toRow = row + dr;
      let toFile = file + df;
      while (trainingInBounds(toRow, toFile)) {
        const target = state.grid[toRow][toFile] || '';
        if (!target) {
          add(toRow, toFile);
        } else {
          if (trainingPieceColor(target) !== color) add(toRow, toFile);
          break;
        }
        toRow += dr;
        toFile += df;
      }
    });
    return moves;
  }

  if (kind === 'k') {
    for (let dr = -1; dr <= 1; dr++) {
      for (let df = -1; df <= 1; df++) {
        if (dr || df) add(row + dr, file + df);
      }
    }
    if (!trainingKingInCheck(state, color)) {
      if (color === 'w' && row === 7 && file === 4) {
        if (state.castling.includes('K') && state.grid[7][7] === 'R' && !state.grid[7][5] && !state.grid[7][6] && !trainingSquareAttacked(state, 7, 5, 'b') && !trainingSquareAttacked(state, 7, 6, 'b')) add(7, 6, { castle: true });
        if (state.castling.includes('Q') && state.grid[7][0] === 'R' && !state.grid[7][1] && !state.grid[7][2] && !state.grid[7][3] && !trainingSquareAttacked(state, 7, 3, 'b') && !trainingSquareAttacked(state, 7, 2, 'b')) add(7, 2, { castle: true });
      }
      if (color === 'b' && row === 0 && file === 4) {
        if (state.castling.includes('k') && state.grid[0][7] === 'r' && !state.grid[0][5] && !state.grid[0][6] && !trainingSquareAttacked(state, 0, 5, 'w') && !trainingSquareAttacked(state, 0, 6, 'w')) add(0, 6, { castle: true });
        if (state.castling.includes('q') && state.grid[0][0] === 'r' && !state.grid[0][1] && !state.grid[0][2] && !state.grid[0][3] && !trainingSquareAttacked(state, 0, 3, 'w') && !trainingSquareAttacked(state, 0, 2, 'w')) add(0, 2, { castle: true });
      }
    }
  }

  return moves;
}

function trainingApplyMove(state, move) {
  const grid = state.grid.map(row => row.slice());
  const from = trainingSquareToGrid(move.from);
  const to = trainingSquareToGrid(move.to);
  if (!from || !to) return { ...state, grid };
  const piece = grid[from.row][from.file] || move.piece;
  grid[from.row][from.file] = '';
  if (move.ep) grid[from.row][to.file] = '';
  if (move.castle) {
    if (to.file === 6) {
      grid[to.row][5] = grid[to.row][7];
      grid[to.row][7] = '';
    } else if (to.file === 2) {
      grid[to.row][3] = grid[to.row][0];
      grid[to.row][0] = '';
    }
  }
  grid[to.row][to.file] = move.promotion || piece;
  return { ...state, grid, turn: state.turn === 'w' ? 'b' : 'w', ep: '-' };
}

function trainingKingInCheck(state, color) {
  const king = trainingFindKing(state.grid, color);
  if (!king) return false;
  return trainingSquareAttacked(state, king.row, king.file, color === 'w' ? 'b' : 'w');
}

function trainingFindKing(grid, color) {
  const king = color === 'w' ? 'K' : 'k';
  for (let row = 0; row < 8; row++) {
    for (let file = 0; file < 8; file++) {
      if (grid[row][file] === king) return { row, file };
    }
  }
  return null;
}

function trainingSquareAttacked(state, row, file, byColor) {
  const grid = state.grid;
  const pawn = byColor === 'w' ? 'P' : 'p';
  const pawnDir = byColor === 'w' ? -1 : 1;
  for (const fileOffset of [-1, 1]) {
    const attackRow = row - pawnDir;
    const attackFile = file + fileOffset;
    if (trainingInBounds(attackRow, attackFile) && grid[attackRow][attackFile] === pawn) return true;
  }

  const knight = byColor === 'w' ? 'N' : 'n';
  for (const [dr, df] of [[1,2],[2,1],[-1,2],[-2,1],[1,-2],[2,-1],[-1,-2],[-2,-1]]) {
    const r = row + dr;
    const f = file + df;
    if (trainingInBounds(r, f) && grid[r][f] === knight) return true;
  }

  if (trainingRayAttacked(grid, row, file, byColor, [[1,1],[1,-1],[-1,1],[-1,-1]], ['b', 'q'])) return true;
  if (trainingRayAttacked(grid, row, file, byColor, [[1,0],[-1,0],[0,1],[0,-1]], ['r', 'q'])) return true;

  const king = byColor === 'w' ? 'K' : 'k';
  for (let dr = -1; dr <= 1; dr++) {
    for (let df = -1; df <= 1; df++) {
      if (!dr && !df) continue;
      const r = row + dr;
      const f = file + df;
      if (trainingInBounds(r, f) && grid[r][f] === king) return true;
    }
  }
  return false;
}

function trainingRayAttacked(grid, row, file, byColor, dirs, attackers) {
  const pieces = attackers.map(piece => byColor === 'w' ? piece.toUpperCase() : piece);
  for (const [dr, df] of dirs) {
    let r = row + dr;
    let f = file + df;
    while (trainingInBounds(r, f)) {
      const piece = grid[r][f] || '';
      if (piece) return pieces.includes(piece);
      r += dr;
      f += df;
    }
  }
  return false;
}

function trainingGridToSquare(row, file) {
  return String.fromCharCode(97 + file) + (8 - row);
}

function trainingInBounds(row, file) {
  return row >= 0 && row < 8 && file >= 0 && file < 8;
}

function trainingPieceColor(piece) {
  if (!piece) return '';
  return piece === piece.toUpperCase() ? 'w' : 'b';
}

function trainingPieceImageHtml(pieceCode) {
  const file = TRAINING_PIECE_IMAGES[pieceCode];
  if (!file) return '';
  const colorClass = pieceCode === pieceCode.toUpperCase() ? 'white-piece' : 'black-piece';
  return `<img class="board-piece ${colorClass}" src="${TRAINING_PIECE_ASSET_PATH}${file}" alt="${escapeAttr(TRAINING_PIECE_LABELS[pieceCode] || 'pieza')}" draggable="false">`;
}

function selectTrainingSquare(square) {
  if (!activeExercise || attemptedTrainingMoves.length >= 5 || activeExercise.resolved_at) return;
  if (!selectedTrainingSquare) {
    selectedTrainingSquare = square;
  } else if (selectedTrainingSquare === square) {
    selectedTrainingSquare = '';
  } else if (selectedTrainingSquare.length >= 4) {
    selectedTrainingSquare = square;
  } else {
    selectedTrainingSquare += square;
  }
  renderTrainingBoard();
  updateTrainingDraft();
}

function updateTrainingDraft() {
  const draft = document.getElementById('trainingMoveDraft');
  const submit = document.getElementById('trainingSubmitBtn');
  const complete = selectedTrainingSquare.length >= 4;
  const promotionWrap = document.getElementById('trainingPromotionWrap');
  const promotion = complete && trainingMoveNeedsPromotion(selectedTrainingSquare);
  if (promotionWrap) promotionWrap.hidden = !promotion;
  if (draft) {
    if (!selectedTrainingSquare) draft.textContent = 'Selecciona origen y destino en el tablero.';
    else if (!complete) draft.textContent = `Origen seleccionado: ${selectedTrainingSquare}. Ahora elige destino.`;
    else draft.textContent = `Jugada seleccionada: ${trainingSelectedMoveText()}.`;
  }
  if (submit) submit.disabled = !complete || trainingExerciseFinished();
  renderTrainingControls();
}

function trainingSelectedMoveText() {
  const base = `${selectedTrainingSquare.slice(0, 2)} → ${selectedTrainingSquare.slice(2, 4)}`;
  if (!trainingMoveNeedsPromotion(selectedTrainingSquare)) return base;
  const piece = document.getElementById('trainingPromotionPiece')?.value || 'q';
  return `${base}=${piece.toUpperCase()}`;
}

function trainingMoveNeedsPromotion(move) {
  if (!move || move.length < 4) return false;
  const from = move.slice(0, 2);
  const to = move.slice(2, 4);
  const piece = trainingPieceAtSquare(from);
  return piece && piece.toLowerCase() === 'p' && (to[1] === '1' || to[1] === '8');
}

function trainingPieceAtSquare(square) {
  if (!activeExercise || !square || square.length < 2) return '';
  const [placement] = (activeExercise.fen || '').split(' ');
  const grid = trainingBoardGridFromPlacement(placement || '');
  const coords = trainingSquareToGrid(square);
  if (!coords) return '';
  return grid[coords.row][coords.file] || '';
}

function clearTrainingSelection() {
  selectedTrainingSquare = '';
  renderTrainingBoard();
  updateTrainingDraft();
}

function trainingExerciseFinished() {
  return !!(activeExercise && (activeExercise.resolved_at || revealedTrainingSolution || attemptedTrainingMoves.length >= 5));
}

function showTrainingHint() {
  if (!activeExercise || trainingExerciseFinished()) return;
  const from = (activeExercise.hint_from || '').toString().toLowerCase();
  if (from.length !== 2) return;
  trainingUsedHint = true;
  trainingHintFrom = from;
  selectedTrainingSquare = trainingHintFrom;
  renderTrainingBoard();
  updateTrainingDraft();
  renderTrainingControls();
}

function renderTrainingControls() {
  const active = document.getElementById('trainingActiveControls');
  const done = document.getElementById('trainingDoneControls');
  const hint = document.getElementById('trainingHintBtn');
  const finished = trainingExerciseFinished();
  if (active) active.hidden = finished;
  if (done) done.hidden = !finished;
  if (hint) hint.disabled = finished || trainingUsedHint || !activeExercise || !(activeExercise.hint_from || '').toString();
}

async function openNextTrainingExercise() {
  await loadTraining(currentTrainingPage);
  const currentId = activeExercise ? Number(activeExercise.id || 0) : 0;
  const next = trainingExercises.find(item => !item.resolved_at && Number(item.id || 0) !== currentId);
  if (!next) {
    closeTrainingSolver();
    return;
  }
  await openTrainingExercise(next.id);
}

async function submitTrainingMove() {
  if (!activeExercise || selectedTrainingSquare.length < 4 || attemptedTrainingMoves.length >= 5) return;
  let move = selectedTrainingSquare.toLowerCase();
  if (trainingMoveNeedsPromotion(move)) {
    move += document.getElementById('trainingPromotionPiece')?.value || 'q';
  }
  attemptedTrainingMoves.push(move);
  selectedTrainingSquare = '';
  const response = await fetch('api/training.php?action=attempt', {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({
      id: activeExercise.id,
      session_id: activeTrainingSession ? activeTrainingSession.id : 0,
      moves: attemptedTrainingMoves,
      duration_ms: Date.now() - trainingStartedAt,
      used_hint: trainingUsedHint
    })
  });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudo registrar el intento.');
  if (data.exercise) activeExercise = data.exercise;
  if (data.session) activeTrainingSession = data.session;
  showTrainingFeedback(data);
  if (data.solved || data.solution_uci) stopTrainingExerciseTimer();
  renderTrainingAttempts();
  renderTrainingBoard();
  updateTrainingDraft();
  renderTrainingStatsFromResponse(data);
  renderTrainingSession();
  if (data.solved || data.solution_uci) {
    await loadTraining(currentTrainingPage);
  }
}

function showTrainingFeedback(data) {
  const feedback = document.getElementById('trainingFeedback');
  if (!feedback) return;
  const remaining = Number(data.remaining_attempts || 0);
  feedback.textContent = data.feedback || (data.solved ? 'Correcto.' : 'Todavía no.');
  if (!data.solved && remaining > 0 && !data.solution_uci) {
    feedback.textContent += ` Te quedan ${remaining} intento(s).`;
  }
  feedback.className = `training-feedback ${data.solved ? 'ok' : 'warn'}`;
  if (!data.solved && data.solution_uci) {
    revealedTrainingSolution = data.solution_uci;
    feedback.textContent += ` Solución: ${data.solution_uci}.`;
  }
}

function renderTrainingAttempts() {
  const el = document.getElementById('trainingAttempts');
  if (!el) return;
  el.innerHTML = attemptedTrainingMoves.length
    ? attemptedTrainingMoves.map((move, index) => `<span>${index + 1}. ${escapeHtml(move)}</span>`).join('')
    : '<span>Sin intentos todavía.</span>';
}

function renderTrainingStatsFromResponse(data) {
  if (!data.stats) return;
  trainingStats = data.stats;
  if (data.session) activeTrainingSession = data.session;
  renderTrainingStats();
}

async function skipTrainingExercise() {
  if (activeExercise) {
    await ensureTrainingSession();
    const response = await fetch('api/training.php?action=skip', {
      method: 'POST',
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ id: activeExercise.id, session_id: activeTrainingSession.id })
    });
    const data = await response.json();
    if (data.ok && data.session) activeTrainingSession = data.session;
    if (data.stats) renderTrainingStatsFromResponse(data);
    renderTrainingSession();
    await loadTraining(currentTrainingPage);
  }
  closeTrainingSolver();
}

function flipTrainingBoard() {
  trainingBoardOrientation = trainingBoardOrientation === 'white' ? 'black' : 'white';
  renderTrainingBoard();
}

function trainingFenSideToMove(fen) {
  const parts = (fen || '').trim().split(/\s+/);
  return parts[1] === 'b' ? 'b' : 'w';
}

function bindTrainingFilters() {
  ['trainingTypeFilter', 'trainingStatusFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => loadTraining(1).catch(showTrainingError));
  });
  const promotion = document.getElementById('trainingPromotionPiece');
  if (promotion) promotion.addEventListener('change', updateTrainingDraft);
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

function showTrainingError(error) {
  const el = document.getElementById('trainingExerciseList');
  if (el) {
    el.innerHTML = `<div class="empty-state"><strong>No se pudo cargar Entrenamiento.</strong><span>${escapeHtml(error.message || 'Error inesperado.')}</span></div>`;
  }
}

function escapeHtml(value) {
  return (value === null || typeof value === 'undefined' ? '' : value).toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

bindTrainingFilters();
loadTraining(1).catch(showTrainingError);
