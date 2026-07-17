<?php
require_once __DIR__ . '/../includes/player_progress.php';

$failures = [];

function assert_progress_value(string $label, mixed $actual, mixed $expected): void {
  global $failures;
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

assert_progress_value('Accuracy con ACPL cero', player_progress_accuracy_from_acpl(0), 100.0);
assert_progress_value('Disciplina sin errores', player_progress_discipline_score(0, 0, 0, 20), 100.0);
assert_progress_value('Disciplina con una omision grave', player_progress_discipline_score(1, 0, 0, 20), 85.0);
assert_progress_value('Calidad de derrota precisa', player_progress_game_quality(80, 90, 'loss'), 78.0);
assert_progress_value('Calidad de victoria imprecisa', player_progress_game_quality(40, 40, 'win'), 46.0);
assert_progress_value('Score combinado', player_progress_combined_score(80.0, 70.0), 760);
assert_progress_value('Score solo ejercicios', player_progress_combined_score(80.0, null), 800);
assert_progress_value('Score solo partidas', player_progress_combined_score(null, 70.0), 700);
assert_progress_value('Score sin muestras', player_progress_combined_score(null, null), 0);
assert_progress_value('Media ponderada por dificultad', player_progress_weighted_average([
  ['quality' => 100.0, 'weight' => 0.85],
  ['quality' => 50.0, 'weight' => 1.15],
]), 71.25);

assert_progress_value('Autonomia limpia', player_progress_autonomy_sample('solved', 1, 0), 100.0);
assert_progress_value('Autonomia con segundo intento', player_progress_autonomy_sample('solved', 2, 0), 95.5);
assert_progress_value('Autonomia con primera pista', player_progress_autonomy_sample('solved', 1, 1), 79.0);
assert_progress_value('Autonomia con maxima ayuda', player_progress_autonomy_sample('solved', 5, 3), 16.0);
assert_progress_value('Autonomia al fallar', player_progress_autonomy_sample('failed', 5, 3), 0.0);

if ($failures) {
  fwrite(STDERR, "Fallos de calculo de progreso:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: formulas de Progress Score, partidas y autonomia.\n";
