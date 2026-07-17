<?php
require_once __DIR__ . '/../includes/review_progress.php';
require_once __DIR__ . '/../includes/training_plan.php';

$failures = [];

function assert_plan_value(string $label, mixed $actual, mixed $expected): void {
  global $failures;
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

assert_plan_value('Plies unicas y ordenadas', review_progress_sanitize_plies([5, 2, 5, 0, 99, 3], 10), [2, 3, 5]);
assert_plan_value('Umbral de review normal', review_progress_required_count(74), 17);
assert_plan_value('Umbral de partida corta', review_progress_required_count(12), 12);
assert_plan_value('Umbral sin movimientos', review_progress_required_count(0), 0);

$date = new DateTimeImmutable('2026-07-17 12:00:00');
assert_plan_value('Periodo diario', training_plan_period('daily', $date), ['start' => '2026-07-17', 'end' => '2026-07-17']);
assert_plan_value('Periodo semanal ISO', training_plan_period('weekly', $date), ['start' => '2026-07-13', 'end' => '2026-07-19']);
assert_plan_value(
  'Clave estable con contexto',
  training_plan_goal_key('daily', '2026-07-17', 'review_game', '42'),
  'daily:2026-07-17:review_game:42'
);

if ($failures) {
  fwrite(STDERR, "Fallos del plan de entrenamiento:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: seguimiento de reviews y periodos del plan de entrenamiento.\n";
