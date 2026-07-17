let games = [];
let dashboardData = null;
let playerDnaData = null;
let latestReviewData = null;
let playerProgressData = null;
let trainingPlanData = null;
let trainingDashboardExtrasLoaded = false;
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
  latestReviewData = await loadLatestReview();

  render();
  schedulePollingIfNeeded();
}

function render() {
  renderStats();
  renderTrainerDashboard();
  renderHomePlayerDna();
  renderRows();
  renderPagination();
  renderPatterns();
  renderLatestReview();
  updateGamesPanelTabs();
}

async function loadLatestReview() {
  const latestDone = games.find(game => game.analysis_status === 'done');
  if (!latestDone || !latestDone.id) return null;
  try {
    return await dashboardGet(`api/review.php?id=${Number(latestDone.id)}`);
  } catch (error) {
    console.warn(error);
    return null;
  }
}

function renderStats() {
  const el = document.getElementById('stats');
  if (!el) return;
  const global = stats.global || { total: 0, wins: 0, losses: 0, draws: 0 };
  const accuracy = stats.analysis_accuracy || { average: null, analyzed_games: 0 };
  const overview = (dashboardData && dashboardData.overview) || {};
  const previous = (dashboardData && dashboardData.previous_period) || {};
  const queue = (dashboardData && dashboardData.queue) || stats.queue || {};
  const winRate = global.total ? Math.round((global.wins || 0) * 100 / global.total) : 0;
  const pending = typeof queue.pending_total !== 'undefined' ? queue.pending_total : ((stats.queue && typeof stats.queue.pending_total !== 'undefined') ? stats.queue.pending_total : 0);
  const analyzedGames = Number(accuracy.analyzed_games || 0);
  const avgAccuracy = accuracy.average === null || typeof accuracy.average === 'undefined' ? null : Number(accuracy.average);
  const trends = dashboardMetricTrends(overview, previous, queue, { winRate, avgAccuracy });
  const cards = [
    { kind: 'pulse', label: 'Partidas', value: global.total || 0, detail: 'Ver todas', href: 'games.php', trend: trends.games, trendLabel: trendDeltaLabel(trendDeltaFromValues(trends.games), 'partidas') },
    { kind: 'target', label: 'Win Rate', value: `${winRate}%`, detail: `${global.wins || 0} victorias / ${global.total || 0}`, trend: trends.winRate, trendLabel: trendDeltaLabel(trendDeltaFromValues(trends.winRate), 'puntos') },
    { kind: 'star', label: 'Accuracy media', value: avgAccuracy === null ? '--' : `${avgAccuracy.toFixed(1)}%`, detail: analyzedGames ? `${analyzedGames} partidas analizadas` : 'sin partidas analizadas', trend: trends.accuracy, trendLabel: trendDeltaLabel(trendDeltaFromValues(trends.accuracy), 'puntos') },
    { kind: 'clock', label: 'Pendientes de análisis', value: pending, detail: 'Ver cola', href: 'analysis-pending.php', trend: trends.pending, trendLabel: trendDeltaLabel(trendDeltaFromValues(trends.pending), 'pendientes') }
  ];
  el.innerHTML = cards.map(card => {
    const detail = card.href
      ? `<a href="${escapeAttr(card.href)}">${escapeHtml(card.detail)}</a>`
      : escapeHtml(card.detail);
    return `<article class="metric-card ${card.kind}">
      <div class="metric-card-top">
        <div class="metric-icon">${iconFor(card.kind)}</div>
        <div><span>${escapeHtml(card.label)}</span><b>${escapeHtml(card.value)}</b></div>
      </div>
      ${sparklineSvg(card.trend || [], card.kind)}
      <small>${detail}</small>
      <em class="${trendLabelClass(card.trendLabel)}">${escapeHtml(card.trendLabel || '')}</em>
    </article>`;
  }).join('');
}

function iconFor(kind) {
  return kind === 'pulse' ? '⌁' : kind === 'target' ? '◎' : kind === 'star' ? '★' : '▷';
}

function dashboardMetricTrends(overview, previous, queue, anchors = {}) {
  const recentGames = ((dashboardData && dashboardData.recent_games) || []).slice().reverse();
  const gamesTrend = recentGames.length ? recentGames.map((_, index) => index + 1) : [0, 0, 0, 0, 0, 0];
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
  const pending = Number(queue.pending_total || 0);
  const pendingTrend = pending ? [Math.max(0, pending - 2), Math.max(0, pending - 1), pending, Math.max(0, pending - 1), pending] : [0, 0, 0, 0, 0];
  return {
    games: gamesTrend,
    winRate: winRateTrend,
    accuracy: accuracyTrend.length ? accuracyTrend : [0, 0, 0, 0, 0],
    pending: pendingTrend,
  };
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
  renderSummary();
  renderHomeTrainingExperience();
  renderStrengths();
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
  el.textContent = 'Cada partida es una oportunidad para mejorar.';
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
  const el = document.getElementById('homeTrainingExperience');
  if (!el) return;
  const experience = (dashboardData && dashboardData.training_experience) || {};
  const settings = experience.settings || {};
  const today = experience.today || {};
  const week = experience.week || {};
  const streak = experience.streak || {};
  const repeatQueue = experience.repeat_queue || {};
  const progress = playerProgressData || {};
  const autonomy = progress.autonomy || {};
  const plan = trainingPlanData || { daily: [], weekly: [] };
  const streakDays = Number(streak.days || 0);
  const dueCount = Number(repeatQueue.due_count || 0);
  el.innerHTML = `
    <div class="home-training-head">
      <div>
        <span class="trainer-state-badge ${today.goal_met ? 'good' : (today.trained ? 'improving' : 'stable')}">Plan personal</span>
        <h2>Tu progreso y próximos pasos</h2>
        <p>${escapeHtml(homeTrainingMessage(today, streak))}</p>
      </div>
      <a class="btn secondary small" href="training.php">Entrenar ahora</a>
    </div>
    <div class="home-training-grid">
      ${homeTrainingCard('racha', 'Racha', `${streakDays} día(s)`, streak.today_goal_met ? 'objetivo cumplido hoy' : 'objetivo diario pendiente', streakDays ? Math.min(100, streakDays * 20) : 0)}
      ${homeTrainingCard('hoy', 'Hoy', homeTrainingTodayText(today, settings), homeTrainingGoalLabel(settings), homeTrainingProgressPercent(homeTrainingTodayProgress(today, settings)))}
      ${homeTrainingCard('semana', 'Semana', homeTrainingWeekText(week, settings), 'progreso semanal', homeTrainingProgressPercent(homeTrainingWeekProgress(week, settings)))}
      ${homeTrainingCard('repasos', 'Para repetir', dueCount ? `${dueCount}` : 'Al día', dueCount === 1 ? 'ejercicio vencido' : (dueCount > 1 ? 'ejercicios vencidos' : 'sin repeticiones vencidas'), dueCount ? 0 : 100)}
    </div>
    <div class="training-plan-overview">
      ${homeProgressMetric('Progress Score', progress.available ? `${Number(progress.score || 0)}/1000` : '--', progress.available ? 'rendimiento reciente' : 'calculando progreso', progress.available ? Number(progress.score || 0) / 10 : 0)}
      ${homeProgressMetric('Autonomía', autonomy.score === null || typeof autonomy.score === 'undefined' ? '--' : `${Math.round(Number(autonomy.score))}%`, autonomy.calibrated ? 'resolución sin ayudas' : `calibrando ${Number(autonomy.samples || 0)}/${Number(autonomy.minimum_samples || 6)}`, autonomy.score || 0)}
    </div>
    <div class="training-plan-columns">
      ${homePlanColumn('Hoy', plan.daily || [])}
      ${homePlanColumn('Esta semana', plan.weekly || [])}
    </div>
  `;
}

function homeProgressMetric(label, value, detail, percent) {
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
  return `<tr><td>${rivalCell(game)}</td><td>${resultBadge(game)}</td><td>${escapeHtml(game.event_name || rhythmFromSite(game.site) || '-')}</td><td class="hide-sm">${game.played_at || (game.imported_at || '').slice(0,10) || '-'}</td><td>${actions.meta}</td><td>${actions.primary}</td><td>${actions.secondary}</td></tr>`;
}

function recommendedRow(item) {
  return `
    <tr>
      <td><a class="game-title-link" href="${escapeAttr(item.review_url || '#')}"><strong>${escapeHtml(item.title || 'Partida')}</strong></a><small class="recommend-reason">${escapeHtml(item.reason || '')}</small></td>
      <td>${resultBadge(item)}</td>
      <td>${item.accuracy === null || typeof item.accuracy === 'undefined' ? '--' : `${Number(item.accuracy).toFixed(1)}%`}</td>
      <td class="hide-sm">${escapeHtml(item.played_at || '-')}</td>
      <td>${analysisMeta(item)}</td>
      <td><a class="btn small game-review-btn" href="${escapeAttr(item.review_url || '#')}">Revisar</a></td>
      <td></td>
    </tr>
  `;
}

function rivalCell(game) {
  return `<span class="rival-line">${opponentCell(game)}${gameTagsCell(game)}</span>`;
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

function renderLatestReview() {
  const card = document.getElementById('latestReviewCard');
  const countsCard = document.getElementById('latestReviewCountsCard');
  if (!card || !countsCard) return;
  if (!latestReviewData || !latestReviewData.ok) {
    const empty = `
      <div class="empty-state compact">
        <strong>Sin revisiones todavía.</strong>
        <span>Analiza una partida para ver aquí el resumen de la última revisión.</span>
        <a href="analysis-pending.php">Ver cola de análisis</a>
      </div>
    `;
    card.innerHTML = `<h2>Revisión de última partida</h2>${empty}`;
    countsCard.innerHTML = `<h2>Resumen</h2>${empty}`;
    return;
  }
  const game = latestReviewData.game || {};
  const summary = latestReviewData.summary || {};
  const gameId = Number(game.id || 0);
  const tags = (summary.smart_tags || []).slice(0, 2);
  card.innerHTML = `
    <div class="home-review-head">
      <div>
        <h2>Revisión de última partida</h2>
        <p>${escapeHtml(latestReviewMeta(game))}</p>
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
      <div><span>Accuracy</span><b>${formatReviewNumber(summary.accuracy)}</b></div>
      <div><span>ACPL</span><b>${formatReviewNumber(summary.acpl)}</b></div>
      <div><span>Jugadas</span><b>${Number(summary.moves || 0)}</b></div>
    </div>
  `;
  const labels = [
    ['best', 'Mejor'],
    ['excellent', 'Excelente'],
    ['good', 'Buena'],
    ['inaccuracy', 'Imprecisión'],
    ['mistake', 'Error'],
    ['blunder', 'Omisión grave']
  ];
  const counts = summary.counts || {};
  countsCard.innerHTML = `
    <h2>Resumen</h2>
    <div class="review-counts home-review-counts">
      ${labels.map(([key, label]) => `
        <div class="review-count ${key}">
          <span>${homeBucketIcon(key)}</span>
          <strong>${Number(counts[key] || 0)}</strong>
          <small>${escapeHtml(label)}</small>
        </div>
      `).join('')}
    </div>
  `;
}

function latestReviewMeta(game) {
  const white = game.white_player || 'Blancas';
  const black = game.black_player || 'Negras';
  const result = game.result_raw || '-';
  const date = game.played_at || (game.imported_at || '').slice(0, 10) || '-';
  return `${white} vs ${black} • ${result} • ${date}`;
}

function formatReviewNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num.toFixed(1).replace(/\.0$/, '') : '--';
}

function homeBucketIcon(bucket) {
  if (bucket === 'best') return '★';
  if (bucket === 'excellent') return '↑';
  if (bucket === 'good') return '✓';
  if (bucket === 'inaccuracy') return '?!';
  if (bucket === 'mistake') return '?';
  if (bucket === 'blunder') return '??';
  return '•';
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
load(1).catch(error => {
  const hero = document.getElementById('trainerHeroText');
  if (hero) hero.textContent = error.message || 'No se pudo cargar el dashboard.';
});
