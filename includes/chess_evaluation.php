<?php

// Existing CPL thresholds are preserved for compatibility with historical analyses.
const CHESS_CPL_BEST_EQUIVALENCE = 10;
const CHESS_CPL_EXCELLENT = 25;
const CHESS_CPL_INACCURACY = 70;
const CHESS_CPL_MISTAKE = 150;
const CHESS_CPL_BLUNDER = 300;

// Scores are centipawns from the moving player's perspective: +0.75 is an advantage,
// +3.00 is clearly winning, and -1.50 is a clear practical loss for transition severity.
const CHESS_POSITION_ADVANTAGE = 75;
const CHESS_POSITION_WINNING = 300;
const CHESS_POSITION_CLEAR_LOSS = -150;

function chess_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
}

function chess_evaluation_to_white(?int $score, ?string $type, ?string $fen): ?int {
  if ($score === null || !in_array($type, ['cp', 'mate'], true)) return null;
  if ($type === 'mate') {
    $forSideToMove = $score > 0
      ? 100000 - abs($score) * 1000
      : -100000 + abs($score) * 1000;
  } else {
    $forSideToMove = $score;
  }
  return chess_fen_side_to_move($fen) === 'w' ? $forSideToMove : -$forSideToMove;
}

function chess_move_side(array $move): string {
  $ply = (int)($move['ply'] ?? 0);
  if ($ply > 0) return $ply % 2 === 1 ? 'w' : 'b';
  return chess_fen_side_to_move($move['fen_before'] ?? null);
}

function chess_move_scores_for_player(array $move): array {
  $side = chess_move_side($move);
  $beforeWhite = chess_evaluation_to_white(
    isset($move['score_before']) ? (int)$move['score_before'] : null,
    $move['score_before_type'] ?? null,
    $move['fen_before'] ?? null
  );
  $afterWhite = chess_evaluation_to_white(
    isset($move['score_after']) ? (int)$move['score_after'] : null,
    $move['score_after_type'] ?? null,
    $move['fen_after'] ?? null
  );
  return [
    'before' => $beforeWhite === null ? null : ($side === 'w' ? $beforeWhite : -$beforeWhite),
    'after' => $afterWhite === null ? null : ($side === 'w' ? $afterWhite : -$afterWhite),
  ];
}

function chess_position_state(?int $score): string {
  if ($score === null) return 'unknown';
  if ($score >= CHESS_POSITION_WINNING) return 'winning';
  if ($score >= CHESS_POSITION_ADVANTAGE) return 'advantage';
  if ($score > -CHESS_POSITION_ADVANTAGE) return 'equal';
  return 'losing';
}

function chess_objective_classification(int $loss): string {
  if ($loss >= CHESS_CPL_BLUNDER) return 'blunder';
  if ($loss >= CHESS_CPL_MISTAKE) return 'mistake';
  if ($loss >= CHESS_CPL_INACCURACY) return 'inaccuracy';
  return 'ok';
}

function classify_loss(int $loss): string {
  return chess_objective_classification($loss);
}

function chess_move_matches_best(array $move): bool {
  $played = strtolower(trim((string)($move['uci'] ?? '')));
  $best = strtolower(trim((string)($move['bestmove'] ?? '')));
  return $played !== '' && $best !== '' && $played === $best;
}

function chess_move_impact(string $before, string $after, ?string $beforeType, ?string $afterType): string {
  if ($beforeType === 'mate' || $afterType === 'mate') {
    if (in_array($before, ['winning', 'advantage'], true) && $after === 'losing') return 'mate_reversal';
    if ($before === 'equal' && $after === 'losing') return 'mate_allowed';
    if ($before === 'winning' && $after !== 'winning') return 'mate_missed';
  }
  if ($before === 'winning' && $after === 'winning') return 'winning_reduced';
  if ($before === 'winning' && $after === 'advantage') return 'winning_to_advantage';
  if ($before === 'winning' && $after === 'equal') return 'winning_to_equal';
  if (in_array($before, ['winning', 'advantage'], true) && $after === 'losing') return 'advantage_reversed';
  if ($before === 'advantage' && $after === 'equal') return 'advantage_lost';
  if ($before === 'equal' && $after === 'losing') return 'equal_to_losing';
  if ($before === 'losing' && $after === 'losing') return 'losing_worsened';
  return $before . '_to_' . $after;
}

function chess_move_pedagogical_bucket(array $move): string {
  if (chess_move_matches_best($move)) return 'best';
  $loss = max(0, (int)($move['centipawn_loss'] ?? 0));
  $scores = chess_move_scores_for_player($move);
  $before = chess_position_state($scores['before']);
  $after = chess_position_state($scores['after']);
  $impact = chess_move_impact($before, $after, $move['score_before_type'] ?? null, $move['score_after_type'] ?? null);

  if (in_array($impact, ['mate_reversal', 'mate_allowed', 'advantage_reversed', 'winning_to_equal'], true)) {
    return 'blunder';
  }
  if ($impact === 'equal_to_losing') {
    return ($scores['after'] ?? 0) <= CHESS_POSITION_CLEAR_LOSS || $loss >= CHESS_CPL_MISTAKE
      ? 'blunder'
      : 'mistake';
  }
  if ($impact === 'advantage_lost' || $impact === 'winning_to_advantage') {
    return $loss >= CHESS_CPL_MISTAKE ? 'mistake' : 'inaccuracy';
  }
  if ($impact === 'winning_reduced' && $loss >= CHESS_CPL_MISTAKE) return 'mistake';
  if ($impact === 'losing_worsened') {
    if ($loss >= CHESS_CPL_MISTAKE) return 'mistake';
    if ($loss >= CHESS_CPL_INACCURACY) return 'inaccuracy';
  }

  $objective = chess_objective_classification($loss);
  if ($objective !== 'ok') return $objective;
  if ($loss <= CHESS_CPL_EXCELLENT) return 'excellent';
  return 'good';
}

function chess_move_assessment(array $move): array {
  $loss = max(0, (int)($move['centipawn_loss'] ?? 0));
  $scores = chess_move_scores_for_player($move);
  $before = chess_position_state($scores['before']);
  $after = chess_position_state($scores['after']);
  $bucket = chess_move_pedagogical_bucket($move);
  $matchesBest = chess_move_matches_best($move);
  $effectiveLoss = $matchesBest ? 0 : $loss;
  $hasAlternative = !$matchesBest
    && trim((string)($move['bestmove'] ?? '')) !== ''
    && $effectiveLoss > CHESS_CPL_BEST_EQUIVALENCE;

  return [
    'bucket' => $bucket,
    'storage_classification' => in_array($bucket, ['inaccuracy', 'mistake', 'blunder'], true) ? $bucket : 'ok',
    'objective_classification' => chess_objective_classification($loss),
    'effective_loss' => $effectiveLoss,
    'before_score' => $scores['before'],
    'after_score' => $scores['after'],
    'before_state' => $before,
    'after_state' => $after,
    'impact' => chess_move_impact($before, $after, $move['score_before_type'] ?? null, $move['score_after_type'] ?? null),
    'matches_bestmove' => $matchesBest,
    'has_relevant_alternative' => $hasAlternative,
  ];
}

function chess_move_explanation(array $move, ?array $assessment = null): string {
  $assessment ??= chess_move_assessment($move);
  $best = trim((string)($move['bestmove_display'] ?? $move['bestmove'] ?? ''));
  if (!empty($assessment['matches_bestmove'])) {
    return 'Es la primera elección de Stockfish: mantiene la mejor evaluación disponible.';
  }
  if ((int)($move['centipawn_loss'] ?? 0) <= CHESS_CPL_BEST_EQUIVALENCE) {
    return 'Jugada prácticamente equivalente a la primera opción del motor; no hay una alternativa superior relevante.';
  }
  return match ($assessment['impact']) {
    'winning_reduced' => 'Has perdido una parte importante de la ventaja, pero la posición continúa claramente ganada.',
    'winning_to_advantage' => 'Has reducido una posición ganadora a una ventaja más modesta, aunque sigues mejor.',
    'winning_to_equal', 'advantage_lost' => 'Has dejado escapar casi toda la ventaja y la posición vuelve a estar equilibrada.',
    'advantage_reversed', 'mate_reversal' => 'La jugada convierte una posición favorable en una posición claramente desfavorable.',
    'equal_to_losing', 'mate_allowed' => 'La jugada rompe el equilibrio y deja una posición claramente desfavorable.',
    'losing_worsened' => 'La posición ya era difícil. La jugada empeora la evaluación, pero no cambia por sí sola el estado práctico de la partida.',
    default => match ($assessment['bucket']) {
      'excellent' => 'Jugada excelente: conserva prácticamente toda la evaluación disponible.',
      'good' => 'Jugada correcta. Había una opción algo más precisa, pero el estado de la posición apenas cambia.',
      'inaccuracy' => 'Pequeña imprecisión: cede parte de la iniciativa sin cambiar decisivamente la partida.',
      'mistake' => $best !== '' ? "Error importante. Una alternativa más fuerte era {$best}." : 'Error importante: el rival recibe una oportunidad clara.',
      'blunder' => $best !== '' ? "Omisión grave. La alternativa principal era {$best}." : 'Omisión grave: cambia de forma decisiva el estado de la posición.',
      default => 'Jugada evaluada sin un cambio práctico relevante.',
    },
  };
}
