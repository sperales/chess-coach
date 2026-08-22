<?php
require_once __DIR__ . '/../includes/training_adaptation.php';

function expect_adaptation(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$hardFailures = [
  ['result' => 'failed', 'difficulty' => 'critical'],
  ['result' => 'failed', 'difficulty' => 'hard'],
];
expect_adaptation(training_adaptation_decision($hardFailures)['action'] === 'lower_difficulty', 'Dos fallos difíciles deben bajar el ritmo.');

$fastSuccesses = array_fill(0, 3, [
  'result' => 'solved', 'difficulty' => 'medium', 'attempts' => 1, 'hint_level' => 0, 'time_to_first_move_ms' => 18000,
]);
expect_adaptation(training_adaptation_decision($fastSuccesses)['action'] === 'raise_difficulty', 'Tres aciertos autónomos rápidos deben subir ligeramente.');

$supported = $fastSuccesses;
$supported[0]['hint_level'] = 1;
expect_adaptation(training_adaptation_decision($supported)['action'] === 'keep', 'Una resolución con pista no debe activar subida automática.');

echo "training_adaptation_test: OK\n";
