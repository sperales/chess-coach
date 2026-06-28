<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
$u = require_login();
$userId = (int)$u['id'];
$gameId = (int)($_GET['id'] ?? 0);

function review_accuracy_from_acpl(float $acpl): float {
  if ($acpl <= 0) return 100.0;
  // Aproximación pedagógica, no pretende replicar Chess.com. Usa pérdidas capadas para que
  // una posición de mate no destruya toda la métrica con valores extremos.
  return round(max(0, min(100, 100 * exp(-$acpl / 220))), 1);
}

function review_loss_for_summary(int $loss): int {
  // Para ACPL/accuracy no tiene sentido que una posición de mate cuente como 99.000 cp.
  // Conservamos la clasificación como blunder, pero limitamos su impacto estadístico.
  return min(max(0, $loss), 1000);
}


function review_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
}

function review_score_to_white(?int $score, string $type, ?string $fen): array {
  $raw = (int)($score ?? 0);
  $side = review_fen_side_to_move($fen);
  if ($type === 'mate') {
    // En UCI, score mate N es desde la perspectiva del bando que mueve en el FEN.
    // N > 0: el bando que mueve da mate. N <= 0: el bando que mueve está siendo/muy pronto será mateado.
    // El caso mate 0 era el origen del bug: si blancas están mateadas y tienen el turno,
    // no debe aparecer como +M0 para blancas, sino como -M0.
    $cpForSideToMove = $raw > 0
      ? 100000 - abs($raw) * 1000
      : -100000 + abs($raw) * 1000;
    $cpForWhite = $side === 'w' ? $cpForSideToMove : -$cpForSideToMove;
    $mateForWhite = $side === 'w' ? $raw : -$raw;
    if ($raw === 0) $mateForWhite = $side === 'w' ? -0.1 : 0.1;
    return ['cp' => $cpForWhite, 'type' => 'mate', 'mate' => $mateForWhite];
  }
  return ['cp' => $side === 'w' ? $raw : -$raw, 'type' => 'cp', 'mate' => null];
}

function review_move_bucket(array $m): string {
  $class = $m['classification'] ?? 'ok';
  $loss = (int)($m['centipawn_loss'] ?? 0);
  if ($class === 'blunder') return 'blunder';
  if ($class === 'mistake') return 'mistake';
  if ($class === 'inaccuracy') return 'inaccuracy';
  if ($loss <= 10) return 'best';
  if ($loss <= 25) return 'excellent';
  return 'good';
}

function review_bucket_label(string $bucket): string {
  return [
    'best' => 'Mejor',
    'excellent' => 'Excelente',
    'good' => 'Buena',
    'inaccuracy' => 'Imprecisión',
    'mistake' => 'Error',
    'blunder' => 'Omisión grave',
  ][$bucket] ?? 'Jugada';
}

function review_explanation(array $m): string {
  $bucket = review_move_bucket($m);
  $loss = (int)($m['centipawn_loss'] ?? 0);
  $best = trim((string)($m['bestmove'] ?? ''));
  return match ($bucket) {
    'best' => 'Muy buena decisión: mantiene prácticamente toda la ventaja disponible en la posición.',
    'excellent' => 'Jugada excelente: mejora tu posición sin conceder opciones importantes al rival.',
    'good' => 'Jugada correcta. Puede que hubiera una opción algo más precisa, pero no cambia de forma seria la evaluación.',
    'inaccuracy' => 'Pequeña imprecisión. No pierde la partida, pero sí deja escapar parte de la iniciativa.',
    'mistake' => $best ? "Aquí había una alternativa más fuerte: {$best}. La jugada permite al rival mejorar claramente." : 'Error importante: la evaluación cae bastante y el rival recibe una oportunidad clara.',
    'blunder' => $best ? "Omisión grave. La mejor alternativa del motor era {$best}. Esta jugada cambia mucho el equilibrio de la posición." : 'Omisión grave: esta jugada cambia mucho el equilibrio de la posición.',
    default => "Pérdida estimada: {$loss} centipawns.",
  };
}

if ($gameId <= 0) json_response(['ok' => false, 'error' => 'Partida no indicada.']);

$st = db()->prepare('SELECT * FROM games WHERE id=? AND user_id=?');
$st->execute([$gameId, $userId]);
$game = $st->fetch();
if (!$game) json_response(['ok' => false, 'error' => 'Partida no encontrada.']);

$st = db()->prepare('SELECT * FROM game_analysis WHERE game_id=? AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1');
$st->execute([$gameId, $userId]);
$analysis = $st->fetch();
if (!$analysis) json_response(['ok' => false, 'error' => 'La partida todavía no tiene un análisis completado.']);

$m = db()->prepare('SELECT * FROM game_move_analysis WHERE analysis_id=? ORDER BY ply');
$m->execute([$analysis['id']]);
$moves = $m->fetchAll();

$count = count($moves);
$totalLoss = 0;
$counts = ['best'=>0,'excellent'=>0,'good'=>0,'inaccuracy'=>0,'mistake'=>0,'blunder'=>0];
$reviewMoves = [];
foreach ($moves as $row) {
  $row['centipawn_loss'] = (int)($row['centipawn_loss'] ?? 0);
  $row['score_before'] = is_null($row['score_before']) ? null : (int)$row['score_before'];
  $row['score_after'] = is_null($row['score_after']) ? null : (int)$row['score_after'];
  $beforeEval = review_score_to_white($row['score_before'], $row['score_before_type'] ?? 'cp', $row['fen_before'] ?? '');
  $afterEval = review_score_to_white($row['score_after'], $row['score_after_type'] ?? 'cp', $row['fen_after'] ?? '');
  $row['eval_before_white'] = $beforeEval['cp'];
  $row['eval_after_white'] = $afterEval['cp'];
  $row['eval_before_type'] = $beforeEval['type'];
  $row['eval_after_type'] = $afterEval['type'];
  $row['eval_before_mate'] = $beforeEval['mate'];
  $row['eval_after_mate'] = $afterEval['mate'];
  $bucket = review_move_bucket($row);
  $counts[$bucket]++;
  $totalLoss += review_loss_for_summary($row['centipawn_loss']);
  $row['review_bucket'] = $bucket;
  $row['review_label'] = review_bucket_label($bucket);
  $row['explanation'] = review_explanation($row);
  $reviewMoves[] = $row;
}
// Si la última posición es mate, forzamos la dirección del mate según el resultado real de la partida.
// Esto evita que un mate recibido por el bando que mueve aparezca como ventaja para ese mismo bando.
if ($count > 0 && !empty($reviewMoves[$count - 1])) {
  $lastSan = (string)($reviewMoves[$count - 1]['san'] ?? '');
  $finalLooksLikeMate = ($reviewMoves[$count - 1]['score_after_type'] ?? '') === 'mate' || str_contains($lastSan, '#');
  if ($finalLooksLikeMate) {
    $resultRaw = trim((string)($game['result_raw'] ?? ''));
    $reviewMoves[$count - 1]['eval_after_type'] = 'mate';
    if ($resultRaw === '1-0') {
      $reviewMoves[$count - 1]['eval_after_white'] = 100000;
      $reviewMoves[$count - 1]['eval_after_mate'] = abs((float)($reviewMoves[$count - 1]['eval_after_mate'] ?? 0));
    } elseif ($resultRaw === '0-1') {
      $reviewMoves[$count - 1]['eval_after_white'] = -100000;
      $reviewMoves[$count - 1]['eval_after_mate'] = -abs((float)($reviewMoves[$count - 1]['eval_after_mate'] ?? 0));
    } elseif ($resultRaw === '1/2-1/2') {
      $reviewMoves[$count - 1]['eval_after_white'] = 0;
      $reviewMoves[$count - 1]['eval_after_type'] = 'cp';
    }
  }
}

$acpl = $count ? round($totalLoss / $count, 1) : 0;
$accuracy = review_accuracy_from_acpl($acpl);

$result = $game['user_result'] ?? 'unknown';
$headline = match ($result) {
  'win' => 'Has ganado la partida',
  'loss' => 'La partida no ha salido como esperabas',
  'draw' => 'La partida terminó en tablas',
  default => 'Revisión de partida',
};
$comment = match ($result) {
  'win' => 'Buen trabajo. Revisa los momentos críticos para entender qué decisiones te ayudaron a construir la ventaja.',
  'loss' => 'Hay errores que revisar, pero también buenas decisiones. La clave es detectar dónde cambió la partida.',
  'draw' => 'Una partida equilibrada. Vamos a buscar dónde pudiste presionar más o simplificar mejor.',
  default => 'Vamos a revisar los momentos más importantes de esta partida.',
};
if (($counts['blunder'] ?? 0) > 0) $comment .= ' Prioridad: revisar las omisiones graves.';
elseif (($counts['mistake'] ?? 0) > 0) $comment .= ' Prioridad: reducir los errores importantes.';
elseif ($accuracy >= 80) $comment .= ' La precisión general es bastante sólida.';

json_response([
  'ok' => true,
  'game' => $game,
  'analysis' => $analysis,
  'summary' => [
    'moves' => $count,
    'accuracy' => $accuracy,
    'acpl' => $acpl,
    'counts' => $counts,
    'headline' => $headline,
    'comment' => $comment,
  ],
  'moves' => $reviewMoves,
]);
