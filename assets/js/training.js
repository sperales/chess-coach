let trainingExercises = [];
let trainingTypes = {};
let trainingTypeCounts = {};
let trainingStats = {};
let trainingPagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.trainingPerPage) || 20, total: 0, pages: 1 };
let currentTrainingPage = 1;

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
  trainingPagination = data.pagination || trainingPagination;
  currentTrainingPage = trainingPagination.page || currentTrainingPage;
  renderTrainingTypeOptions();
  renderTrainingStats();
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

function trainingIconFor(kind) {
  return kind === 'pulse' ? '◎' : kind === 'target' ? '●' : kind === 'star' ? '★' : '▷';
}

function renderTrainingExercises() {
  const el = document.getElementById('trainingExerciseList');
  if (!el) return;
  if (!trainingExercises.length) {
    el.innerHTML = `
      <div class="empty-state">
        <strong>No hay ejercicios con estos filtros.</strong>
        <span>Genera ejercicios desde Ajustes / Mi Perfil o analiza nuevas partidas para alimentar el Training Center.</span>
        <a href="profile.php">Ir a procesos batch</a>
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
        <a class="btn secondary small" href="${escapeAttr(item.review_url || '#')}">Ver partida</a>
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

function bindTrainingFilters() {
  ['trainingTypeFilter', 'trainingStatusFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => loadTraining(1).catch(showTrainingError));
  });
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
