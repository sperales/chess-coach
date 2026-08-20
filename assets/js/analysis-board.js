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
  const data = await analysisCoordinator.run(signal => ChessInteractive.api({ action: 'analyze', fen: node.fen }, signal));
  if (!data || node !== analysisCurrent()) return;
  node.analysis = data.analysis;
  document.getElementById('analysisEval').textContent = analysisEvalText(data.analysis);
  document.getElementById('analysisBest').textContent = data.analysis.bestmove_san || '--';
  document.getElementById('analysisPv').textContent = (data.analysis.pv_moves || []).map(move => move.san).join(' ') || 'Sin línea disponible.';
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
  document.getElementById('analysisHistory').innerHTML = line.map((node, index) => `<span class="${node.id === analysisTree.currentId ? 'active' : ''}">${index ? node.san : 'Inicio'}</span>`).join('');
  document.getElementById('analysisBack').disabled = !analysisCurrent()?.parentId;
  document.getElementById('analysisForward').disabled = !(analysisCurrent()?.children || []).length;
  const branches = document.getElementById('analysisBranches');
  const children = (analysisCurrent()?.children || []).map(id => analysisTree.nodes.get(id));
  branches.innerHTML = children.length > 1
    ? `<small>Continuaciones guardadas</small>${children.map(node => `<button class="secondary small" type="button" data-analysis-branch="${node.id}">${node.san || node.uci}</button>`).join('')}`
    : '';
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
document.getElementById('analysisLoad').addEventListener('click', () => loadAnalysisFen(document.getElementById('analysisFen').value).catch(error => analysisSetStatus(error.message, true)));
document.getElementById('analysisCopy').addEventListener('click', async () => { await navigator.clipboard.writeText(analysisCurrent().fen); analysisSetStatus('FEN copiado'); });
document.getElementById('analysisStart').addEventListener('click', () => loadAnalysisFen(ANALYSIS_START_FEN));
document.getElementById('analysisBranches').addEventListener('click', event => {
  const button = event.target.closest('[data-analysis-branch]');
  if (!button) return;
  analysisTree.forward(Number(button.dataset.analysisBranch));
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
