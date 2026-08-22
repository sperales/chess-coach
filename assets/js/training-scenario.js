const SCENARIO_ID = Number(window.CHESS_TRAINING_SCENARIO_ID || 0);
const SCENARIO_PLAN_ID = Number(window.CHESS_TRAINING_PLAN_ID || 0);
const SCENARIO_PIECE_PATH = String(window.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/');
const SCENARIO_PIECES = {
  P: 'wp.png', N: 'wn.png', B: 'wb.png', R: 'wr.png', Q: 'wq.png', K: 'wk.png',
  p: 'bp.png', n: 'bn.png', b: 'bb.png', r: 'br.png', q: 'bq.png', k: 'bk.png',
};

let scenario = null;
let scenarioRun = null;
let scenarioEvents = [];
let scenarioTraining = null;
let scenarioPlan = null;
let scenarioFen = '';
let scenarioOrientation = 'white';
let scenarioSelection = '';
let scenarioWrongDestination = '';
let scenarioHintOrigin = '';
let scenarioLastMove = '';
let scenarioFeedIndex = 0;
let scenarioRenderedFeedLength = 0;
let scenarioFeedAnimateAdvance = false;
let scenarioStartedAt = Date.now();
let scenarioBusy = false;
let scenarioTimerHandle = null;
let scenarioMoveSequence = 0;
let scenarioMoveController = null;

function scenarioEscape(value) {
  return (value == null ? '' : String(value)).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

async function scenarioJson(url, options = {}) {
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la operación.');
  return data;
}

function scenarioPost(action, body = {}, signal = null) {
  return scenarioJson(`api/training.php?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(body), signal,
  });
}

async function loadScenarioPlan() {
  if (SCENARIO_PLAN_ID <= 0) {
    scenarioTraining = null;
    scenarioPlan = null;
    return;
  }
  const data = await scenarioJson('api/training.php?action=list&type=recommended&status=pending&page=1&per_page=1', { cache: 'no-store' });
  scenarioTraining = data.session || null;
  scenarioPlan = data.coach_plan || null;
}

async function startScenario() {
  if (!SCENARIO_ID) throw new Error('Escenario no indicado.');
  await loadScenarioPlan();
  const data = await scenarioPost('scenario_start', {
    scenario_id: SCENARIO_ID,
    session_id: Number(scenarioTraining?.id || SCENARIO_PLAN_ID || 0),
  });
  scenario = data.scenario;
  scenarioRun = scenario.run;
  scenarioFen = scenarioRun.fen;
  scenarioOrientation = scenario.player_color === 'b' ? 'black' : 'white';
  scenarioStartedAt = Date.now() - Number(scenarioRun.duration_ms || 0);
  await refreshScenario();
  startScenarioTimer();
}

async function refreshScenario() {
  const runId = Number(scenarioRun?.id || 0);
  const query = new URLSearchParams({ action: 'scenario_get', id: String(SCENARIO_ID) });
  if (runId) query.set('run_id', String(runId));
  const data = await scenarioJson(`api/training.php?${query.toString()}`, { cache: 'no-store' });
  scenario = data.scenario;
  scenarioRun = scenario.run;
  scenarioFen = scenarioRun?.fen || scenarioFen;
  scenarioEvents = data.events || [];
  renderScenario();
}

function scenarioPlanItems() {
  return Array.isArray(scenarioPlan?.items) ? scenarioPlan.items : [];
}

function currentScenarioPlanItem() {
  return scenarioPlanItems().find(item => item.item_type === 'scenario' && Number(item.scenario_id) === SCENARIO_ID) || null;
}

function renderScenario() {
  if (!scenario || !scenarioRun) return;
  const side = scenario.player_color === 'b' ? 'negras' : 'blancas';
  const position = Number(currentScenarioPlanItem()?.position || 1);
  const total = Math.max(1, Number(scenarioPlan?.item_count || scenarioPlanItems().length || 1));
  const hasPlanContext = SCENARIO_PLAN_ID > 0 && !!currentScenarioPlanItem();
  const planHeader = document.getElementById('scenarioPlanHeader');
  const planSummary = document.getElementById('scenarioPlanSummary');
  if (planHeader) planHeader.hidden = !hasPlanContext;
  if (planSummary) planSummary.hidden = !hasPlanContext;
  setScenarioText('scenarioTitle', scenario.title);
  setScenarioText('scenarioPrompt', scenario.prompt.replace(/\s*Juegan\s+(blancas|negras)\.?/i, '').trim());
  setScenarioText('scenarioSide', `Juegan ${side}`);
  setScenarioText('scenarioDifficulty', scenarioDifficulty(scenario.difficulty));
  setScenarioText('scenarioPlanPosition', `Ejercicio ${position} de ${total}`);
  setScenarioText('scenarioPlanProgress', `${position} de ${total}`);
  setScenarioText('scenarioDecisionText', `${Number(scenarioRun.player_moves || 0)} de ${Number(scenario.target_player_moves || 0)} mínimas`);
  setScenarioText('scenarioAttempts', `${Number(scenarioRun.attempts || 0)} intento${Number(scenarioRun.attempts || 0) === 1 ? '' : 's'}`);
  setScenarioText('scenarioOrigin', `${scenario.source.white} vs ${scenario.source.black} · jugada ${Math.ceil(Number(scenario.source.ply || 1) / 2)}`);
  setScenarioText('scenarioDetailObjective', scenario.prompt);
  setScenarioText('scenarioDetailType', scenarioTypeLabel(scenario.type));
  setScenarioText('scenarioDetailDifficulty', scenarioDifficulty(scenario.difficulty));
  const origin = document.getElementById('scenarioOriginLink');
  if (origin) origin.href = `review.php?id=${Number(scenario.source.game_id || 0)}&ply=${Number(scenario.source.ply || 0)}`;
  const track = document.getElementById('scenarioPlanTrack');
  if (track) track.innerHTML = Array.from({ length: total }, (_, index) => {
    const item = scenarioPlanItems()[index] || null;
    const done = item && ['completed', 'failed', 'skipped'].includes(item.status);
    return `<i class="${done ? 'complete' : ''}${index === position - 1 ? ' current' : ''}"></i>`;
  }).join('');
  const bar = document.getElementById('scenarioPlanBar');
  if (bar) bar.style.width = `${Math.min(100, Math.round((position / total) * 100))}%`;
  renderScenarioBoard();
  renderScenarioFeed();
  renderScenarioControls();
}

function scenarioTypeLabel(type) {
  return { conversion: 'Conversión de ventaja', defense: 'Defensa', mate: 'Secuencia de mate' }[type] || 'Escenario';
}

function scenarioDifficulty(value) {
  return { easy: 'Básico', medium: 'Intermedio', hard: 'Avanzado', critical: 'Crítico' }[value] || 'Intermedio';
}

function scenarioGrid(fen) {
  const rows = String(fen || '').split(' ')[0].split('/');
  return rows.map(row => {
    const cells = [];
    for (const char of row) {
      if (/\d/.test(char)) cells.push(...Array(Number(char)).fill(''));
      else cells.push(char);
    }
    return cells;
  });
}

function scenarioSquareCoords(square) {
  if (!/^[a-h][1-8]$/.test(square)) return null;
  return { row: 8 - Number(square[1]), file: square.charCodeAt(0) - 97 };
}

function scenarioPieceAt(square) {
  const coords = scenarioSquareCoords(square);
  return coords ? scenarioGrid(scenarioFen)[coords.row]?.[coords.file] || '' : '';
}

function scenarioPieceColor(piece) {
  return piece && piece === piece.toUpperCase() ? 'w' : piece ? 'b' : '';
}

function scenarioPreviewGrid() {
  const grid = scenarioGrid(scenarioFen).map(row => row.slice());
  if (scenarioSelection.length < 4) return grid;
  const from = scenarioSquareCoords(scenarioSelection.slice(0, 2));
  const to = scenarioSquareCoords(scenarioSelection.slice(2, 4));
  if (from && to) {
    grid[to.row][to.file] = grid[from.row][from.file];
    grid[from.row][from.file] = '';
  }
  return grid;
}

function renderScenarioBoard() {
  const board = document.getElementById('scenarioBoard');
  if (!board || !scenarioFen) return;
  const grid = scenarioPreviewGrid();
  const ranks = scenarioOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const files = scenarioOrientation === 'black' ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const selectedFrom = scenarioSelection.slice(0, 2);
  const selectedTo = scenarioSelection.slice(2, 4);
  const lastFrom = scenarioLastMove.slice(0, 2);
  const lastTo = scenarioLastMove.slice(2, 4);
  let html = '';
  ranks.forEach((row, rankIndex) => files.forEach((file, fileIndex) => {
    const square = String.fromCharCode(97 + file) + (8 - row);
    const piece = grid[row]?.[file] || '';
    const classes = [((row + file) % 2 ? 'dark' : 'light')];
    if (square === selectedFrom || square === selectedTo) classes.push('selected');
    if (square === scenarioWrongDestination) classes.push('incorrect-destination');
    if (square === scenarioHintOrigin) classes.push('hint');
    if (square === lastFrom) classes.push('from');
    if (square === lastTo) classes.push('to');
    const fileLabel = rankIndex === 7 ? `<span class="scenario-file-label">${square[0].toUpperCase()}</span>` : '';
    const rankLabel = fileIndex === 0 ? `<span class="scenario-rank-label">${square[1]}</span>` : '';
    const image = piece ? `<img class="board-piece" src="${SCENARIO_PIECE_PATH}${SCENARIO_PIECES[piece]}" alt="pieza" draggable="false">` : '';
    html += `<button type="button" class="sq ${classes.join(' ')}" data-square="${square}">${image}${fileLabel}${rankLabel}</button>`;
  }));
  board.innerHTML = html;
  board.querySelectorAll('[data-square]').forEach(button => button.addEventListener('click', () => selectScenarioSquare(button.dataset.square)));
}

function selectScenarioSquare(square) {
  if (!scenarioRun || scenarioRun.status !== 'active' || scenarioBusy) return;
  const clicked = scenarioPieceAt(square);
  if (!scenarioSelection || scenarioSelection.length >= 4) {
    if (!clicked || scenarioPieceColor(clicked) !== scenario.player_color) {
      setScenarioDraft(`Selecciona una pieza ${scenario.player_color === 'b' ? 'negra' : 'blanca'}.`, true);
      return;
    }
    scenarioSelection = square;
    scenarioWrongDestination = '';
  } else if (scenarioSelection === square) {
    scenarioSelection = '';
  } else if (clicked && scenarioPieceColor(clicked) === scenario.player_color) {
    scenarioSelection = square;
  } else {
    scenarioSelection += square;
  }
  setScenarioDraft(scenarioSelection.length >= 4 ? `Jugada seleccionada: ${scenarioSelection.slice(0, 2)} → ${scenarioSelection.slice(2, 4)}.` : 'Ahora selecciona la casilla de destino.');
  renderScenarioBoard();
  renderScenarioControls();
  if (scenarioSelection.length >= 4) window.setTimeout(submitScenarioMove, 0);
}

function scenarioMoveUci() {
  let move = scenarioSelection.toLowerCase();
  const piece = scenarioPieceAt(move.slice(0, 2));
  if (piece?.toLowerCase() === 'p' && ['1', '8'].includes(move[3])) move += 'q';
  return move;
}

async function submitScenarioMove() {
  if (scenarioSelection.length < 4 || scenarioBusy) return;
  scenarioBusy = true;
  const sequence = ++scenarioMoveSequence;
  if (scenarioMoveController) scenarioMoveController.abort();
  scenarioMoveController = new AbortController();
  const move = scenarioMoveUci();
  try {
    const data = await scenarioPost('scenario_move', { run_id: scenarioRun.id, move_uci: move }, scenarioMoveController.signal);
    if (sequence !== scenarioMoveSequence) return;
    if (!data.accepted) {
      scenarioWrongDestination = move.slice(2, 4);
      scenarioSelection = '';
      setScenarioDraft(data.feedback || 'Prueba otra idea.', true);
    } else {
      scenarioLastMove = data.opponent_move?.uci || data.player_move?.uci || move;
      scenarioSelection = '';
      scenarioWrongDestination = '';
      if (data.player_fen && data.opponent_move) {
        scenarioFen = data.player_fen;
        renderScenarioBoard();
        await new Promise(resolve => setTimeout(resolve, 350));
      }
      scenario = data.scenario;
      scenarioRun = scenario.run;
      scenarioFen = scenarioRun.fen;
      setScenarioDraft(data.feedback || 'Continúa con el plan.');
    }
    if (scenarioRun.status !== 'active') await loadScenarioPlan();
    await refreshScenario();
    scenarioFeedIndex = Math.max(0, scenarioFeed().length - 1);
    renderScenarioFeed();
  } catch (error) {
    if (error?.name === 'AbortError') return;
    setScenarioDraft(error.message, true);
  } finally {
    if (sequence === scenarioMoveSequence) {
      scenarioBusy = false;
      renderScenarioControls();
    }
  }
}

async function requestScenarioHint() {
  if (scenarioBusy || scenarioRun.status !== 'active') return;
  scenarioBusy = true;
  try {
    const level = Math.min(3, Number(scenarioRun.highest_hint_level || 0) + 1);
    const data = await scenarioPost('scenario_hint', { run_id: scenarioRun.id, level });
    scenarioHintOrigin = data.origin_square || '';
    await refreshScenario();
    scenarioFeedIndex = Math.max(0, scenarioFeed().length - 1);
    renderScenarioFeed();
  } catch (error) {
    setScenarioDraft(error.message, true);
  } finally {
    scenarioBusy = false;
    renderScenarioControls();
  }
}

async function requestScenarioWhy() {
  if (scenarioBusy) return;
  scenarioBusy = true;
  try {
    await scenarioPost('scenario_why', { run_id: scenarioRun.id });
    await refreshScenario();
    scenarioFeedIndex = Math.max(0, scenarioFeed().length - 1);
    renderScenarioFeed();
  } catch (error) {
    setScenarioDraft(error.message, true);
  } finally {
    scenarioBusy = false;
    renderScenarioControls();
  }
}

async function skipScenario() {
  if (scenarioBusy || scenarioRun.status !== 'active') return;
  scenarioBusy = true;
  try {
    await scenarioPost('scenario_skip', { run_id: scenarioRun.id });
    await loadScenarioPlan();
    await goToNextScenarioPlanItem();
  } catch (error) {
    setScenarioDraft(error.message, true);
    scenarioBusy = false;
  }
}

function scenarioFeed() {
  const feed = [{ title: 'Objetivo', text: scenario.prompt, state: 'neutral' }];
  scenarioEvents.forEach(event => {
    if (event.event_type === 'start') return;
    if (event.event_type === 'move' && event.actor === 'opponent') {
      feed.push({ title: 'Respuesta rival', text: `Stockfish responde ${event.move_san || event.move_uci}. Te toca decidir de nuevo.`, state: 'neutral' });
      return;
    }
    if (event.event_type === 'move') feed.push({ title: event.move_san || 'Buena decisión', text: event.feedback_text || 'La jugada mantiene el objetivo.', state: event.decision_bucket === 'optimal' ? 'success' : 'neutral' });
    if (event.event_type === 'retry') feed.push({ title: event.move_san || 'Inténtalo de nuevo', text: event.feedback_text || 'La posición se mantiene para que busques otra idea.', state: 'error' });
    if (event.event_type === 'hint') feed.push({ title: `Pista ${Number(event.metadata?.level || 1)}`, text: event.feedback_text || '', state: 'hint' });
    if (event.event_type === 'explanation') feed.push({ title: '¿Por qué?', text: event.feedback_text || '', state: 'explanation' });
    if (event.event_type === 'completion') feed.push({ title: event.accepted ? 'Escenario completado' : 'Escenario terminado', text: event.accepted ? 'Has demostrado la idea contra la mejor defensa.' : 'Has llegado al límite del escenario. Lo retomaremos más adelante.', state: event.accepted ? 'success' : 'error' });
  });
  return feed;
}

function renderScenarioFeed() {
  const feed = scenarioFeed();
  if (scenarioRenderedFeedLength > 0 && feed.length > scenarioRenderedFeedLength) {
    scenarioFeedIndex = feed.length - 1;
    scenarioFeedAnimateAdvance = true;
  }
  scenarioRenderedFeedLength = feed.length;
  scenarioFeedIndex = Math.max(0, Math.min(feed.length - 1, scenarioFeedIndex));
  const message = feed[scenarioFeedIndex];
  const card = document.getElementById('scenarioCoach');
  const avatar = document.getElementById('scenarioNova');
  if (card) card.className = `training-mobile-coach training-scenario-coach nova-state-${message.state}`;
  if (avatar) avatar.className = `nova-avatar ${message.state === 'success' ? 'nova-avatar--success' : message.state === 'error' ? 'nova-avatar--error' : message.state === 'hint' ? 'nova-avatar--warning' : message.state === 'explanation' ? 'nova-avatar--focus' : 'nova-avatar--neutral'}`;
  setScenarioText('scenarioCoachTitle', message.title);
  setScenarioText('scenarioCoachText', message.text);
  setScenarioText('scenarioCoachStep', `${scenarioFeedIndex + 1} de ${feed.length}`);
  const dots = document.getElementById('scenarioCoachDots');
  if (dots) dots.innerHTML = feed.map((_, index) => `<button type="button" class="${index === scenarioFeedIndex ? 'active' : ''}" data-index="${index}" aria-label="Mensaje ${index + 1} de ${feed.length}"></button>`).join('');
  dots?.querySelectorAll('[data-index]').forEach(button => button.addEventListener('click', () => { scenarioFeedIndex = Number(button.dataset.index); renderScenarioFeed(); }));
  if (card) {
    card.classList.remove('is-changing', 'is-advancing');
    void card.offsetWidth;
    card.classList.add(scenarioFeedAnimateAdvance ? 'is-advancing' : 'is-changing');
  }
  scenarioFeedAnimateAdvance = false;
}

function bindScenarioSwipe() {
  const card = document.getElementById('scenarioCoach');
  let startX = null;
  card?.addEventListener('touchstart', event => { startX = event.touches[0]?.clientX ?? null; }, { passive: true });
  card?.addEventListener('touchend', event => {
    if (startX == null) return;
    const delta = (event.changedTouches[0]?.clientX ?? startX) - startX;
    startX = null;
    if (Math.abs(delta) < 36) return;
    scenarioFeedIndex += delta < 0 ? 1 : -1;
    renderScenarioFeed();
  }, { passive: true });
}

function renderScenarioControls() {
  const finished = scenarioRun && scenarioRun.status !== 'active';
  const hint = document.getElementById('scenarioHint');
  if (hint) {
    const level = Number(scenarioRun?.highest_hint_level || 0);
    hint.disabled = scenarioBusy || finished || level >= 3;
    hint.textContent = level >= 3 ? 'Pistas completadas' : level ? 'Siguiente pista' : 'Ayuda';
  }
  const why = document.getElementById('scenarioWhy');
  if (why) why.disabled = scenarioBusy;
  const active = document.getElementById('scenarioActiveControls');
  const done = document.getElementById('scenarioDoneControls');
  if (active) active.hidden = finished;
  if (done) done.hidden = !finished;
  const skip = document.getElementById('scenarioSkip');
  if (skip) skip.disabled = scenarioBusy || finished;
  const next = document.getElementById('scenarioNext');
  if (next) {
    const current = currentScenarioPlanItem();
    const pending = scenarioPlanItems().find(item => item.status === 'pending' && Number(item.position) > Number(current?.position || 0))
      || scenarioPlanItems().find(item => item.status === 'pending' && item !== current);
    next.textContent = SCENARIO_PLAN_ID > 0 && !pending ? 'Finalizar entrenamiento' : 'Siguiente ejercicio';
  }
}

async function goToNextScenarioPlanItem() {
  await loadScenarioPlan();
  const items = scenarioPlanItems();
  const current = currentScenarioPlanItem();
  const pending = items.find(item => item.status === 'pending' && Number(item.position) > Number(current?.position || 0))
    || items.find(item => item.status === 'pending');
  if (!pending) {
    if (SCENARIO_PLAN_ID > 0) {
      await scenarioPost('session_end', { session_id: SCENARIO_PLAN_ID, status: 'completed' });
      window.location.href = `training.php?completed_training=${SCENARIO_PLAN_ID}`;
      return;
    }
    window.location.href = 'training.php';
    return;
  }
  const params = new URLSearchParams({ id: String(pending.item_type === 'scenario' ? pending.scenario_id : pending.exercise_id) });
  if (scenarioTraining?.id) params.set('training_id', String(scenarioTraining.id));
  window.location.href = `${pending.item_type === 'scenario' ? 'training-scenario.php' : 'training-exercise.php'}?${params.toString()}`;
}

function setScenarioDraft(text, error = false) {
  const draft = document.getElementById('scenarioDraft');
  if (draft) {
    draft.textContent = text;
    draft.classList.toggle('is-error', error);
  }
}

function setScenarioText(id, text) {
  const element = document.getElementById(id);
  if (element) element.textContent = text == null ? '' : String(text);
}

function startScenarioTimer() {
  clearInterval(scenarioTimerHandle);
  const update = () => {
    const seconds = Math.max(0, Math.floor((Date.now() - scenarioStartedAt) / 1000));
    setScenarioText('scenarioTimer', `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`);
  };
  update();
  scenarioTimerHandle = setInterval(update, 1000);
}

document.getElementById('scenarioHint')?.addEventListener('click', requestScenarioHint);
document.getElementById('scenarioWhy')?.addEventListener('click', requestScenarioWhy);
document.getElementById('scenarioSkip')?.addEventListener('click', skipScenario);
document.getElementById('scenarioNext')?.addEventListener('click', goToNextScenarioPlanItem);
document.getElementById('scenarioFlip')?.addEventListener('click', () => { scenarioOrientation = scenarioOrientation === 'white' ? 'black' : 'white'; renderScenarioBoard(); });
bindScenarioSwipe();
startScenario().catch(error => setScenarioDraft(error.message || 'No se pudo iniciar el escenario.', true));
window.addEventListener('pagehide', () => {
  scenarioMoveSequence++;
  scenarioMoveController?.abort();
});
