let games = [];
let dashboardData = null;
let playerDnaData = null;
let playerProgressData = null;
let trainingPlanData = null;
let trainingDashboardExtrasLoaded = false;
let currentPage = 1;
let pagination = { page: 1, per_page: (window.CHESS_COACH_CONFIG && window.CHESS_COACH_CONFIG.gamesPerPage) || 50, total: 0, pages: 1 };
let stats = { recent10: { total: 0, wins: 0, losses: 0, draws: 0 }, analysis_accuracy: { average: null, analyzed_games: 0 }, queue: { pending_total: 0 } };
let analyzing = new Set();
let pollTimer = null;
let gamesPanelMode = 'latest';
let homeProgressPeriod = '30';
let homeProgressMetric = 'accuracy';

async function dashboardGet(url) {
  const response = await fetch(url, { cache: 'no-store' });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'No se pudieron cargar los datos.');
  return data;
}

async function dashboardPost(url, body = {}) {
  const response = await fetch(url, {
    method: 'POST',
    headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudieron cargar los datos.');
  return data;
}

async function loadTrainingPlanAndProgress() {
  if (trainingDashboardExtrasLoaded) return;
  const [progressPayload, planPayload] = await Promise.all([
    dashboardGet('api/training.php?action=progress').catch(() => ({ progress: null })),
    dashboardPost('api/training-plan.php').catch(() => ({ plan: null })),
  ]);
  playerProgressData = progressPayload.progress || null;
  trainingPlanData = planPayload.plan || null;
  if (playerProgressData && playerProgressData.available === false) {
    const refreshed = await dashboardPost('api/training.php?action=progress_refresh').catch(() => null);
    if (refreshed && refreshed.progress) playerProgressData = refreshed.progress;
  }
  trainingDashboardExtrasLoaded = true;
}

async function load(page = currentPage) {
  currentPage = Math.max(1, Number(page) || 1);
  const perPage = pagination.per_page || 50;
  const [gamesPayload, trainerPayload, playerDnaPayload] = await Promise.all([
    dashboardGet(`api/games.php?action=list&page=${currentPage}&per_page=${perPage}`),
    dashboardGet('api/dashboard.php'),
    dashboardGet('api/player-dna.php?action=snapshot').catch(() => ({ ok: true, snapshot: null }))
  ]);

  games = gamesPayload.games || [];
  pagination = gamesPayload.pagination || pagination;
  currentPage = pagination.page || currentPage;
  stats = gamesPayload.stats || stats;
  dashboardData = trainerPayload;
  playerDnaData = playerDnaPayload;
  await loadTrainingPlanAndProgress();
  render();
  schedulePollingIfNeeded();
}

function render() {
  renderStats();
  renderHomeProgressChart();
  renderTrainerDashboard();
  renderHomePlayerDna();
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
  const avgAccuracy = accuracy.average === null || typeof accuracy.average === 'undefined' ? null : Number(accuracy.average);
  const streak = (dashboardData && dashboardData.training_experience && dashboardData.training_experience.streak) || {};
  const cards = [
    { kind: 'games', label: 'Partidas', value: global.total || 0 },
    { kind: 'wins', label: 'Win rate', value: `${winRate}%` },
    { kind: 'accuracy', label: 'Accuracy media', value: avgAccuracy === null ? '--' : `${avgAccuracy.toFixed(1)}%` },
    { kind: 'streak', label: 'Racha', value: Number(streak.days || 0) }
  ];
  el.innerHTML = cards.map(card => `<article class="metric-card home-total-card ${card.kind}">
    <span class="home-total-icon" aria-hidden="true">${homeTotalIcon(card.kind)}</span>
    <div><span>${escapeHtml(card.label)}</span><b>${escapeHtml(card.value)}</b></div>
  </article>`).join('');
}

function homeTotalIcon(kind) {
  if (kind === 'games') return '♙';
  if (kind === 'wins') return '◎';
  if (kind === 'accuracy') return '◉';
  return 'ϟ';
}

function renderHomeProgressChart() {
  const el = document.getElementById('homeProgressChart');
  if (!el || !dashboardData) return;
  const periods = dashboardData.progress_history || {};
  const series = periods[homeProgressPeriod] && periods[homeProgressPeriod][homeProgressMetric];
  document.querySelectorAll('[data-progress-period]').forEach(button => button.classList.toggle('active', button.dataset.progressPeriod === homeProgressPeriod));
  document.querySelectorAll('[data-progress-metric]').forEach(button => button.classList.toggle('active', button.dataset.progressMetric === homeProgressMetric));
  if (!series || !Array.isArray(series.values) || !series.values.some(value => value !== null && value !== '' && Number.isFinite(Number(value)))) {
    el.innerHTML = '<div class="empty-state compact"><strong>Sin datos para este periodo.</strong><span>La gráfica aparecerá cuando haya actividad suficiente.</span></div>';
    return;
  }
  el.innerHTML = homeProgressSvg(series, homeProgressMetric);
}

function homeProgressSvg(series, metric) {
  const sourceLabels = Array.isArray(series.labels) ? series.labels : [];
  const sourceValues = Array.isArray(series.values) ? series.values : [];
  const points = sourceValues.map((value, index) => ({ raw: value, value: Number(value), label: sourceLabels[index] || '' })).filter(point => point.raw !== null && point.raw !== '' && Number.isFinite(point.value));
  const width = 720;
  const height = 250;
  const left = 44;
  const right = 18;
  const top = 18;
  const bottom = 38;
  const chartWidth = width - left - right;
  const chartHeight = height - top - bottom;
  const absoluteMax = metric === 'performance' ? 1000 : 100;
  const values = points.map(point => Math.max(0, Math.min(absoluteMax, point.value)));
  const observedMin = Math.min(...values);
  const observedMax = Math.max(...values);
  const minimumSpan = metric === 'performance' ? 100 : 10;
  const padding = Math.max(minimumSpan * .25, (observedMax - observedMin) * .2);
  let minValue = Math.max(0, Math.floor((observedMin - padding) / 5) * 5);
  let maxValue = Math.min(absoluteMax, Math.ceil((observedMax + padding) / 5) * 5);
  if (maxValue - minValue < minimumSpan) {
    const center = (observedMin + observedMax) / 2;
    minValue = Math.max(0, Math.floor(center - minimumSpan / 2));
    maxValue = Math.min(absoluteMax, minValue + minimumSpan);
    minValue = Math.max(0, maxValue - minimumSpan);
  }
  if (maxValue <= minValue) maxValue = Math.min(absoluteMax, minValue + minimumSpan);
  const valueSpan = Math.max(1, maxValue - minValue);
  const coordinates = points.map((point, index) => ({
    ...point,
    x: left + (points.length === 1 ? chartWidth / 2 : (index / (points.length - 1)) * chartWidth),
    y: top + chartHeight - ((Math.max(minValue, Math.min(maxValue, point.value)) - minValue) / valueSpan) * chartHeight,
  }));
  const polyline = coordinates.map(point => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ');
  const ticks = [0, .25, .5, .75, 1];
  const labelIndexes = [...new Set([0, Math.floor((points.length - 1) / 2), points.length - 1])];
  const metricLabel = metric === 'accuracy' ? 'Accuracy' : (metric === 'win_rate' ? 'Win rate' : 'Índice de rendimiento');
  const suffix = metric === 'performance' ? '' : '%';
  return `<svg class="home-progress-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Evolución de ${escapeAttr(metricLabel)}">
    ${ticks.map(tick => {
      const y = top + chartHeight - tick * chartHeight;
      return `<line x1="${left}" y1="${y}" x2="${width - right}" y2="${y}" class="home-progress-gridline"></line><text x="${left - 8}" y="${y + 4}" text-anchor="end">${Math.round(minValue + tick * valueSpan)}${suffix}</text>`;
    }).join('')}
    <polyline points="${polyline}" class="home-progress-line"></polyline>
    ${coordinates.map(point => `<circle cx="${point.x}" cy="${point.y}" r="4" class="home-progress-point"><title>${escapeHtml(formatProgressDate(point.label))}: ${Number(point.value).toFixed(metric === 'performance' ? 0 : 1)}${suffix}</title></circle>`).join('')}
    ${labelIndexes.map(index => `<text x="${coordinates[index].x}" y="${height - 10}" text-anchor="${index === 0 ? 'start' : (index === points.length - 1 ? 'end' : 'middle')}">${escapeHtml(formatProgressDate(points[index].label))}</text>`).join('')}
  </svg>`;
}

function formatProgressDate(value) {
  const parts = String(value || '').split('-');
  if (parts.length !== 3) return String(value || '');
  return `${Number(parts[2])}/${Number(parts[1])}`;
}

function initializeHomeProgressControls() {
  document.querySelectorAll('[data-progress-period]').forEach(button => button.addEventListener('click', () => {
    homeProgressPeriod = button.dataset.progressPeriod || '30';
    renderHomeProgressChart();
  }));
  document.querySelectorAll('[data-progress-metric]').forEach(button => button.addEventListener('click', () => {
    homeProgressMetric = button.dataset.progressMetric || 'accuracy';
    renderHomeProgressChart();
  }));
}

function iconFor(kind) {
  return kind === 'pulse' ? '⌁' : kind === 'target' ? '◎' : kind === 'star' ? '★' : '▷';
}

function dashboardMetricTrends(overview, previous, queue, anchors = {}) {
  const recentGames = ((dashboardData && dashboardData.recent_games) || []).slice().reverse();
  const history = (dashboardData && dashboardData.metric_history) || {};
  const gamesTrend = numericTrend(history.games_total, [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
  const analysesCompleted = numericTrend(history.analyses_completed, [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
  const gamesAdded = Number(history.games_added || 0);
  let wins = 0;
  const recentWinRates = recentGames.length ? recentGames.map((game, index) => {
    if ((game.user_result || '') === 'win') wins++;
    return Math.round((wins / (index + 1)) * 100);
  }) : [0, 0, 0, 0, 0, 0];
  const winRateBase = recentWinRates.length > 4 ? recentWinRates.slice(2) : recentWinRates;
  const winRateTrend = anchorTrendToValue(winRateBase, anchors.winRate, 0, 100);
  const accuracyBase = recentGames
    .map(game => game.accuracy === null || typeof game.accuracy === 'undefined' ? null : Number(game.accuracy))
    .filter(value => Number.isFinite(value));
  const accuracyTrend = anchorTrendToValue(accuracyBase, anchors.avgAccuracy, 0, 100);
  return {
    games: gamesTrend,
    gamesAdded: Number.isFinite(gamesAdded) ? gamesAdded : 0,
    winRate: winRateTrend,
    accuracy: accuracyTrend.length ? accuracyTrend : [0, 0, 0, 0, 0],
    analysesCompleted,
  };
}

function numericTrend(values, fallback) {
  const nums = Array.isArray(values) ? values.map(Number).filter(value => Number.isFinite(value)) : [];
  return nums.length >= 2 ? nums : fallback;
}

function anchorTrendToValue(values, endValue, min = -Infinity, max = Infinity) {
  const nums = (values || []).map(Number).filter(value => Number.isFinite(value));
  const target = Number(endValue);
  if (!Number.isFinite(target)) return nums.length >= 2 ? nums : [0, 0];
  if (!nums.length) return [target, target];
  const delta = target - nums[nums.length - 1];
  const anchored = nums.map(value => Math.max(min, Math.min(max, value + delta)));
  return anchored.length >= 2 ? anchored : [target, target];
}

function sparklineSvg(values, kind) {
  const nums = (values || []).map(Number).filter(value => Number.isFinite(value));
  const data = nums.length >= 2 ? nums : [0, 0];
  const width = 180;
  const height = 58;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const range = max - min || 1;
  const points = data.map((value, index) => {
    const x = data.length === 1 ? width : (index / (data.length - 1)) * width;
    const y = height - ((value - min) / range) * (height - 10) - 5;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(' ');
  return `<svg class="metric-spark ${escapeAttr(kind)}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true">
    <polygon points="0,${height} ${points} ${width},${height}" class="metric-spark-fill"></polygon>
    <polyline points="${points}" class="metric-spark-line"></polyline>
  </svg>`;
}

function trendDeltaLabel(delta, unit) {
  if (!Number.isFinite(delta) || Math.abs(delta) < 0.1) return 'sin cambios';
  const rounded = Math.abs(delta) >= 10 ? Math.round(delta) : Number(delta).toFixed(1).replace(/\.0$/, '');
  return `${delta > 0 ? '↗' : '↘'} ${rounded} ${unit}`;
}

function trendDeltaFromValues(values) {
  const nums = (values || []).map(Number).filter(value => Number.isFinite(value));
  if (nums.length < 2) return NaN;
  return nums[nums.length - 1] - nums[0];
}

function trendLabelClass(label) {
  if (!label) return '';
  if (label.indexOf('↘') !== -1) return 'down';
  if (label.indexOf('↗') !== -1) return 'up';
  return '';
}

function renderTrainerDashboard() {
  if (!dashboardData) return;
  renderHero();
  renderFocus();
  renderState();
  renderHomeTrainingExperience();
  renderHomeProgressFocus();
  renderStrengths();
}

function renderHomeProgressFocus() {
  const el = document.getElementById('homeProgressFocus');
  if (!el) return;
  const focus = (dashboardData.training_focus || [])[0] || null;
  const strength = (dashboardData.strengths || [])[0] || null;
  if (!focus && !strength) {
    el.innerHTML = '<p class="muted">Analiza más partidas para descubrir tu foco actual.</p><a href="player-dna.php">Ver mi ADN <span aria-hidden="true">›</span></a>';
    return;
  }
  el.innerHTML = `
    ${focus ? `<div class="home-current-focus"><span>Foco actual</span><strong>${escapeHtml(focus.title || 'Entrenamiento')}</strong><small>↗ ${escapeHtml(focus.description || 'Sigue trabajando este patrón.')}</small></div>` : ''}
    ${strength ? `<div class="home-current-strength"><span aria-hidden="true">♞</span><div><strong>Fortaleza</strong><small>${escapeHtml(strength.title || 'En progreso')}</small></div></div>` : ''}
    <a href="player-dna.php">Ver mi ADN <span aria-hidden="true">›</span></a>
  `;
}

function renderHero() {
  const el = document.getElementById('trainerHeroText');
  const focusBox = document.getElementById('trainerHeroFocus');
  const focusLabel = document.getElementById('trainerHeroFocusLabel');
  const focus = (dashboardData.training_focus || [])[0];
  if (!el) return;
  if (focusBox && focusLabel) {
    if (focus) {
      focusLabel.textContent = focus.title || 'Foco actual';
      focusBox.hidden = false;
    } else {
      focusBox.hidden = true;
    }
  }
  if (!dashboardData.period || !dashboardData.period.available_games) {
    el.textContent = 'Importa y analiza partidas para construir tu primer plan de entrenamiento.';
    return;
  }
  el.textContent = 'Nova ha preparado tu entrenamiento.';
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
      <div class="trainer-focus-icon">${focusIconSvg(focus, index)}</div>
      <h3>${escapeHtml(focus.title || 'Foco')}</h3>
      <p>${escapeHtml(focus.description || '')}</p>
      ${focusEvidence(focus)}
      <strong>${escapeHtml(focus.recommended_action || '')}</strong>
      ${focus.games_url ? `<a href="${escapeAttr(focus.games_url)}">${focusLinkLabel(focus.games_url)}</a>` : ''}
    </article>
  `).join('') + (available < minimum ? `<p class="muted small-note">Con ${minimum} partidas analizadas el diagnóstico será más fiable.</p>` : '');
}

function focusIconSvg(focus, index) {
  const title = ((focus && focus.title) || '').toLowerCase();
  if (title.indexOf('táct') !== -1 || title.indexOf('tact') !== -1 || index === 0) {
    return '<img src="assets/images/focus/ojo.png" alt="" loading="lazy">';
  }
  if (title.indexOf('final') !== -1 || index === 2) {
    return '<img src="assets/images/focus/bandera.png" alt="" loading="lazy">';
  }
  return '<img src="assets/images/focus/diana.png" alt="" loading="lazy">';
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
  const ring = document.getElementById('trainerAccuracyRing');
  const ringValue = document.getElementById('trainerAccuracyRingValue');
  const overview = dashboardData.overview || {};
  if (summary) summary.textContent = dashboardData.summary_text || 'Cargando resumen...';
  const accuracy = overview.avg_accuracy === null || typeof overview.avg_accuracy === 'undefined' ? null : Number(overview.avg_accuracy);
  if (ring) {
    const pct = accuracy === null ? 0 : Math.max(0, Math.min(100, accuracy));
    ring.style.setProperty('--accuracy', `${pct}%`);
  }
  if (ringValue) ringValue.textContent = accuracy === null ? '--' : `${accuracy.toFixed(1)}%`;
  if (!kpis) return;
  const values = [
    ['Win rate', typeof overview.score_rate === 'number' ? `${overview.score_rate}%` : '--'],
    ['ACPL', overview.avg_acpl === null || typeof overview.avg_acpl === 'undefined' ? '--' : Number(overview.avg_acpl).toFixed(1)],
    ['Errores', `B:${overview.own_blunders || 0}/E:${overview.own_mistakes || 0}/I:${overview.own_inaccuracies || 0}`],
    ['Color', colorNote(overview)]
  ];
  kpis.innerHTML = values.map(item => `<div><span>${escapeHtml(item[0])}</span><b>${escapeHtml(item[1])}</b></div>`).join('');
}

function renderHomeTrainingExperience() {
  const el = document.getElementById('homeToday');
  if (!el) return;
  const experience = (dashboardData && dashboardData.training_experience) || {};
  const settings = experience.settings || {};
  const today = experience.today || {};
  const plan = trainingPlanData || { daily: [], weekly: [] };
  const dailyGoals = Array.isArray(plan.daily) ? plan.daily : [];
  const weeklyGoals = Array.isArray(plan.weekly) ? plan.weekly : [];
  const primaryGoal = dailyGoals.find(goal => goal.status !== 'completed') || weeklyGoals.find(goal => goal.status !== 'completed') || dailyGoals[0] || weeklyGoals[0] || null;
  const primaryProgress = primaryGoal ? homeTrainingProgressPercent(primaryGoal.progress_percent) : homeTrainingProgressPercent(homeTrainingTodayProgress(today, settings));
  const primaryTitle = primaryGoal ? (primaryGoal.title || 'Entrenamiento recomendado') : 'Empieza tu entrenamiento de hoy';
  const current = primaryGoal ? Number(primaryGoal.current_value || 0) : Number(today.exercises || 0);
  const target = primaryGoal ? Number(primaryGoal.target_value || 1) : Math.max(1, Number(settings.daily_exercise_goal || 5));
  const focusName = ((dashboardData.training_focus || [])[0] || {}).title || primaryTitle;
  const coachPlan = dashboardData.coach_plan || null;
  const coachTitle = coachPlan?.focus?.title || primaryTitle;
  const coachCurrent = Number((coachPlan?.items || []).filter(item => item.status === 'completed').length || current);
  const coachTarget = Number(coachPlan?.item_count || target);
  const coachProgress = coachTarget > 0 ? homeTrainingProgressPercent((coachCurrent / coachTarget) * 100) : primaryProgress;
  const coachRationale = coachPlan?.rationale || `Hoy nos centraremos en ${focusName.toLowerCase()}.`;
  const trainingHref = homeTrainingActionUrl(coachPlan, dashboardData.active_training);
  const hasActiveTraining = Number(dashboardData.active_training?.id || 0) > 0
    && (coachPlan?.items || []).some(item => item.status === 'pending' || item.status === 'active');
  el.innerHTML = `
    <div class="home-nova-card">
      <div class="home-nova-card__content">
        <span class="home-nova-eyebrow">Tu entrenamiento de hoy</span>
        <h2>${escapeHtml(coachTitle)}</h2>
        <strong class="home-nova-count">${coachCurrent} de ${coachTarget}</strong>
        <div class="home-training-progress" aria-label="Progreso ${coachProgress}%"><i style="width:${coachProgress}%"></i></div>
        <blockquote>${escapeHtml(coachRationale)}</blockquote>
        <a class="home-nova-dna" href="player-dna.php">Ver mi ADN <span aria-hidden="true">›</span></a>
      </div>
      <img class="home-nova-pointing" src="assets/nova/nova-coach-pointing.png" alt="Nova, tu entrenador">
    </div>
    <a class="btn home-nova-primary" href="${escapeAttr(trainingHref)}">${hasActiveTraining ? 'Seguir entrenando' : 'Empezar entrenamiento'} <span aria-hidden="true">›</span></a>
  `;
}

function homeTrainingActionUrl(plan, activeTraining) {
  const trainingId = Number(activeTraining?.id || plan?.training_id || 0);
  const pending = (plan?.items || []).find(item => item.status === 'active' || item.status === 'pending');
  if (!pending || trainingId <= 0) return 'training.php?start=1';
  const params = new URLSearchParams({ training_id: String(trainingId) });
  if (pending.item_type === 'scenario' && Number(pending.scenario_id || 0) > 0) {
    params.set('id', String(Number(pending.scenario_id)));
    return `training-scenario.php?${params.toString()}`;
  }
  if (Number(pending.exercise_id || 0) > 0) {
    params.set('id', String(Number(pending.exercise_id)));
    return `training-exercise.php?${params.toString()}`;
  }
  return 'training.php?start=1';
}

function legacyHomeProgressMetric(label, value, detail, percent) {
  const pct = homeTrainingProgressPercent(percent);
  return `<article class="training-progress-metric"><div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div><p>${escapeHtml(detail)}</p><div class="home-training-progress"><i style="width:${pct}%"></i></div></article>`;
}

function homePlanColumn(title, goals) {
  const items = Array.isArray(goals) ? goals : [];
  return `<section class="training-plan-column"><div class="training-plan-column-head"><h3>${escapeHtml(title)}</h3><span>${items.filter(goal => goal.status === 'completed').length}/${items.length}</span></div>${items.length ? items.map(homePlanGoal).join('') : '<p class="muted">No hay acciones pendientes.</p>'}</section>`;
}

function homePlanGoal(goal) {
  const done = goal.status === 'completed';
  const percent = homeTrainingProgressPercent(goal.progress_percent);
  const content = `<span class="training-plan-check" aria-hidden="true">${done ? '✓' : ''}</span><div><strong>${escapeHtml(goal.title || 'Objetivo')}</strong><small>${escapeHtml(goal.rationale || '')}</small><div class="home-training-progress"><i style="width:${percent}%"></i></div><em>${Number(goal.current_value || 0)}/${Number(goal.target_value || 0)}</em></div>`;
  return goal.action_url && !done ? `<a class="training-plan-goal" href="${escapeAttr(goal.action_url)}">${content}</a>` : `<div class="training-plan-goal ${done ? 'completed' : ''}">${content}</div>`;
}

function homeTrainingMessage(today, streak) {
  if (today.goal_met) return 'Objetivo diario completado. Buen trabajo: la racha sigue viva.';
  if (today.trained) return 'Ya has entrenado hoy. Un poco más y conviertes actividad en objetivo cumplido.';
  if (Number(streak.days || 0) > 0) return 'Tu racha espera el objetivo de hoy. Un bloque corto mantiene la continuidad.';
  return 'Empieza con un ejercicio. La continuidad nace de sesiones pequeñas y sostenibles.';
}

function homeTrainingCard(kind, label, value, detail, percent) {
  const pct = homeTrainingProgressPercent(percent);
  return `<article class="home-training-card ${escapeAttr(kind)}">
    <span>${homeTrainingIcon(kind)}</span>
    <div>
      <small>${escapeHtml(label)}</small>
      <div class="home-training-progress" aria-label="Progreso ${pct}%"><i style="width:${pct}%"></i></div>
      <strong>${escapeHtml(value)}</strong>
      <em>${escapeHtml(detail)}</em>
    </div>
  </article>`;
}

function homeTrainingIcon(kind) {
  if (kind === 'racha') return '↗';
  if (kind === 'hoy') return '◎';
  if (kind === 'semana') return '▦';
  return '↺';
}

function homeTrainingGoalLabel(settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  if (mode === 'minutes') return 'objetivo por tiempo';
  if (mode === 'both') return 'ejercicios y tiempo';
  return 'objetivo por ejercicios';
}

function homeTrainingTodayText(today, settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  const exercises = Number(today.exercises || 0);
  const minutes = Number(today.duration_minutes || 0);
  const exerciseGoal = Number(settings.daily_exercise_goal || 5);
  const minuteGoal = Number(settings.daily_minutes_goal || 10);
  if (mode === 'minutes') return `${minutes}/${minuteGoal} min`;
  if (mode === 'both') return `${exercises}/${exerciseGoal} ej. · ${minutes}/${minuteGoal} min`;
  return `${exercises}/${exerciseGoal} ejercicios`;
}

function homeTrainingWeekText(week, settings) {
  const days = Number(week.training_days || 0);
  const dayGoal = Number(week.training_days_goal || settings.weekly_training_days_goal || 4);
  const exercises = Number(week.exercises || 0);
  const exerciseGoal = Number(week.exercise_goal || settings.weekly_exercise_goal || 25);
  return `${days}/${dayGoal} días · ${exercises}/${exerciseGoal} ej.`;
}

function homeTrainingTodayProgress(today, settings) {
  const mode = settings.daily_goal_mode || 'exercises';
  const exercises = Number(today.exercises || 0);
  const minutes = Number(today.duration_minutes || 0);
  const exerciseGoal = Math.max(1, Number(settings.daily_exercise_goal || 5));
  const minuteGoal = Math.max(1, Number(settings.daily_minutes_goal || 10));
  if (mode === 'minutes') return (minutes / minuteGoal) * 100;
  if (mode === 'both') return Math.min((exercises / exerciseGoal) * 100, (minutes / minuteGoal) * 100);
  return (exercises / exerciseGoal) * 100;
}

function homeTrainingWeekProgress(week, settings) {
  const dayGoal = Math.max(1, Number(week.training_days_goal || settings.weekly_training_days_goal || 4));
  const exerciseGoal = Math.max(1, Number(week.exercise_goal || settings.weekly_exercise_goal || 25));
  const dayProgress = Number(week.training_days || 0) / dayGoal;
  const exerciseProgress = Number(week.exercises || 0) / exerciseGoal;
  return Math.min(dayProgress, exerciseProgress) * 100;
}

function homeTrainingProgressPercent(percent) {
  return Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
}

function renderHomePlayerDna() {
  const el = document.getElementById('homePlayerDna');
  if (!el) return;
  const snapshot = playerDnaData ? playerDnaData.snapshot : null;
  if (!snapshot) {
    el.innerHTML = `<div class="home-dna-empty">
      <div>
        <span class="trainer-state-badge insufficient">ADN pendiente</span>
        <h2>ADN del jugador</h2>
        <p>Genera tu primer snapshot para ver estilo, fortalezas, debilidades y evolución.</p>
      </div>
      <a class="btn secondary small" href="profile.php">Generar desde perfil</a>
    </div>`;
    return;
  }

  const strength = (snapshot.strengths || [])[0] || null;
  const weakness = (snapshot.weaknesses || [])[0] || null;
  const primary = (snapshot.recommendations && snapshot.recommendations.primary) || {};
  el.innerHTML = `<div class="home-dna-layout">
    <div class="home-dna-main">
      <span class="trainer-state-badge ${confidenceClass(snapshot.confidence)}">Confianza ${escapeHtml(confidenceLabel(snapshot.confidence))}</span>
      <h2>ADN del jugador</h2>
      <p>${escapeHtml(snapshot.summary_text || 'Perfil generado con tus partidas analizadas.')}</p>
      <div class="home-dna-actions">
        <a class="btn small" href="player-dna.php">Ver ADN completo</a>
        ${primary.url ? `<a class="btn secondary small" href="${escapeAttr(primary.url)}">${escapeHtml(primary.action_label || 'Ver foco')}</a>` : ''}
      </div>
    </div>
    <div class="home-dna-kpis">
      ${homeDnaItem('Perfil', snapshot.profile_label || '-')}
      ${homeDnaItem('Fortaleza', strength ? strength.title : '-')}
      ${homeDnaItem('Prioridad', weakness ? weakness.title : '-')}
      ${homeDnaItem('Muestra', `${Number(snapshot.recent_games || 0)} recientes / ${Number(snapshot.analyzed_games || 0)} analizadas`)}
    </div>
  </div>`;
}

function homeDnaItem(label, value) {
  return `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
}

function confidenceLabel(value) {
  return { low: 'baja', medium: 'media', high: 'alta' }[value] || value || 'baja';
}

function confidenceClass(value) {
  return value === 'high' ? 'good' : (value === 'medium' ? 'improving' : 'insufficient');
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
  el.innerHTML = strengths.map((item, index) => `
    <article class="trainer-strength">
      <span class="trainer-strength-icon">${strengthIconSvg(index)}</span>
      <span class="trainer-strength-copy">
        <strong>${escapeHtml(item.title || 'Fortaleza')}</strong>
        ${item.games_url ? `<a href="${escapeAttr(item.games_url)}">${escapeHtml(item.evidence || '')}</a>` : `<span>${escapeHtml(item.evidence || '')}</span>`}
      </span>
    </article>
  `).join('');
}

function strengthIconSvg(index) {
  const icons = [
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M4 12h16M7 7l10 10M17 7 7 17"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4l2.5 5 5.5.8-4 3.9.9 5.5-4.9-2.6-4.9 2.6.9-5.5-4-3.9 5.5-.8L12 4Z"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h10v4a5 5 0 0 1-10 0V5Z"/><path d="M7 7H4v2a4 4 0 0 0 4 4M17 7h3v2a4 4 0 0 1-4 4M12 14v5M9 19h6"/></svg>'
  ];
  return icons[index % icons.length];
}

function renderRows() {
  const el = document.getElementById('rows');
  if (!el) return;
  const panelTitle = document.getElementById('gamesPanelTitle');
  if (panelTitle) {
    panelTitle.textContent = gamesPanelMode === 'recommended' ? 'Partidas recomendadas' : 'Últimas partidas';
  }
  const thirdColumnHeader = document.getElementById('gamesThirdColumnHeader');
  if (thirdColumnHeader) {
    thirdColumnHeader.textContent = gamesPanelMode === 'recommended' ? 'Accuracy' : 'Ritmo';
  }
  if (gamesPanelMode === 'recommended') {
    const recommended = dashboardData ? (dashboardData.recommended_reviews || []) : [];
    el.innerHTML = recommended.map(recommendedRow).join('') || `
      <tr>
        <td colspan="7" class="muted">
          No hay recomendaciones todavía. Analiza más partidas para que el entrenador priorice revisiones.
        </td>
      </tr>`;
    return;
  }
  const list = games.slice(0, 5);
  el.innerHTML = list.map(gameRow).join('') || `<tr><td colspan="7" class="muted">Todavía no hay partidas. Empieza importando tus PGN o desde Chess.com.</td></tr>`;
}

function gameRow(game) {
  const actions = analysisActions(game);
  return `<tr><td data-label="Rival">${rivalCell(game)}</td><td data-label="Resultado">${resultBadge(game)}</td><td data-label="Ritmo">${escapeHtml(game.event_name || rhythmFromSite(game.site) || '-')}</td><td class="hide-sm" data-label="Fecha">${game.played_at || (game.imported_at || '').slice(0,10) || '-'}</td><td data-label="Análisis">${actions.meta}</td><td class="game-row-action">${actions.primary}</td><td class="game-row-action">${actions.secondary}</td></tr>`;
}

function recommendedRow(item) {
  return `
    <tr>
      <td data-label="Partida"><a class="game-title-link" href="${escapeAttr(item.review_url || '#')}"><strong>${escapeHtml(item.title || 'Partida')}</strong></a><small class="recommend-reason">${escapeHtml(item.reason || '')}</small></td>
      <td data-label="Resultado">${resultBadge(item)}</td>
      <td data-label="Accuracy">${item.accuracy === null || typeof item.accuracy === 'undefined' ? '--' : `${Number(item.accuracy).toFixed(1)}%`}</td>
      <td class="hide-sm" data-label="Fecha">${escapeHtml(item.played_at || '-')}</td>
      <td data-label="Análisis">${analysisMeta(item)}</td>
      <td class="game-row-action"><a class="btn small game-review-btn" href="${escapeAttr(item.review_url || '#')}">Revisar</a></td>
      <td class="game-row-action"></td>
    </tr>
  `;
}

function rivalCell(game) {
  const side = gameUserSide(game);
  const outcome = game.user_result === 'win' ? 'Ganaste' : (game.user_result === 'loss' ? 'Ganó el rival' : (game.user_result === 'draw' ? 'Tablas' : 'Resultado pendiente'));
  const color = side === 'w' ? 'Blancas' : (side === 'b' ? 'Negras' : 'Color desconocido');
  const piece = side === 'w' ? 'wp.png' : 'bp.png';
  return `<span class="rival-line"><span class="home-game-color"><img src="assets/pieces/Set%201/${piece}" alt="${escapeAttr(color)}"></span><span class="home-game-main">${opponentCell(game)}<span class="home-game-outcome ${escapeAttr(game.user_result || '')}">${escapeHtml(outcome)} · ${escapeHtml(color)}</span>${homeGameDetails(game)}${gameTagsCell(game)}</span></span>`;
}

function homeGameDetails(game) {
  const recent = ((dashboardData && dashboardData.recent_games) || []).find(item => Number(item.game_id) === Number(game.id));
  const date = game.played_at || (game.imported_at || '').slice(0, 10);
  if (!recent) {
    const status = game.analysis_status === 'done' ? 'Análisis completado' : 'Pendiente de análisis';
    return `<span class="home-game-details"><span>${escapeHtml(status)}</span><span>${escapeHtml(relativeGameDate(date))}</span></span>`;
  }
  const accuracy = recent.accuracy === null || typeof recent.accuracy === 'undefined'
    ? '--'
    : `${Number(recent.accuracy).toFixed(1)}%`;
  const errors = Number(recent.own_blunders || 0) + Number(recent.own_mistakes || 0) + Number(recent.own_inaccuracies || 0);
  return `<span class="home-game-details"><span>Accuracy ${escapeHtml(accuracy)} · ${escapeHtml(relativeGameDate(date))}</span><span>${errors} ${errors === 1 ? 'error propio' : 'errores propios'}</span></span>`;
}

function relativeGameDate(value) {
  if (!value) return 'Sin fecha';
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const days = Math.round((today.getTime() - date.getTime()) / 86400000);
  if (days === 0) return 'Hoy';
  if (days === 1) return 'Ayer';
  if (days > 1 && days < 7) return `Hace ${days} días`;
  return String(value).slice(0, 10);
}

function gameUserSide(game) {
  const me = (window.CHESS_COACH_USERNAME || '').trim().toLowerCase();
  if (!me) return '';
  if ((game.white_player || '').trim().toLowerCase() === me) return 'w';
  if ((game.black_player || '').trim().toLowerCase() === me) return 'b';
  return '';
}

function opponentCell(game) {
  const me = (window.CHESS_COACH_USERNAME || '').toLowerCase();
  const white = (game.white_player || '').toLowerCase();
  const opponent = white === me ? game.black_player : game.white_player;
  const opponentLabel = truncateOpponentName(opponent || 'Rival');
  const symbol = game.user_result === 'win' ? '★' : game.user_result === 'loss' ? 'x' : '=';
  const cls = game.user_result === 'win' ? 'win-dot' : game.user_result === 'loss' ? 'loss-dot' : 'draw-dot';
  return `<span class="opponent"><i class="${cls}">${symbol}</i><span title="${escapeAttr(opponent || 'Rival')}">vs. ${escapeHtml(opponentLabel)}</span></span>`;
}

function truncateOpponentName(value) {
  const characters = Array.from(String(value || ''));
  return characters.length > 15 ? `${characters.slice(0, 15).join('')}...` : characters.join('');
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
  const actions = analysisActions(game);
  return `${actions.meta} ${actions.primary} ${actions.secondary}`.trim();
}

function analysisMeta(game) {
  return `<span class="status-mini">B:${game.blunders || 0} E:${game.mistakes || 0} I:${game.inaccuracies || 0}</span>`;
}

function analysisActions(game) {
  const localBusy = analyzing.has(Number(game.id));
  const status = game.analysis_status || '';
  const gameId = Number(game.id);
  if (localBusy) return { meta: '', primary: '<button class="secondary small" disabled>Encolando...</button>', secondary: '' };
  if (status === 'queued') return { meta: '', primary: '<span class="queue-status queued">En cola</span>', secondary: '' };
  if (status === 'running') return { meta: '', primary: '<span class="queue-status running">Analizando</span>', secondary: '' };
  if (status === 'done') {
    return {
      meta: analysisMeta(game),
      primary: `<a class="btn small game-review-btn" href="review.php?id=${gameId}">Revisar</a>`,
      secondary: `<button class="secondary small" onclick="analyzeGame(${gameId}, true)">Reanalizar</button>`
    };
  }
  if (status === 'error') return { meta: '', primary: `<button class="secondary small" onclick="analyzeGame(${gameId}, true)">Reintentar</button>`, secondary: '' };
  if (status === 'cancelled') return { meta: '', primary: `<button class="secondary small" onclick="analyzeGame(${gameId}, true)">Encolar</button>`, secondary: '' };
  return { meta: '', primary: `<button class="secondary small" onclick="analyzeGame(${gameId})">Encolar</button>`, secondary: '' };
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
  const allTags = game.smart_tags || [];
  const tags = allTags.slice(0, 1);
  if (!tags.length) return '';
  const extraTags = allTags.slice(1);
  const more = extraTags.length
    ? `<button class="smart-tag more tag-toggle" type="button" aria-expanded="false" onclick="toggleGameTags(this)">+${extraTags.length}</button><span class="game-tags-extra" hidden>${extraTags.map(smartTagChip).join('')}</span>`
    : '';
  return `<div class="smart-tag-list game-tags">${tags.map(smartTagChip).join('')}${more}</div>`;
}

function toggleGameTags(button) {
  if (!button) return;
  const extra = button.parentElement ? button.parentElement.querySelector('.game-tags-extra') : null;
  if (!extra) return;
  const expanded = button.getAttribute('aria-expanded') === 'true';
  button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  extra.hidden = expanded;
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
  if (latest) latest.style.display = gamesPanelMode === 'recommended' ? '' : 'none';
  if (recommended) recommended.style.display = gamesPanelMode === 'latest' ? '' : 'none';
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
      headers: window.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
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
  return (value === null || typeof value === 'undefined' ? '' : value).toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(() => {});
initializeHomeProgressControls();
load(1).catch(error => {
  const hero = document.getElementById('trainerHeroText');
  if (hero) hero.textContent = error.message || 'No se pudo cargar el dashboard.';
});
