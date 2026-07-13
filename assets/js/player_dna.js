let playerDnaSnapshot = null;

async function loadPlayerDna() {
  const response = await fetch('api/player-dna.php?action=snapshot', { cache: 'no-store' });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.error || 'No se pudo cargar el ADN del jugador.');
  playerDnaSnapshot = payload.snapshot || null;
  renderPlayerDna(payload.period || {});
}

function renderPlayerDna(period) {
  if (!playerDnaSnapshot) {
    renderPlayerDnaEmpty(period);
    return;
  }

  const hero = document.getElementById('playerDnaHeroText');
  if (hero) hero.textContent = playerDnaSnapshot.summary_text || 'Perfil generado con tus partidas analizadas.';

  const periodEl = document.getElementById('playerDnaPeriod');
  if (periodEl) periodEl.textContent = `${Number(playerDnaSnapshot.recent_games || 0)}/${Number(period.size || playerDnaSnapshot.period_size || 10)} recientes · confianza ${confidenceLabel(playerDnaSnapshot.confidence)}`;

  renderSummary();
  renderDimensions();
  renderStrengthWeaknessList('playerDnaStrengths', playerDnaSnapshot.strengths || [], 'fortaleza');
  renderStrengthWeaknessList('playerDnaWeaknesses', playerDnaSnapshot.weaknesses || [], 'debilidad');
  renderStyle();
  renderComparisons();
  renderNextStep();
}

function renderPlayerDnaEmpty(period) {
  const hero = document.getElementById('playerDnaHeroText');
  if (hero) hero.textContent = 'Genera tu primer snapshot desde Ajustes / Mi Perfil para ver tu ADN de jugador.';

  const summary = document.getElementById('playerDnaSummary');
  if (summary) {
    summary.innerHTML = `<div class="empty-state">
      <strong>ADN del jugador pendiente</strong>
      <span>Necesito un snapshot generado para mostrar fortalezas, debilidades, estilo y evolución. El recálculo no analiza con Stockfish: usa datos ya existentes.</span>
      <a href="profile.php">Ir a procesos batch</a>
    </div>`;
  }

  ['playerDnaDimensions', 'playerDnaStrengths', 'playerDnaWeaknesses', 'playerDnaStyle', 'playerDnaComparisons', 'playerDnaNext'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.innerHTML = `<div class="empty-state compact"><strong>Sin datos todavía</strong><span>Genera el ADN desde perfil. Muestra recomendada: ${Number(period.minimum_games || 6)} partidas analizadas o más.</span></div>`;
  });
}

function renderSummary() {
  const el = document.getElementById('playerDnaSummary');
  if (!el) return;
  const rec = playerDnaSnapshot.recommendations || {};
  const primary = rec.primary || {};
  const overview = playerDnaSnapshot.overview || {};
  const improvement = overview.biggest_improvement || rec.biggest_improvement || null;
  const problem = overview.most_persistent_problem || rec.most_persistent_problem || null;

  el.innerHTML = `<div class="player-dna-summary-layout">
    <div>
      <span class="trainer-state-badge ${confidenceClass(playerDnaSnapshot.confidence)}">Confianza ${escapeHtml(confidenceLabel(playerDnaSnapshot.confidence))}</span>
      <h2>${escapeHtml(playerDnaSnapshot.profile_label || 'Perfil en construcción')}</h2>
      <p>${escapeHtml(playerDnaSnapshot.summary_text || '')}</p>
      ${primary.url ? `<a class="btn small" href="${escapeAttr(primary.url)}">${escapeHtml(primary.action_label || 'Ver foco')}</a>` : ''}
    </div>
    <div class="trainer-mini-kpis player-dna-kpis">
      ${miniKpi('Analizadas', playerDnaSnapshot.analyzed_games || 0)}
      ${miniKpi('Recientes', playerDnaSnapshot.recent_games || 0)}
      ${miniKpi('Mejora', improvement ? signedDelta(improvement.delta) : '-')}
      ${miniKpi('Problema', problem ? problem.label || problem.title || '-' : '-')}
    </div>
  </div>`;
}

function renderDimensions() {
  const el = document.getElementById('playerDnaDimensions');
  if (!el) return;
  const dimensions = playerDnaSnapshot.dimensions || [];
  if (!dimensions.length) {
    el.innerHTML = '<div class="empty-state compact"><strong>Sin dimensiones</strong><span>No hay datos suficientes para calcular el perfil.</span></div>';
    return;
  }
  el.innerHTML = dimensions.map(dimension => {
    const score = Number(dimension.score || 0);
    const scoreClass = playerDnaScoreClass(score);
    return `<article class="player-dna-dimension">
      <div>
        <strong>${escapeHtml(dimension.label || dimension.code || 'Dimensión')}</strong>
        <span>${escapeHtml(levelLabel(dimension.level))}</span>
      </div>
      <div class="player-dna-score ${scoreClass}" style="--score:${score}%"><b>${score}</b></div>
      <ul>${(dimension.evidence || []).slice(0, 2).map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
    </article>`;
  }).join('');
}

function playerDnaScoreClass(score) {
  if (score >= 70) return 'good';
  if (score >= 40) return 'warn';
  return 'bad';
}

function renderStrengthWeaknessList(id, items, kind) {
  const el = document.getElementById(id);
  if (!el) return;
  if (!items.length) {
    el.innerHTML = `<div class="empty-state compact"><strong>Sin ${escapeHtml(kind)} clara</strong><span>La muestra actual todavía no separa patrones con seguridad.</span></div>`;
    return;
  }
  el.innerHTML = items.slice(0, 3).map(item => `<article class="player-dna-list-item">
    <span>${kind === 'fortaleza' ? '↗' : '!'}</span>
    <div>
      <strong>${escapeHtml(item.title || item.label || item.code || '')}</strong>
      <small>${escapeHtml(item.evidence || '')}</small>
    </div>
    <b>${Number(item.score || 0)}</b>
  </article>`).join('');
}

function renderStyle() {
  const el = document.getElementById('playerDnaStyle');
  if (!el) return;
  const items = playerDnaSnapshot.style || [];
  if (!items.length) {
    el.innerHTML = '<div class="empty-state compact"><strong>Sin indicadores</strong><span>Faltan datos para interpretar estilo.</span></div>';
    return;
  }
  el.innerHTML = items.map(item => {
    const value = Number(item.value || 0);
    return `<article class="player-dna-style-row">
      <div>
        <strong>${escapeHtml(item.left || '')}</strong>
        <strong>${escapeHtml(item.right || '')}</strong>
      </div>
      <div class="player-dna-style-bar"><span style="left:${value}%"></span></div>
      <p>${escapeHtml(item.summary || '')}</p>
    </article>`;
  }).join('');
}

function renderComparisons() {
  const el = document.getElementById('playerDnaComparisons');
  if (!el) return;
  const items = Object.values(playerDnaSnapshot.comparisons || {});
  if (!items.length) {
    el.innerHTML = '<div class="empty-state compact"><strong>Sin comparativas</strong><span>Necesito más partidas para comparar periodos.</span></div>';
    return;
  }
  el.innerHTML = items.map(item => `<article class="player-dna-comparison">
    <strong>${escapeHtml(item.label || 'Comparativa')}</strong>
    <div>
      ${comparisonMetric('Accuracy', item.accuracy_delta)}
      ${comparisonMetric('Score', item.score_delta)}
      ${typeof item.acpl_delta !== 'undefined' ? comparisonMetric('ACPL', item.acpl_delta) : ''}
    </div>
  </article>`).join('');
}

function renderNextStep() {
  const el = document.getElementById('playerDnaNext');
  if (!el) return;
  const primary = (playerDnaSnapshot.recommendations || {}).primary || {};
  const target = primary.source === 'tag'
    ? `<span class="smart-tag ${smartTagClass(primary)}">${escapeHtml(primary.title || primary.code || 'Objetivo')}</span>`
    : `<span>Objetivo</span>`;
  el.innerHTML = `<div class="player-dna-next-card">
    ${target}
    <h3>${escapeHtml(primary.title || 'Mantener consistencia')}</h3>
    <p>${escapeHtml(primary.text || 'Revisa tus partidas recientes y vuelve a generar el ADN cuando tengas más análisis.')}</p>
    ${primary.url ? `<a class="btn" href="${escapeAttr(primary.url)}">${escapeHtml(primary.action_label || 'Abrir')}</a>` : '<a class="btn secondary" href="profile.php">Recalcular ADN</a>'}
  </div>`;
}

function smartTagClass(tag) {
  const severity = tag && tag.severity ? tag.severity : 'info';
  const category = tag && tag.category ? tag.category : '';
  if (category === 'positive') return 'positive';
  return ['critical', 'high', 'medium', 'low', 'info'].includes(severity) ? severity : 'info';
}

function miniKpi(label, value) {
  return `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
}

function comparisonMetric(label, delta) {
  const value = delta === null || typeof delta === 'undefined' ? '-' : signedDelta(delta);
  const cls = Number(delta || 0) > 0 ? 'up' : (Number(delta || 0) < 0 ? 'down' : '');
  return `<span class="${cls}"><small>${escapeHtml(label)}</small><b>${escapeHtml(value)}</b></span>`;
}

function signedDelta(value) {
  if (value === null || typeof value === 'undefined' || value === '') return '-';
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${number > 0 ? '+' : ''}${number}`;
}

function confidenceLabel(value) {
  return { low: 'baja', medium: 'media', high: 'alta' }[value] || value || 'baja';
}

function confidenceClass(value) {
  return value === 'high' ? 'good' : (value === 'medium' ? 'improving' : 'insufficient');
}

function levelLabel(value) {
  return {
    fortaleza: 'Fortaleza',
    estable: 'Estable',
    mejorable: 'Mejorable',
    prioridad: 'Prioridad'
  }[value] || value || '';
}

function escapeHtml(value) {
  return (value === null || typeof value === 'undefined' ? '' : value).toString().replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(() => {});
loadPlayerDna().catch(error => {
  const hero = document.getElementById('playerDnaHeroText');
  if (hero) hero.textContent = error.message || 'No se pudo cargar el ADN del jugador.';
});
