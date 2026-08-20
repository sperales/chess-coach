<?php
require_once __DIR__ . '/chess_evaluation.php';

function adaptive_analysis_enabled(array $config): bool {
  return strtolower(trim((string)($config['analysis_strategy'] ?? ''))) === 'adaptive_nodes';
}

function adaptive_analysis_profile(array $config): array {
  $baselineNodes = max(10000, (int)($config['baseline_nodes'] ?? 40000));
  $criticalNodes = max($baselineNodes, (int)($config['critical_nodes'] ?? 200000));
  return [
    'mode' => 'adaptive_nodes',
    'baseline_nodes' => $baselineNodes,
    'critical_nodes' => $criticalNodes,
    'critical_loss_cp' => max(10, (int)($config['critical_loss_cp'] ?? 40)),
    'threshold_margin_cp' => max(0, (int)($config['critical_threshold_margin_cp'] ?? 20)),
    'minimum_baseline_depth' => max(1, (int)($config['minimum_baseline_depth'] ?? 12)),
    'max_critical_positions' => max(2, (int)($config['max_critical_positions'] ?? 32)),
  ];
}

function adaptive_analysis_move_metrics(array $move, array $before, array $after): array {
  $side = chess_fen_side_to_move($move['fen_before'] ?? null);
  $beforeWhite = chess_evaluation_to_white(
    isset($before['score']) ? (int)$before['score'] : null,
    $before['score_type'] ?? null,
    $move['fen_before'] ?? null
  );
  $afterWhite = chess_evaluation_to_white(
    isset($after['score']) ? (int)$after['score'] : null,
    $after['score_type'] ?? null,
    $move['fen_after'] ?? null
  );
  if ($beforeWhite === null || $afterWhite === null) {
    throw new UnexpectedValueException('No se puede comparar una evaluación preliminar ausente.');
  }
  $beforePlayer = $side === 'w' ? $beforeWhite : -$beforeWhite;
  $afterPlayer = $side === 'w' ? $afterWhite : -$afterWhite;
  return [
    'loss' => min(1000, max(0, $beforePlayer - $afterPlayer)),
    'before_player' => $beforePlayer,
    'after_player' => $afterPlayer,
    'before_state' => chess_position_state($beforePlayer),
    'after_state' => chess_position_state($afterPlayer),
  ];
}

function adaptive_analysis_position_is_terminal(array $evaluation): bool {
  return !empty($evaluation['terminal']);
}

function adaptive_analysis_critical_positions(array $moves, array $evaluations, array $config): array {
  $profile = adaptive_analysis_profile($config);
  $candidates = [];
  $thresholds = [CHESS_CPL_INACCURACY, CHESS_CPL_MISTAKE, CHESS_CPL_BLUNDER];

  foreach ($moves as $index => $move) {
    if (!isset($evaluations[$index], $evaluations[$index + 1])) continue;
    $before = $evaluations[$index];
    $after = $evaluations[$index + 1];
    $metrics = adaptive_analysis_move_metrics($move, $before, $after);
    $priority = 0;

    if (($before['score_type'] ?? null) === 'mate' || ($after['score_type'] ?? null) === 'mate') {
      $priority = max($priority, 10000);
    }
    if ($metrics['loss'] >= $profile['critical_loss_cp']) {
      $priority = max($priority, 1000 + $metrics['loss']);
    }
    if ($metrics['before_state'] !== $metrics['after_state']) {
      $priority = max($priority, 1800 + $metrics['loss']);
    }
    foreach ($thresholds as $threshold) {
      $distance = abs($metrics['loss'] - $threshold);
      if ($distance <= $profile['threshold_margin_cp']) {
        $priority = max($priority, 1400 + ($profile['threshold_margin_cp'] - $distance));
      }
    }
    if ((int)($before['depth'] ?? 0) < $profile['minimum_baseline_depth']
        || (int)($after['depth'] ?? 0) < $profile['minimum_baseline_depth']) {
      $priority = max($priority, 900);
    }
    if ($priority === 0) continue;

    $positions = [];
    foreach ([$index, $index + 1] as $positionIndex) {
      if (!isset($evaluations[$positionIndex]) || adaptive_analysis_position_is_terminal($evaluations[$positionIndex])) continue;
      $positions[] = $positionIndex;
    }
    if ($positions) $candidates[] = ['priority' => $priority, 'positions' => $positions];
  }

  usort($candidates, fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
  $selectedMap = [];
  foreach ($candidates as $candidate) {
    $newPositions = array_values(array_filter(
      $candidate['positions'],
      fn(int $positionIndex): bool => !isset($selectedMap[$positionIndex])
    ));
    if (count($selectedMap) + count($newPositions) > $profile['max_critical_positions']) continue;
    foreach ($newPositions as $positionIndex) $selectedMap[$positionIndex] = true;
  }
  $selected = array_keys($selectedMap);
  sort($selected, SORT_NUMERIC);
  return array_map('intval', $selected);
}
