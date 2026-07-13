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
        <span><b>${Number(opening.games || 0)}</b><small>partidas</small></span>
        <span><b>${Number(opening.score_rate || 0)}%</b><small>score</small></span>
        <span><b>${escapeHtml(accuracy)}</b><small>accuracy</small></span>
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
  renderOpeningDetail(payload.opening || {}, payload.games || [], payload.recommended_games || [], payload.games_url || '', {
    frequentTags: payload.frequent_tags || [],
    earlyErrorPatterns: payload.early_error_patterns || [],
    relatedExercises: payload.related_exercises || [],
  });
}

function renderOpeningDetail(opening, games, recommendedGames, gamesUrl, connections = {}) {
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
    <strong>Principio recomendado</strong>
    ${renderOpeningPrinciple(opening.recommended_principle)}
    <strong>Recomendación</strong>
    <p>${escapeHtml(opening.recommendation || 'Mantén esta apertura en observación.')}</p>
    ${moves ? `<strong>Primeras jugadas</strong><p>${escapeHtml(moves)}</p>` : ''}
  </div>
  <div class="opening-connections">
    ${renderFrequentTags(connections.frequentTags || [])}
    ${renderEarlyPatterns(connections.earlyErrorPatterns || [])}
    ${renderRelatedExercises(connections.relatedExercises || [])}
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

function renderOpeningPrinciple(principle) {
  if (!principle) return '<p>Revisa desarrollo, rey seguro y control del centro antes de memorizar variantes.</p>';
  const checklist = (principle.checklist || []).slice(0, 3);
  return `<div class="opening-principle">
    <b>${escapeHtml(principle.title || 'Principio de apertura')}</b>
    <p>${escapeHtml(principle.summary || '')}</p>
    ${checklist.length ? `<ul>${checklist.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>` : ''}
  </div>`;
}

function renderFrequentTags(tags) {
  return `<section class="opening-connection-card">
    <h3>Tags frecuentes</h3>
    ${tags.length ? `<div class="smart-tag-summary opening-tag-summary">
      ${tags.slice(0, 5).map(tag => `<div><span>${smartTagChip(tag)}</span><strong>${Number(tag.total || 0)}</strong></div>`).join('')}
    </div>` : `<div class="empty-state compact"><strong>Sin tags frecuentes.</strong><span>Ejecuta Smart Tags o analiza mas partidas para detectar patrones.</span></div>`}
  </section>`;
}

function renderEarlyPatterns(patterns) {
  return `<section class="opening-connection-card">
    <h3>Errores tempranos recurrentes</h3>
    ${patterns.length ? `<div class="opening-pattern-list">
      ${patterns.slice(0, 5).map(pattern => `<article>
        <div>
          <strong>${classificationLabel(pattern.classification)} en ply ${Number(pattern.ply || 0)}</strong>
          <span>${escapeHtml(pattern.sample_san || pattern.sample_uci || 'Jugada de apertura')} · ${Number(pattern.count || 0)} vez/veces</span>
        </div>
        <a class="btn secondary small" href="${escapeAttr(pattern.review_url || '#')}">Ver ejemplo</a>
      </article>`).join('')}
    </div>` : `<div class="empty-state compact"><strong>Sin errores repetidos.</strong><span>No aparece un patron temprano claro en esta apertura.</span></div>`}
  </section>`;
}

function renderRelatedExercises(exercises) {
  return `<section class="opening-connection-card">
    <h3>Ejercicios relacionados</h3>
    ${exercises.length ? `<div class="opening-exercise-list">
      ${exercises.slice(0, 6).map(exercise => `<article>
        <div>
          <strong>${escapeHtml(exercise.type_label || 'Ejercicio')}</strong>
          <span>${escapeHtml(exercise.title || 'Partida')} · ply ${Number(exercise.ply || 0)} · ${exercise.resolved ? 'resuelto' : 'pendiente'}</span>
        </div>
        <div class="opening-exercise-actions">
          <a class="btn small action-review" href="${escapeAttr(exercise.review_url || '#')}">Revisar</a>
          <a class="btn small action-train" href="${escapeAttr(exercise.training_url || 'training.php')}">Entrenar</a>
        </div>
      </article>`).join('')}
    </div>` : `<div class="empty-state compact"><strong>Sin ejercicios existentes.</strong><span>No hay ejercicios ya generados antes de la ply 16 para partidas con el mismo ECO. No se crearan nuevos desde el Lab.</span></div>`}
  </section>`;
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
  return `<span class="smart-tag ${smartTagClass(tag)}" title="${escapeHtml(code)}">${escapeHtml(label)}</span>`;
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
      <a class="btn small action-review" href="${escapeAttr(reviewUrl)}">${recommended ? 'Revisar foco' : 'Revisar'}</a>
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
