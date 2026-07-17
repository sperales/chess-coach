<?php
require_once __DIR__ . '/../includes/training_hints.php';

$failures = [];

function expect_hint(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$knightExercise = [
  'exercise_type' => 'find_best_move',
  'fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
  'solution_uci' => 'g1f3',
];
$idea = training_hint_generate($knightExercise, 1);
$piece = training_hint_generate($knightExercise, 2);
$action = training_hint_generate($knightExercise, 3);

expect_hint(($idea['type'] ?? '') === 'idea', 'El nivel 1 debe explicar la idea.');
expect_hint(($idea['highlight_squares'] ?? null) === [], 'El nivel 1 no debe marcar casillas.');
expect_hint(strpos((string)($piece['text'] ?? ''), 'caballo') !== false, 'El nivel 2 debe identificar el caballo.');
expect_hint(($piece['highlight_squares'] ?? []) === ['g1'], 'El nivel 2 debe marcar solo la casilla origen.');
expect_hint(strpos((string)($action['text'] ?? ''), 'flanco de rey') !== false, 'El nivel 3 debe indicar la zona.');
expect_hint(($action['highlight_squares'] ?? []) === ['g1'], 'El nivel 3 debe conservar la marca de origen.');

foreach ([$idea, $piece, $action] as $index => $hint) {
  $serialized = json_encode($hint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  expect_hint(strpos((string)$serialized, 'g1f3') === false, 'La pista ' . ($index + 1) . ' filtra la UCI completa.');
  expect_hint(strpos((string)$serialized, 'f3') === false, 'La pista ' . ($index + 1) . ' filtra la casilla destino.');
}

$capture = training_hint_generate([
  'exercise_type' => 'find_tactic',
  'fen' => '4k3/8/8/3p4/4P3/8/8/4K3 w - - 0 1',
  'solution_uci' => 'e4d5',
], 3);
expect_hint(strpos((string)($capture['text'] ?? ''), 'captura') !== false, 'El nivel 3 debe reconocer una captura.');

$castle = training_hint_generate([
  'exercise_type' => 'find_best_move',
  'fen' => '4k3/8/8/8/8/8/8/4K2R w K - 0 1',
  'solution_uci' => 'e1g1',
], 3);
expect_hint(strpos((string)($castle['text'] ?? ''), 'enrocar') !== false, 'El nivel 3 debe reconocer el enroque.');

$promotion = training_hint_generate([
  'exercise_type' => 'find_best_move',
  'fen' => '4k3/P7/8/8/8/8/8/4K3 w - - 0 1',
  'solution_uci' => 'a7a8q',
], 3);
expect_hint(strpos((string)($promotion['text'] ?? ''), 'promocionar') !== false, 'El nivel 3 debe reconocer la promoción.');

expect_hint(training_hint_generate($knightExercise, 0) === null, 'El nivel 0 debe rechazarse.');
expect_hint(training_hint_generate(['fen' => 'invalid', 'solution_uci' => 'g1f3'], 1) === null, 'Un FEN inválido debe fallar de forma controlada.');
expect_hint(training_hint_generate(['fen' => $knightExercise['fen'], 'solution_uci' => 'bad'], 1) === null, 'Una solución inválida debe fallar de forma controlada.');
expect_hint(training_hint_level_is_available(0, 1), 'La primera pista debe estar disponible al comenzar.');
expect_hint(!training_hint_level_is_available(0, 2), 'No se debe poder saltar directamente a la segunda pista.');
expect_hint(training_hint_level_is_available(1, 1), 'Repetir una petición de pista debe ser idempotente.');
expect_hint(training_hint_level_is_available(1, 2), 'La segunda pista debe habilitarse después de la primera.');
expect_hint(!training_hint_level_is_available(1, 3), 'No se debe poder saltar de la primera a la tercera pista.');

if ($failures) {
  fwrite(STDERR, "Fallos de pistas progresivas:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: pistas progresivas contextuales sin filtrar la solución.\n";
