<?php
require_once __DIR__ . '/../includes/chess_evaluation.php';

function evaluation_fixture(int $before, int $after, int $loss, string $uci = 'e2e4', string $bestmove = 'd2d4', string $beforeType = 'cp', string $afterType = 'cp'): array {
  return [
    'ply' => 1,
    'uci' => $uci,
    'bestmove' => $bestmove,
    'fen_before' => '8/8/8/8/8/8/8/8 w - - 0 1',
    'fen_after' => '8/8/8/8/8/8/8/8 b - - 0 1',
    'score_before' => $before,
    'score_before_type' => $beforeType,
    'score_after' => $after,
    'score_after_type' => $afterType,
    'centipawn_loss' => $loss,
  ];
}

$cases = [
  ['bestmove exacta', evaluation_fixture(50, -20, 200, 'e2e4', 'e2e4'), 'best', false, 'equal_to_equal'],
  ['alternativa equivalente', evaluation_fixture(20, -15, 5), 'excellent', false, 'equal_to_equal'],
  ['ganadora sigue ganadora', evaluation_fixture(600, -350, 250), 'mistake', true, 'winning_reduced'],
  ['ventaja a igualdad', evaluation_fixture(200, -20, 180), 'mistake', true, 'advantage_lost'],
  ['ventaja a perdida', evaluation_fixture(200, 200, 400), 'blunder', true, 'advantage_reversed'],
  ['igualdad a perdida', evaluation_fixture(0, 400, 400), 'blunder', true, 'equal_to_losing'],
  ['perdida empeora', evaluation_fixture(-400, 700, 300), 'mistake', true, 'losing_worsened'],
  ['mate desaprovechado', evaluation_fixture(3, -300, 1000, 'e2e4', 'd2d4', 'mate', 'cp'), 'mistake', true, 'winning_reduced'],
];

$failures = [];
foreach ($cases as [$label, $move, $bucket, $alternative, $impact]) {
  $assessment = chess_move_assessment($move);
  if ($assessment['bucket'] !== $bucket) {
    $failures[] = "$label: esperado $bucket, recibido {$assessment['bucket']}";
  }
  if ($assessment['has_relevant_alternative'] !== $alternative) {
    $failures[] = "$label: visibilidad de alternativa incorrecta";
  }
  if ($assessment['impact'] !== $impact) {
    $failures[] = "$label: impacto esperado $impact, recibido {$assessment['impact']}";
  }
  if ($label === 'bestmove exacta' && $assessment['effective_loss'] !== 0) {
    $failures[] = 'La mejor jugada exacta debe aportar CPL efectivo 0.';
  }
}

$winningExplanation = chess_move_explanation($cases[2][1]);
if (!str_contains($winningExplanation, 'continúa claramente ganada')) {
  $failures[] = 'La transición ganadora->ganadora no conserva el matiz pedagógico esperado.';
}

if ($failures) {
  fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
  exit(1);
}

echo "OK: clasificación pedagógica y transiciones verificadas." . PHP_EOL;
