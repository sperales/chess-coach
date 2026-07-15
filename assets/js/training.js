let trainingExercises = [];
let trainingTypes = {};
let trainingTypeCounts = {};
let trainingStats = {};
let trainingExperience = {};
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
let completedTrainingMove = '';
let trainingOriginReviewLoadedFor = 0;
let trainingSelectionMessage = '';
let trainingMoveSubmitting = false;
const TRAINING_PIECE_ASSET_PATH = (window.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/').toString();
const TRAINING_PREFERENCES = window.CHESS_TRAINING_PREFERENCES || {};
const TRAINING_SHOW_LEGAL_MOVES = TRAINING_PREFERENCES.showLegalMoves !== false;
const TRAINING_AUTO_SUBMIT_MOVE = TRAINING_PREFERENCES.autoSubmitMove === true;
const TRAINING_INITIAL_PARAMS = new URLSearchParams(window.location.search);
const TRAINING_SOLVER_MODE = window.CHESS_TRAINING_SOLVER_MODE === true;
const TRAINING_INITIAL_EXERCISE_ID = Number(window.CHESS_TRAINING_INITIAL_EXERCISE_ID || TRAINING_INITIAL_PARAMS.get('id') || TRAINING_INITIAL_PARAMS.get('exercise_id') || 0);

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
  trainingExperience = data.experience || {};
  activeTrainingSession = data.session || null;
  trainingPagination = data.pagination || trainingPagination;
  currentTrainingPage = trainingPagination.page || currentTrainingPage;
  renderTrainingTypeOptions();
  renderTrainingStats();
  renderTrainingExperience();
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
  el.innerHTML = cards.map(card => `<article class="metric-card compact-metric-card ${card[0]}"><div class="metric-icon">${trainingIconFor(card[0])}</div><span>${escapeHtml(card[1])}</span><b>${escapeHtml(card[2])}</b><small>${escapeHtml(card[3])}</small></article>`).join('');
}

function renderTrainingExperience() {
  const panel = document.getElementById('trainingExperiencePanel');
  if (!panel) {
    renderTrainingSession();
    return;
  }

  let head = panel.querySelector('.panel-head');
  if (!head) {
    head = document.createElement('div');
    head.className = 'panel-head';
    head.innerHTML = '<h2>Progreso de entrenamiento</h2><div class="review-board-actions"></div>';
    panel.insertBefore(head, panel.firstChild);
  }
  const title = head.querySelector('h2');
  const actions = head.querySelector('.review-board-actions');
  const summary = document.getElementById('trainingExperienceSummary') || document.getElementById('trainingSessionSummary');
  const grid = document.getElementById('trainingExperienceGrid') || document.getElementById('trainingSessionKpis');
  const repeatList = document.getElementById('trainingRepeatList');
  const settings = trainingExperience.settings || {};
  const today = trainingExperience.today || {};
  const week = trainingExperience.week || {};
  const streak = trainingExperience.streak || {};
  const repeatQueue = trainingExperience.repeat_queue || {};
  const goalMode = settings.daily_goal_mode || 'exercises';
  const goalText = trainingDailyGoalText(settings);
  const todayProgress = trainingTodayProgressText(today, settings);
  const weeklyProgress = `${Number(week.training_days || 0)}/${Number(week.training_days_goal || settings.weekly_training_days_goal || 4)} días · ${Number(week.exercises || 0)}/${Number(week.exercise_goal || settings.weekly_exercise_goal || 25)} ejercicios`;
  const todayPercent = trainingProgressPercent(trainingTodayProgressBar(today, settings));
  const weeklyPercent = trainingProgressPercent(trainingWeeklyProgressBar(week, settings));
  const dueCount = Number(repeatQueue.due_count || 0);

  if (title) title.textContent = panel.classList.contains('training-solve-experience') ? 'Progreso de entrenamiento' : 'Entrenamiento de hoy';
  if (actions) actions.innerHTML = '<a class="btn secondary small" href="profile.php">Cambiar objetivo</a>';
  if (summary) {
    summary.className = 'training-experience-summary';
    summary.innerHTML = `<span>${today.trained ? 'Ya has entrenado hoy.' : 'Todavía no has entrenado hoy.'}</span><strong>${today.goal_met ? 'Objetivo diario cumplido.' : `Objetivo diario: ${escapeHtml(goalText)}.`}</strong>`;
  }
  if (grid) {
    grid.className = 'training-experience-grid';
    grid.innerHTML = [
      { kind: 'streak', label: 'Racha', value: `${Number(streak.days || 0)} día(s)`, detail: streak.today_goal_met ? 'objetivo cumplido hoy' : 'cumple el objetivo para mantenerla' },
      { kind: 'target', label: 'Hoy', value: todayProgress, detail: trainingGoalModeLabel(goalMode), percent: todayPercent, compact: true },
      { kind: 'calendar', label: 'Semana', value: weeklyProgress, detail: 'progreso semanal', percent: weeklyPercent, compact: true },
      { kind: 'repeat', label: 'Para repetir', value: dueCount, detail: dueCount === 1 ? 'ejercicio pendiente' : 'ejercicios pendientes' },
    ].map(({ kind, label, value, detail, percent = null, compact = false }) => `
      <article class="training-experience-card ${escapeAttr(kind)}${compact ? ' compact-progress' : ''}">
        <span>${trainingExperienceIcon(kind)}</span>
        <div>
          <small>${escapeHtml(label)}</small>
          ${percent === null ? '' : trainingInlineProgress(percent)}
          <strong>${escapeHtml(value)}</strong>
          <em>${escapeHtml(detail)}</em>
        </div>
      </article>
    `).join('');
  }
  if (repeatList) {
    const sample = repeatQueue.sample || [];
    repeatList.innerHTML = sample.length
      ? `<strong>Repeticiones recomendadas</strong><div>${sample.map(trainingRepeatItem).join('')}</div>`
      : '<strong>Repeticiones recomendadas</strong><p class="muted">No hay ejercicios vencidos para repetir ahora mismo.</p>';
  }
}

function trainingProgressPercent(percent) {
  return Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
}

function trainingInlineProgress(percent) {
  const value = trainingProgressPercent(percent);
  return `<div class="training-inline-progress" aria-label="Progreso ${value}%"><span style="width:${value}%"></span></div>`;
}

function trainingTodayProgressBar(today, settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  const exercises = Number(today.exercises || 0);
  const minutes = Number(today.duration_minutes || 0);
  const exerciseGoal = Math.max(1, Number(settings.daily_exercise_goal || 5));
  const minuteGoal = Math.max(1, Number(settings.daily_minutes_goal || 10));
  if (mode === 'minutes') return (minutes / minuteGoal) * 100;
  if (mode === 'both') return Math.min((exercises / exerciseGoal) * 100, (minutes / minuteGoal) * 100);
  return (exercises / exerciseGoal) * 100;
}

function trainingWeeklyProgressBar(week, settings) {
  const dayGoal = Math.max(1, Number(week.training_days_goal || settings.weekly_training_days_goal || 4));
  const exerciseGoal = Math.max(1, Number(week.exercise_goal || settings.weekly_exercise_goal || 25));
  const dayProgress = Number(week.training_days || 0) / dayGoal;
  const exerciseProgress = Number(week.exercises || 0) / exerciseGoal;
  return Math.max(dayProgress, exerciseProgress) * 100;
}

function trainingDailyGoalText(settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  const exercises = Number(settings.daily_exercise_goal || 5);
  const minutes = Number(settings.daily_minutes_goal || 10);
  if (mode === 'minutes') return `${minutes} minuto(s)`;
  if (mode === 'both') return `${exercises} ejercicio(s) y ${minutes} minuto(s)`;
  return `${exercises} ejercicio(s)`;
}

function trainingTodayProgressText(today, settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  const exercises = Number(today.exercises || 0);
  const minutes = Number(today.duration_minutes || 0);
  if (mode === 'minutes') return `${minutes}/${Number(settings.daily_minutes_goal || 10)} min`;
  if (mode === 'both') return `${exercises}/${Number(settings.daily_exercise_goal || 5)} ej · ${minutes}/${Number(settings.daily_minutes_goal || 10)} min`;
  return `${exercises}/${Number(settings.daily_exercise_goal || 5)} ejercicios`;
}

function trainingGoalModeLabel(mode) {
  if (mode === 'minutes') return 'objetivo por tiempo';
  if (mode === 'both') return 'objetivo combinado';
  return 'objetivo por ejercicios';
}

function trainingExperienceIcon(kind) {
  if (kind === 'streak') return '↗';
  if (kind === 'target') return '◎';
  if (kind === 'calendar') return '▦';
  return '↻';
}

function trainingRepeatItem(item) {
  return `
    <a class="training-repeat-item" href="training-exercise.php?id=${Number(item.id || 0)}">
      <span>${escapeHtml(item.type_label || 'Ejercicio')}</span>
      <small>${escapeHtml(item.reason_label || 'Pendiente de repetir')}</small>
    </a>
  `;
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
  el.innerHTML = trainingExercises.map((item, index) => trainingExerciseCard(item, currentTrainingPage === 1 && index === 0)).join('');
}

function trainingExerciseCard(item, featured = false) {
  const source = item.source_side === 'opponent' ? 'Rival' : 'Propia';
  const isRepeatDue = !!item.is_repeat_due;
  const isTrainable = trainingExerciseIsTrainable(item);
  const status = isRepeatDue ? 'Repetir' : item.resolved_at ? 'Resuelto' : 'Pendiente';
  const moveNo = Math.floor((Number(item.ply || 1) - 1) / 2) + 1;
  const side = Number(item.ply || 1) % 2 === 1 ? 'blancas' : 'negras';
  const gameTitle = `${item.white_player || 'Blancas'} vs ${item.black_player || 'Negras'}`;
  const date = item.played_at || '';
  const primaryAction = isTrainable
    ? `<a class="btn secondary small" href="training-exercise.php?id=${Number(item.id || 0)}">Entrenar</a>`
    : `<a class="btn secondary small" href="${escapeAttr(item.review_url || '#')}">Ver partida</a>`;
  const difficulty = item.difficulty || 'medium';
  const difficultyBlock = `
    <div class="training-card-difficulty">
      <small>Dificultad</small>
      <div class="training-difficulty-bars" aria-hidden="true">${trainingDifficultyBars(difficulty)}</div>
      <strong>${escapeHtml(trainingDifficultyLabel(difficulty))}</strong>
    </div>
  `;
  return `
    <article class="training-card${featured ? ' training-card-featured' : ''}">
      ${featured ? `<div class="training-card-preview"><span>Ejercicio destacado</span>${trainingExercisePreviewBoard(item)}</div>` : ''}
      <div class="training-card-main">
        <div class="training-card-title">
          <span class="queue-status ${isTrainable ? 'queued' : 'done'}">${escapeHtml(status)}</span>
          <h3>${escapeHtml(item.type_label || item.exercise_type || 'Ejercicio')}</h3>
        </div>
        <p>${escapeHtml(item.prompt || 'Encuentra la mejor jugada.')}</p>
        <div class="training-meta">
          <span>${escapeHtml(source)}</span>
          <span>Movimiento ${moveNo} · ${escapeHtml(side)}</span>
          <span>Prioridad ${Number(item.priority_score || 0)}</span>
          ${isRepeatDue && item.repetition_reason ? `<span>${escapeHtml(item.repetition_reason)}</span>` : ''}
        </div>
        ${trainingTags(item)}
      </div>
      ${featured ? '' : difficultyBlock}
      <div class="training-card-side">
        ${featured ? difficultyBlock : ''}
        <strong>${escapeHtml(gameTitle)}</strong>
        <small>${escapeHtml(date || item.result_raw || '')}</small>
        <small>Intentos: ${Number(item.attempt_count || 0)}</small>
        ${primaryAction}
      </div>
    </article>
  `;
}

function trainingExercisePreviewBoard(item) {
  const fen = (item && item.fen ? item.fen : '').toString();
  const [placement] = fen.split(' ');
  const grid = trainingBoardGridFromPlacement(placement || '');
  const blackOrientation = trainingFenSideToMove(fen) === 'b';
  const ranks = blackOrientation ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  const files = blackOrientation ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
  let squares = '';
  for (const row of ranks) {
    for (const file of files) {
      const dark = (row + file) % 2 === 1;
      squares += `<span class="sq ${dark ? 'dark' : 'light'}">${trainingPieceImageHtml(grid[row][file] || '')}</span>`;
    }
  }
  const side = blackOrientation ? 'negras' : 'blancas';
  return `<div class="training-card-board" role="img" aria-label="Vista previa de la posición. Juegan ${side}.">${squares}</div>`;
}

function trainingTags(item) {
  const tags = (item.smart_tags || []).slice(0, 5);
  if (!tags.length) return '';
  return `<div class="smart-tag-list training-tags">${tags.map(smartTagChip).join('')}</div>`;
}

function trainingExerciseIsTrainable(item) {
  return !!(item && (item.is_trainable || !item.resolved_at));
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
  const statusLabels = { pending: 'pendientes', failed: 'fallados', resolved: 'resueltos', all: 'todos' };
  const statusLabel = statusLabels[filters.status] || 'pendientes';
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
  trainingSelectionMessage = '';
  revealedTrainingSolution = '';
  completedTrainingMove = '';
  trainingHintFrom = '';
  trainingUsedHint = false;
  trainingStartedAt = Date.now();
  startTrainingExerciseTimer();
  trainingBoardOrientation = trainingFenSideToMove(activeExercise.fen) === 'b' ? 'black' : 'white';
  renderTrainingSolver();
  if (TRAINING_SOLVER_MODE && activeExercise && activeExercise.id) {
    const url = new URL(window.location.href);
    url.searchParams.set('id', activeExercise.id);
    window.history.replaceState({}, '', url.toString());
  }
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
  renderTrainingExperience();
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
  if (TRAINING_SOLVER_MODE) {
    window.location.href = 'training.php';
    return;
  }
  const panel = document.getElementById('trainingSolverPanel');
  if (panel) panel.hidden = true;
  stopTrainingExerciseTimer();
  activeExercise = null;
  attemptedTrainingMoves = [];
  selectedTrainingSquare = '';
  trainingSelectionMessage = '';
  trainingHintFrom = '';
  revealedTrainingSolution = '';
  completedTrainingMove = '';
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
  renderTrainingSolverChrome();
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
  loadTrainingOriginReview();
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

function renderTrainingSolverChrome() {
  if (!activeExercise) return;
  const typeLabel = activeExercise.type_label || activeExercise.exercise_type || 'Ejercicio';
  const promptText = activeExercise.prompt || 'Encuentra la mejor jugada.';
  const side = trainingFenSideToMove(activeExercise.fen) === 'b' ? 'negras' : 'blancas';
  const gameTitle = `${activeExercise.white_player || 'Blancas'} vs ${activeExercise.black_player || 'Negras'}`;
  setText('trainingSolverHeroTitle', typeLabel);
  setText('trainingSolverHeroPrompt', promptText.replace(/\s*Juegan\s+(blancas|negras)\.?/i, '').trim() || promptText);
  setText('trainingSolverHeroSide', `Juegan ${side}`);
  const sideObjective = document.getElementById('trainingSideObjective');
  if (sideObjective) sideObjective.innerHTML = trainingPromptHtml(promptText);
  const isRepeatDue = !!activeExercise.is_repeat_due;
  const isTrainable = trainingExerciseIsTrainable(activeExercise);
  setText('trainingSolverStatus', isRepeatDue ? 'Repetir' : activeExercise.resolved_at ? 'Resuelto' : 'Pendiente');
  setText('trainingPriorityValue', activeExercise.priority_score || 0);
  setText('trainingSourceGame', gameTitle);
  setText('trainingSourceDate', activeExercise.played_at || activeExercise.result_raw || '');
  setText('trainingSourceMove', trainingMoveLabel(activeExercise));
  setText('trainingDetailsObjective', promptText);
  setText('trainingDetailsTheme', trainingThemeForType(activeExercise.exercise_type || ''));
  setText('trainingDetailsLevel', trainingDifficultyLabel(activeExercise.difficulty || 'medium'));
  setText('trainingDetailsPriority', activeExercise.priority_score || 0);
  setText('trainingCorrectMove', revealedTrainingSolution || activeExercise.solution_uci || '-');

  const status = document.getElementById('trainingSolverStatus');
  if (status) status.className = `queue-status ${isTrainable ? 'queued' : 'done'}`;
  const difficulty = document.getElementById('trainingDifficultyBars');
  if (difficulty) difficulty.innerHTML = trainingDifficultyBars(activeExercise.difficulty || 'medium');
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value === null || typeof value === 'undefined' ? '' : value.toString();
}

function trainingMoveLabel(item) {
  const moveNo = Math.floor((Number(item.ply || 1) - 1) / 2) + 1;
  const level = trainingDifficultyLabel(item.difficulty || 'medium');
  return `${moveNo}. ${level}`;
}

function trainingDifficultyLabel(value) {
  const normalized = (value || '').toString().toLowerCase();
  if (normalized === 'easy') return 'Básico';
  if (normalized === 'hard') return 'Avanzado';
  if (normalized === 'critical') return 'Crítico';
  return 'Intermedio';
}

function trainingDifficultyBars(value) {
  const normalized = (value || '').toString().toLowerCase();
  const active = normalized === 'critical' ? 4 : normalized === 'hard' ? 3 : normalized === 'easy' ? 1 : 2;
  return Array.from({ length: 4 }, (_, index) => `<span class="${index < active ? 'active' : ''}"></span>`).join('');
}

function trainingThemeForType(type) {
  const themes = {
    find_best_move: 'Táctica - Mejor jugada',
    avoid_blunder: 'Táctica - Precisión',
    find_mate: 'Táctica - Mate',
    spot_threat: 'Táctica - Defensa',
    find_tactic: 'Táctica - Recurso',
    defend_position: 'Defensa',
    convert_advantage: 'Técnica - Conversión',
    other: 'Patrón general',
  };
  return themes[type] || 'Entrenamiento personalizado';
}

async function loadTrainingOriginReview() {
  if (!TRAINING_SOLVER_MODE || !activeExercise || !activeExercise.game_id) return;
  const gameId = Number(activeExercise.game_id || 0);
  if (!gameId || trainingOriginReviewLoadedFor === gameId) return;
  trainingOriginReviewLoadedFor = gameId;
  const target = document.getElementById('trainingOriginReviewGrid');
  if (!target) return;
  try {
    const response = await fetch(`api/review.php?id=${gameId}`, { cache: 'no-store' });
    const data = await response.json();
    renderTrainingOriginReview(data);
  } catch (error) {
    target.innerHTML = `<div class="empty-state compact"><strong>No se pudo cargar la partida origen.</strong><span>${escapeHtml(error.message || 'Error inesperado.')}</span></div>`;
  }
}

function renderTrainingOriginReview(data) {
  const target = document.getElementById('trainingOriginReviewGrid');
  if (!target) return;
  if (!data || !data.ok) {
    target.innerHTML = '<div class="empty-state compact"><strong>Sin resumen disponible.</strong><span>Abre la partida para revisar el análisis completo.</span></div>';
    return;
  }
  const game = data.game || {};
  const summary = data.summary || {};
  const tags = (summary.smart_tags || []).slice(0, 2);
  const gameId = Number(game.id || activeExercise.game_id || 0);
  const labels = [
    ['best', 'Mejor'],
    ['excellent', 'Excelente'],
    ['good', 'Buena'],
    ['inaccuracy', 'Imprecisión'],
    ['mistake', 'Error'],
    ['blunder', 'Omisión grave']
  ];
  const counts = summary.counts || {};
  target.innerHTML = `
    <section class="home-review-card training-origin-card">
      <div class="home-review-head">
        <div>
          <h2>Revisión de partida origen</h2>
          <p>${escapeHtml(trainingReviewMeta(game))}</p>
        </div>
        ${gameId ? `<a class="home-review-piece" href="review.php?id=${gameId}" aria-label="Abrir revisión">♞</a>` : '<span class="home-review-piece">♞</span>'}
      </div>
      <div class="home-review-coach">
        <div class="coach-avatar">♞</div>
        <div>
          <h3>${escapeHtml(summary.headline || 'Revisión de partida')}</h3>
          <p>${escapeHtml(summary.comment || 'Vamos a revisar los momentos importantes.')}</p>
          ${tags.length ? `<div class="smart-tag-list review-tags">${tags.map(smartTagChip).join('')}</div>` : ''}
        </div>
      </div>
      <div class="review-kpis home-review-kpis">
        <div><span>Accuracy</span><b>${formatTrainingReviewNumber(summary.accuracy)}</b></div>
        <div><span>ACPL</span><b>${formatTrainingReviewNumber(summary.acpl)}</b></div>
        <div><span>Jugadas</span><b>${Number(summary.moves || 0)}</b></div>
      </div>
    </section>
    <section class="home-review-counts-card training-origin-card">
      <h2>Resumen</h2>
      <div class="review-counts home-review-counts">
        ${labels.map(([key, label]) => `
          <div class="review-count ${key}">
            <span>${trainingReviewBucketIcon(key)}</span>
            <strong>${Number(counts[key] || 0)}</strong>
            <small>${escapeHtml(label)}</small>
          </div>
        `).join('')}
      </div>
    </section>
  `;
}

function trainingReviewMeta(game) {
  const white = game.white_player || 'Blancas';
  const black = game.black_player || 'Negras';
  const result = game.result_raw || '-';
  const date = game.played_at || (game.imported_at || '').slice(0, 10) || '-';
  return `${white} vs ${black} · ${result} · ${date}`;
}

function formatTrainingReviewNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num.toFixed(1).replace(/\.0$/, '') : '--';
}

function trainingReviewBucketIcon(bucket) {
  if (bucket === 'best') return '★';
  if (bucket === 'excellent') return '↑';
  if (bucket === 'good') return '✓';
  if (bucket === 'inaccuracy') return '?!';
  if (bucket === 'mistake') return '?';
  if (bucket === 'blunder') return '??';
  return '•';
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
  const displayGrid = trainingPreviewGrid(grid, selectedTrainingSquare.length >= 4 ? selectedTrainingSquare : completedTrainingMove);
  const legalTargets = TRAINING_SHOW_LEGAL_MOVES ? trainingLegalTargetSet(grid) : new Set();
  const previousMove = (activeExercise.previous_uci || '').toString().toLowerCase();
  const previousFrom = previousMove.length >= 4 ? previousMove.slice(0, 2) : '';
  const previousTo = previousMove.length >= 4 ? previousMove.slice(2, 4) : '';
  const solutionMove = (revealedTrainingSolution || '').toString().toLowerCase();
  const solutionFrom = solutionMove.length >= 4 ? solutionMove.slice(0, 2) : '';
  const solutionTo = solutionMove.length >= 4 ? solutionMove.slice(2, 4) : '';
  const completedMove = (completedTrainingMove || '').toString().toLowerCase();
  const completedFrom = completedMove.length >= 4 ? completedMove.slice(0, 2) : '';
  const completedTo = completedMove.length >= 4 ? completedMove.slice(2, 4) : '';
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
      const correct = sq === completedFrom || sq === completedTo ? ' correct' : '';
      const correctDestination = sq === completedTo ? ' correct-destination' : '';
      const legal = legalTargets.has(sq) ? ' legal-target' : '';
      const hint = sq === trainingHintFrom ? ' hint' : '';
      html += `<button class="sq ${dark ? 'dark' : 'light'}${previous}${selected}${solution}${correct}${correctDestination}${legal}${hint}" type="button" data-sq="${sq}" onclick="selectTrainingSquare('${sq}')">${trainingPieceImageHtml(displayGrid[r][file] || '')}</button>`;
    }
  }
  board.innerHTML = html;
  board.dataset.orientation = trainingBoardOrientation;
  renderTrainingBoardCoordinates();
}

function renderTrainingBoardCoordinates() {
  const ranksEl = document.getElementById('trainingBoardRanks');
  const filesEl = document.getElementById('trainingBoardFiles');
  const frame = document.getElementById('trainingBoardFrame');
  if (!ranksEl || !filesEl) return;
  const ranks = trainingBoardOrientation === 'black' ? [1,2,3,4,5,6,7,8] : [8,7,6,5,4,3,2,1];
  const files = trainingBoardOrientation === 'black' ? ['h','g','f','e','d','c','b','a'] : ['a','b','c','d','e','f','g','h'];
  ranksEl.innerHTML = ranks.map(rank => `<span>${rank}</span>`).join('');
  filesEl.innerHTML = files.map(file => `<span>${file}</span>`).join('');
  if (frame) frame.dataset.orientation = trainingBoardOrientation;
}

function trainingPreviewGrid(grid, move = selectedTrainingSquare) {
  const preview = grid.map(row => row.slice());
  if (!move || move.length < 4) return preview;
  const from = move.slice(0, 2);
  const to = move.slice(2, 4);
  const state = { ...trainingFenState(activeExercise?.fen || ''), grid: preview };
  const legalMove = trainingLegalMovesForState(state, from).find(candidate => candidate.to === to);
  if (legalMove) {
    const promotion = move.slice(4, 5).toLowerCase();
    if (promotion && ['q', 'r', 'b', 'n'].includes(promotion)) {
      legalMove.promotion = state.turn === 'w' ? promotion.toUpperCase() : promotion;
    }
    return trainingApplyMove(state, legalMove).grid;
  }
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
  if (selectedTrainingSquare.length !== 2) return new Set();
  return trainingLegalTargetsFrom(selectedTrainingSquare, grid);
}

function trainingLegalTargetsFrom(square, grid = null) {
  const targets = new Set();
  if (!activeExercise || !square || square.length !== 2) return targets;
  if (trainingExerciseFinished()) return targets;

  const state = trainingFenState(activeExercise.fen);
  const from = trainingSquareToGrid(square);
  if (!from) return targets;
  const boardGrid = grid || trainingBoardGridFromPlacement(state.placement);
  const piece = boardGrid[from.row][from.file] || '';
  if (!piece || trainingPieceColor(piece) !== state.turn) return targets;

  trainingLegalMovesForState({ ...state, grid: boardGrid }, square).forEach(move => targets.add(move.to));
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
  if (!activeExercise || trainingExerciseFinished() || trainingMoveSubmitting) return;
  const state = trainingFenState(activeExercise.fen);
  const clickedPiece = trainingPieceAtSquare(square);
  if (!selectedTrainingSquare || selectedTrainingSquare.length >= 4) {
    if (!clickedPiece || trainingPieceColor(clickedPiece) !== state.turn) {
      trainingSelectionMessage = `Selecciona una pieza ${state.turn === 'b' ? 'negra' : 'blanca'}.`;
      updateTrainingDraft();
      return;
    }
    selectedTrainingSquare = square;
    trainingSelectionMessage = '';
  } else if (selectedTrainingSquare === square) {
    selectedTrainingSquare = '';
    trainingSelectionMessage = '';
  } else if (clickedPiece && trainingPieceColor(clickedPiece) === state.turn) {
    selectedTrainingSquare = square;
    trainingSelectionMessage = '';
  } else {
    const legalTargets = trainingLegalTargetsFrom(selectedTrainingSquare);
    if (!legalTargets.has(square)) {
      trainingSelectionMessage = 'Esa casilla no es un destino legal para la pieza seleccionada.';
      updateTrainingDraft();
      return;
    }
    selectedTrainingSquare += square;
    trainingSelectionMessage = '';
  }
  renderTrainingBoard();
  updateTrainingDraft();
  if (selectedTrainingSquare.length >= 4 && TRAINING_AUTO_SUBMIT_MOVE) {
    Promise.resolve().then(() => submitTrainingMove()).catch(showTrainingError);
  }
}

function updateTrainingDraft() {
  const draft = document.getElementById('trainingMoveDraft');
  const submit = document.getElementById('trainingSubmitBtn');
  const complete = selectedTrainingSquare.length >= 4;
  const promotionWrap = document.getElementById('trainingPromotionWrap');
  const promotion = complete && trainingMoveNeedsPromotion(selectedTrainingSquare);
  if (promotionWrap) promotionWrap.hidden = !promotion;
  if (draft) {
    if (trainingSelectionMessage) draft.textContent = trainingSelectionMessage;
    else if (!selectedTrainingSquare) draft.textContent = 'Selecciona origen y destino en el tablero.';
    else if (!complete) draft.textContent = `Origen seleccionado: ${selectedTrainingSquare}. Ahora elige destino.`;
    else draft.textContent = `Jugada seleccionada: ${trainingSelectedMoveText()}.`;
  }
  if (submit) submit.disabled = !complete || trainingExerciseFinished() || trainingMoveSubmitting;
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
  trainingSelectionMessage = '';
  renderTrainingBoard();
  updateTrainingDraft();
}

function trainingExerciseFinished() {
  return !!(activeExercise && (!trainingExerciseIsTrainable(activeExercise) || revealedTrainingSolution || attemptedTrainingMoves.length >= 5));
}

function showTrainingHint() {
  if (!activeExercise || trainingExerciseFinished()) return;
  const from = (activeExercise.hint_from || '').toString().toLowerCase();
  if (from.length !== 2) return;
  trainingUsedHint = true;
  trainingHintFrom = from;
  selectedTrainingSquare = trainingHintFrom;
  trainingSelectionMessage = '';
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
  const next = trainingExercises.find(item => trainingExerciseIsTrainable(item) && Number(item.id || 0) !== currentId);
  if (!next) {
    closeTrainingSolver();
    return;
  }
  await openTrainingExercise(next.id);
}

async function submitTrainingMove() {
  if (!activeExercise || selectedTrainingSquare.length < 4 || trainingExerciseFinished() || trainingMoveSubmitting) return;
  trainingMoveSubmitting = true;
  updateTrainingDraft();
  try {
    let move = selectedTrainingSquare.toLowerCase();
    if (trainingMoveNeedsPromotion(move)) {
      move += document.getElementById('trainingPromotionPiece')?.value || 'q';
    }
    attemptedTrainingMoves.push(move);
    selectedTrainingSquare = '';
    trainingSelectionMessage = '';
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
    if (data.solved) completedTrainingMove = move;
    showTrainingFeedback(data);
    if (data.solved || data.solution_uci) stopTrainingExerciseTimer();
    renderTrainingAttempts();
    renderTrainingSolverChrome();
    renderTrainingBoard();
    renderTrainingStatsFromResponse(data);
    renderTrainingExperience();
    if (data.solved || data.solution_uci) {
      await loadTraining(currentTrainingPage);
    }
  } finally {
    trainingMoveSubmitting = false;
    updateTrainingDraft();
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
  const history = document.getElementById('trainingAttemptHistory');
  const count = document.getElementById('trainingAttemptsCount');
  const html = attemptedTrainingMoves.length
    ? attemptedTrainingMoves.map((move, index) => `<span>${index + 1}. ${escapeHtml(move)}</span>`).join('')
    : '<span>Sin intentos todavía.</span>';
  if (el) el.innerHTML = html;
  if (history) history.innerHTML = html;
  if (count) count.textContent = `${attemptedTrainingMoves.length}/5`;
}

function renderTrainingStatsFromResponse(data) {
  if (!data.stats) return;
  trainingStats = data.stats;
  if (data.experience) trainingExperience = data.experience;
  if (data.session) activeTrainingSession = data.session;
  renderTrainingStats();
  renderTrainingExperience();
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
    renderTrainingExperience();
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
  const feedback = document.getElementById('trainingFeedback');
  if (feedback) {
    feedback.textContent = error.message || 'No se pudo cargar Entrenamiento.';
    feedback.className = 'training-feedback warn';
  }
}

function escapeHtml(value) {
  return (value === null || typeof value === 'undefined' ? '' : value).toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

bindTrainingFilters();
loadTraining(1)
  .then(() => {
    if (Number.isInteger(TRAINING_INITIAL_EXERCISE_ID) && TRAINING_INITIAL_EXERCISE_ID > 0) {
      return openTrainingExercise(TRAINING_INITIAL_EXERCISE_ID);
    }
    if (TRAINING_SOLVER_MODE && trainingExercises.length) {
      return openTrainingExercise(trainingExercises[0].id);
    }
    return null;
  })
  .catch(showTrainingError);
