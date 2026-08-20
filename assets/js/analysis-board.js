const ANALYSIS_START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
const analysisCoordinator = new ChessInteractive.RequestCoordinator();
let analysisTree = null;
let analysisOrientation = 'white';
let analysisSelected = '';
let analysisMoves = [];

function analysisCurrent() { return analysisTree?.current(); }
function analysisSetStatus(text, error = false) {
  const el = document.getElementById('analysisStatus');
  if (el) { el.textContent = text; el.classList.toggle('error-text', error); }
}
function analysisEvalText(result) {
  if (!result || result.score == null) return '--';
  if (result.score_type === 'mate') return `${Number(result.score) >= 0 ? '' : '-'}M${Math.abs(Number(result.score))}`;
  return `${Number(result.score) > 0 ? '+' : ''}${(Number(result.score) / 100).toFixed(2)}`;
}
function analysisEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
}
function renderAnalysisEngine(analysis, live = false) {
  if (!analysis) return;
  const evaluation = analysisEvalText(analysis);
  document.getElementById('analysisEval').textContent = evaluation;
  document.getElementById('analysisLiveEval').textContent = evaluation;
  document.getElementById('analysisBest').textContent = analysis.bestmove_san || '--';
  const pv = (analysis.pv_moves || []).map(move => move.san).join(' ') || 'Calculando línea...';
  document.getElementById('analysisPv').textContent = pv;
  document.getElementById('analysisLiveDepth').textContent = analysis.depth
    ? `Profundidad ${analysis.depth}${analysis.nodes ? ` · ${Number(analysis.nodes).toLocaleString('es-ES')} nodos` : ''}`
    : 'Preparando búsqueda';
  const lines = document.getElementById('analysisLiveLines');
  if (lines) lines.innerHTML = `<div><b>${analysisEscape(evaluation)}</b><span>${analysisEscape(pv)}</span></div>`;
  if (live) analysisSetStatus(`Analizando · profundidad ${analysis.depth || '...'}`);
}
async function analysisRefresh(runEngine = true) {
  const node = analysisCurrent();
  if (!node) return;
  document.getElementById('analysisFen').value = node.fen;
  analysisSelected = '';
  try {
    const movesData = await ChessInteractive.api({ action: 'moves', fen: node.fen });
    if (node !== analysisCurrent()) return;
    analysisMoves = movesData.moves || [];
  } catch (error) { analysisMoves = []; analysisSetStatus(error.message, true); }
  renderAnalysisBoard();
  renderAnalysisHistory();
  if (!runEngine) return;
  analysisSetStatus('Analizando posición...');
  let data = null;
  try {
    data = await analysisCoordinator.run(signal => ChessInteractive.streamAnalysis(node.fen, event => {
      if (node !== analysisCurrent() || !event.analysis) return;
      node.analysis = event.analysis;
      renderAnalysisEngine(event.analysis, event.type === 'info');
    }, signal));
  } catch (error) {
    analysisSetStatus(error.message, true);
    return;
  }
  if (!data || node !== analysisCurrent()) return;
  node.analysis = data.analysis;
  renderAnalysisEngine(data.analysis);
  renderAnalysisHistory();
  analysisSetStatus(data.analysis.source === 'cache' ? 'Resultado reutilizado' : 'Análisis actualizado');
}
function renderAnalysisBoard() {
  const targets = analysisSelected ? analysisMoves.filter(move => move.uci.startsWith(analysisSelected)).map(move => move.uci.slice(2,4)) : [];
  ChessInteractive.renderBoard(document.getElementById('analysisBoard'), analysisCurrent()?.fen, {
    orientation: analysisOrientation, selected: analysisSelected, targets,
    lastMove: analysisCurrent()?.uci || '', onSquare: analysisSquare,
  });
}
async function analysisSquare(square) {
  if (!analysisSelected) {
    if (!analysisMoves.some(move => move.uci.startsWith(square))) return;
    analysisSelected = square; renderAnalysisBoard(); return;
  }
  if (square === analysisSelected) { analysisSelected = ''; renderAnalysisBoard(); return; }
  const candidates = analysisMoves.filter(move => move.uci.startsWith(analysisSelected + square));
  if (!candidates.length) {
    analysisSelected = analysisMoves.some(move => move.uci.startsWith(square)) ? square : '';
    renderAnalysisBoard(); return;
  }
  const chosen = candidates.find(move => !move.uci[4] || move.uci[4] === 'q') || candidates[0];
  analysisSetStatus('Aplicando jugada...');
  try {
    const data = await ChessInteractive.api({ action: 'apply', fen: analysisCurrent().fen, move_uci: chosen.uci });
    analysisTree.play(data.fen_after, data.move_uci, data.move_san);
    await analysisRefresh();
  } catch (error) { analysisSetStatus(error.message, true); }
}
function renderAnalysisHistory() {
  const line = analysisTree?.line() || [];
  document.getElementById('analysisHistory').innerHTML = line.map((node, index) => `<button type="button" data-analysis-node="${node.id}" class="${node.id === analysisTree.currentId ? 'active' : ''}">${index ? analysisEscape(node.san || node.uci) : 'Inicio'}</button>`).join('');
  document.getElementById('analysisBack').disabled = !analysisCurrent()?.parentId;
  document.getElementById('analysisForward').disabled = !(analysisCurrent()?.children || []).length;
  document.getElementById('analysisBranches').innerHTML = analysisTreeHtml(analysisTree.rootId);
}
function analysisTreeHtml(parentId, depth = 0) {
  const parent = analysisTree.nodes.get(parentId);
  if (!parent?.children?.length) return depth === 0 ? '<small>Añade jugadas sobre el tablero para crear ramas.</small>' : '';
  return `<ol>${parent.children.map(childId => {
    const node = analysisTree.nodes.get(childId);
    const active = node.id === analysisTree.currentId ? ' active' : '';
    return `<li><button type="button" class="analysis-tree-move${active}" data-analysis-node="${node.id}"><span>${analysisEscape(node.san || node.uci)}</span><small>${analysisEscape(analysisEvalText(node.analysis))}</small></button>${analysisTreeHtml(node.id, depth + 1)}</li>`;
  }).join('')}</ol>`;
}
async function loadAnalysisFen(fen) {
  const data = await ChessInteractive.api({ action: 'validate', fen });
  analysisCoordinator.cancel();
  analysisTree = new ChessInteractive.PositionTree(data.fen);
  await analysisRefresh();
}
document.getElementById('analysisBack').addEventListener('click', () => { analysisTree.back(); analysisRefresh(); });
document.getElementById('analysisForward').addEventListener('click', () => { analysisTree.forward(); analysisRefresh(); });
document.getElementById('analysisReset').addEventListener('click', () => { analysisTree.reset(); analysisRefresh(); });
document.getElementById('analysisFlip').addEventListener('click', () => { analysisOrientation = analysisOrientation === 'white' ? 'black' : 'white'; renderAnalysisBoard(); });
document.getElementById('analysisMobileFlip')?.addEventListener('click', () => document.getElementById('analysisFlip').click());
document.getElementById('analysisClose')?.addEventListener('click', () => history.back());
document.getElementById('analysisLoad').addEventListener('click', () => loadAnalysisFen(document.getElementById('analysisFen').value).catch(error => analysisSetStatus(error.message, true)));
document.getElementById('analysisCopy').addEventListener('click', async () => { await navigator.clipboard.writeText(analysisCurrent().fen); analysisSetStatus('FEN copiado'); });
document.getElementById('analysisStart').addEventListener('click', () => loadAnalysisFen(ANALYSIS_START_FEN));
document.querySelector('.analysis-tree-panel').addEventListener('click', event => {
  const button = event.target.closest('[data-analysis-node]');
  if (!button) return;
  const nodeId = Number(button.dataset.analysisNode);
  if (!analysisTree.nodes.has(nodeId)) return;
  analysisTree.currentId = nodeId;
  analysisRefresh();
});

const params = new URLSearchParams(location.search);
const returnGame = Number(params.get('from_review') || 0);
const returnPly = Number(params.get('ply') || 0);
if (returnGame > 0) {
  const link = document.getElementById('analysisReturnReview');
  link.href = `review.php?id=${returnGame}${returnPly > 0 ? `&ply=${returnPly}` : ''}&tab=analysis`;
  link.hidden = false;
}
loadAnalysisFen(params.get('fen') || ANALYSIS_START_FEN).catch(error => analysisSetStatus(error.message, true));
