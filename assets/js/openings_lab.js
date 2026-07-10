let openings = [];
let openingsSummary = {};
let selectedOpeningKey = '';

function selectedMinGames() {
  return Number(document.getElementById('openingsMinGames')?.value || 3);
}

async function loadOpenings() {
  const params = new URLSearchParams({
    action: 'dashboard',
    min_games: String(selectedMinGames()),
    limit: '80',
  });
  const response = await fetch(`api/openings.php?${params.toString()}`, { cache: 'no-store' });
  const payload = await response.json();
  openings = payload.openings || [];
  openingsSummary = payload.summary || {};
  renderOpeningsSummary();
  renderOpeningsList();
  renderOpeningsStatus(payload.filters || {});

  if (selectedOpeningKey && openings.some(item => item.opening_key === selectedOpeningKey)) {
    loadOpeningDetail(selectedOpeningKey);
  } else if (openings.length) {
    loadOpeningDetail(openings[0].opening_key);
  } else {
    renderEmptyDetail();
  }
}

function renderOpeningsSummary() {
  const el = document.getElementById('openingsSummary');
  if (!el) return;
  const best = openingsSummary.best_opening;
  const issue = openingsSummary.main_issue_opening;
  el.innerHTML = [
    metricCard('♙', 'Aperturas', openingsSummary.total_openings || 0, `${openingsSummary.total_profiled_games || 0} partidas perfiladas`),
    metricCard('✓', 'Con muestra fiable', openingsSummary.openings_with_minimum_games || 0, `mínimo ${openingsSummary.minimum_games || 3} partidas`),
    metricCard('★', 'Mejor apertura', best ? `${best.score_rate}%` : '-', best ? best.display_name : 'sin datos suficientes', 'star'),
    metricCard('!', 'Atención', issue ? issue.opening_error_count : 0, issue ? issue.display_name : 'sin patrón claro', 'clock'),
  ].join('');
}

function metricCard(icon, label, value, detail, extraClass = '') {
  return `<article class="metric-card ${extraClass}">
    <div class="metric-icon">${escapeHtml(icon)}</div>
    <div>
      <span>${escapeHtml(label)}</span>
      <b>${escapeHtml(value)}</b>
      <small>${escapeHtml(detail)}</small>
    </div>
  </article>`;
}

function renderOpeningsStatus(filters) {
  const status = document.getElementById('openingsStatus');
  const count = document.getElementById('openingsCount');
  const pending = Number(openingsSummary.pending_profiles || 0);
  if (status) {
    status.textContent = pending > 0
      ? `${pending} partidas pendientes de perfilar`
      : 'Perfiles de apertura al día';
  }
  if (count) {
    const minGames = filters.minimum_games || selectedMinGames();
    count.textContent = `${openings.length} apertura${openings.length === 1 ? '' : 's'} con ${minGames}+ partida${minGames === 1 ? '' : 's'}`;
  }
}

function renderOpeningsList() {
  const el = document.getElementById('openingsList');
  if (!el) return;
  if (!openings.length) {
    el.innerHTML = `<div class="empty-state">
      <strong>No hay aperturas para esta vista.</strong>
      <span>Analiza partidas o baja el mínimo de partidas para ver agrupaciones con menos muestra.</span>
    </div>`;
    return;
  }

  el.innerHTML = openings.map(opening => {
    const active = opening.opening_key === selectedOpeningKey ? ' active' : '';
    const issue = opening.common_issue ? `<span>${escapeHtml(opening.common_issue.label)}: ${Number(opening.common_issue.count || 0)}</span>` : '<span>Sin error recurrente claro</span>';
    const accuracy = opening.avg_opening_accuracy === null ? '-' : `${opening.avg_opening_accuracy}%`;
    const ecoUrl = safeUrl(opening.eco_url || '');
    const eco = ecoUrl && opening.eco_code
      ? `<a href="${escapeAttr(ecoUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(opening.eco_code)}</a>`
      : escapeHtml(opening.eco_code || '');
    return `<button class="opening-card${active}" type="button" data-opening-key="${escapeAttr(opening.opening_key || '')}">
      <span class="opening-card-title">
        <strong>${escapeHtml(opening.display_name || 'Apertura no identificada')}</strong>
        ${eco ? `<small>${eco}</small>` : ''}
      </span>
      <span class="opening-card-kpis">
        <b>${Number(opening.games || 0)}</b><small>partidas</small>
        <b>${Number(opening.score_rate || 0)}%</b><small>score</small>
        <b>${escapeHtml(accuracy)}</b><small>accuracy</small>
      </span>
      <span class="opening-card-note">${issue}</span>
    </button>`;
  }).join('');
  el.querySelectorAll('[data-opening-key]').forEach(button => {
    button.addEventListener('click', () => loadOpeningDetail(button.dataset.openingKey || ''));
  });
}

async function loadOpeningDetail(openingKey) {
  selectedOpeningKey = openingKey || '';
  renderOpeningsList();
  const detail = document.getElementById('openingDetail');
  if (detail) detail.innerHTML = `<div class="empty-state compact"><strong>Cargando apertura...</strong><span>Preparando métricas y partidas ejemplo.</span></div>`;

  const params = new URLSearchParams({ action: 'detail', opening_key: selectedOpeningKey });
  const response = await fetch(`api/openings.php?${params.toString()}`, { cache: 'no-store' });
  const payload = await response.json();
  if (!payload.ok) {
    if (detail) detail.innerHTML = `<div class="empty-state compact"><strong>No se pudo cargar.</strong><span>${escapeHtml(payload.error || 'Inténtalo de nuevo.')}</span></div>`;
    return;
  }
  renderOpeningDetail(payload.opening || {}, payload.games || [], payload.recommended_games || [], payload.games_url || '');
}

function renderOpeningDetail(opening, games, recommendedGames, gamesUrl) {
  const el = document.getElementById('openingDetail');
  if (!el) return;
  const issue = opening.common_issue ? `${opening.common_issue.label}: ${opening.common_issue.count}` : 'Sin error recurrente claro';
  const eval10 = opening.avg_eval_after_move_10 === null ? '-' : cpText(opening.avg_eval_after_move_10);
  const moves = (opening.first_moves_san || []).slice(0, 8).join(' ');
  const ecoUrl = safeUrl(opening.eco_url || '');
  const eco = ecoUrl && opening.eco_code
    ? `<a href="${escapeAttr(ecoUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(opening.eco_code)}</a>`
    : escapeHtml(opening.eco_code || '');
  const filteredGamesUrl = safeLocalUrl(gamesUrl) || `games.php?opening_key=${encodeURIComponent(opening.opening_key || '')}`;

  el.innerHTML = `<div class="opening-detail-head">
    <div>
      <h3>${escapeHtml(opening.display_name || 'Apertura no identificada')}</h3>
      ${eco ? `<p class="muted">ECO ${eco}</p>` : ''}
    </div>
    <a class="btn secondary small" href="${escapeAttr(filteredGamesUrl)}">Ver partidas</a>
  </div>
  <div class="trainer-mini-kpis opening-detail-kpis">
    ${miniKpi('Score', `${Number(opening.score_rate || 0)}%`)}
    ${miniKpi('Accuracy', opening.avg_opening_accuracy === null ? '-' : `${opening.avg_opening_accuracy}%`)}
    ${miniKpi('ACPL', opening.avg_opening_acpl === null ? '-' : opening.avg_opening_acpl)}
    ${miniKpi('Eval mov. 10', eval10)}
  </div>
  <div class="opening-guidance">
    <strong>Diagnóstico</strong>
    <p>${escapeHtml(issue)}</p>
    <strong>Recomendación</strong>
    <p>${escapeHtml(opening.recommendation || 'Mantén esta apertura en observación.')}</p>
    ${moves ? `<strong>Primeras jugadas</strong><p>${escapeHtml(moves)}</p>` : ''}
  </div>
  <div class="opening-games">
    <h3>Partidas recomendadas para revisar</h3>
    ${renderOpeningGames(recommendedGames, true)}
  </div>
  <div class="opening-games">
    <h3>Partidas ejemplo</h3>
    ${renderOpeningGames(games, false)}
  </div>`;
}

function renderOpeningGames(games, recommended = false) {
  if (!games.length) {
    const text = recommended
      ? 'No hay errores tempranos claros en esta apertura.'
      : 'Cuando haya perfiles suficientes aparecerán aquí.';
    return `<div class="empty-state compact"><strong>Sin partidas asociadas.</strong><span>${escapeHtml(text)}</span></div>`;
  }
  return games.slice(0, 8).map(game => {
    const accuracy = game.opening_accuracy === null ? '-' : `${game.opening_accuracy}%`;
    const reviewUrl = recommended ? (game.review_focus_url || game.review_url || '#') : (game.review_url || '#');
    const reason = recommendedReason(game);
    return `<article class="opening-game-row">
      <div>
        <strong>${escapeHtml(game.title || 'Partida')}</strong>
        <span>${escapeHtml(game.played_at || '-')} · ${resultLabel(game.result)} · ${colorLabel(game.user_color)}</span>
        ${recommended ? `<span>${escapeHtml(reason)}</span>` : ''}
      </div>
      <div>
        <b>${escapeHtml(accuracy)}</b>
        <small>B:${Number(game.opening_errors?.blunders || 0)} E:${Number(game.opening_errors?.mistakes || 0)} I:${Number(game.opening_errors?.inaccuracies || 0)}</small>
      </div>
      <a class="btn secondary small" href="${escapeAttr(reviewUrl)}">${recommended ? 'Revisar foco' : 'Revisar'}</a>
    </article>`;
  }).join('');
}

function recommendedReason(game) {
  const label = classificationLabel(game.first_error_label || '');
  if (game.first_error_ply) return `${label} en la jugada ${game.first_error_ply}.`;
  return `${Number(game.opening_errors?.total || 0)} error(es) tempranos.`;
}

function classificationLabel(value) {
  return { blunder: 'Omisión grave', mistake: 'Error', inaccuracy: 'Imprecisión' }[value] || 'Error temprano';
}

function colorLabel(value) {
  return { white: 'blancas', black: 'negras', unknown: 'color desconocido' }[value] || 'color desconocido';
}

function miniKpi(label, value) {
  return `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
}

function renderEmptyDetail() {
  const el = document.getElementById('openingDetail');
  if (!el) return;
  el.innerHTML = `<div class="empty-state compact">
    <strong>No hay apertura seleccionada.</strong>
    <span>Cuando haya partidas perfiladas, podrás revisar aquí los patrones por apertura.</span>
  </div>`;
}

function cpText(cp) {
  const value = Number(cp || 0) / 100;
  return `${value > 0 ? '+' : ''}${value.toFixed(2)}`;
}

function resultLabel(result) {
  return { win: 'victoria', loss: 'derrota', draw: 'tablas', unknown: 'resultado desconocido' }[result] || 'resultado desconocido';
}

function escapeHtml(value) {
  return (value ?? '').toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value);
}

function safeUrl(value) {
  const url = (value || '').toString().trim();
  return /^https?:\/\//i.test(url) ? url : '';
}

function safeLocalUrl(value) {
  const url = (value || '').toString().trim();
  return /^[a-z0-9._-]+\.php(\?[^#]*)?$/i.test(url) ? url : '';
}

document.getElementById('openingsMinGames')?.addEventListener('change', () => {
  selectedOpeningKey = '';
  loadOpenings();
});

loadOpenings().catch(() => {
  const el = document.getElementById('openingsList');
  if (el) {
    el.innerHTML = `<div class="empty-state"><strong>No se pudo cargar el Lab de Aperturas.</strong><span>Comprueba que la migración de v1.2.0 esté aplicada.</span></div>`;
  }
});
