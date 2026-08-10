<?php
require_once __DIR__ . '/../includes/dashboard.php';

$failures = [];
$items = [
  ['played_at' => '2026-07-01', 'imported_at' => null, 'accuracy' => 80.0, 'user_result' => 'win'],
  ['played_at' => '2026-07-05', 'imported_at' => null, 'accuracy' => 60.0, 'user_result' => 'loss'],
];

$all = dashboard_progress_points($items, null);
if ($all['labels'] !== ['2026-07-01', '2026-07-05']) {
  $failures[] = 'El historico completo no conserva las fechas.';
}
if ($all['accuracy'] !== [80.0, 70.0]) {
  $failures[] = 'La serie acumulada de Accuracy no es correcta.';
}
if ($all['win_rate'] !== [100.0, 50.0]) {
  $failures[] = 'La serie acumulada de Win rate no es correcta.';
}

$filtered = dashboard_progress_points($items, '2026-07-03');
if ($filtered['labels'] !== ['2026-07-05'] || $filtered['accuracy'] !== [60.0] || $filtered['win_rate'] !== [0.0]) {
  $failures[] = 'El filtro temporal no recalcula el periodo seleccionado.';
}

$sampled = dashboard_sample_series(range(1, 100), range(101, 200), 8);
if (count($sampled['labels']) !== 8 || $sampled['labels'][0] !== 1 || $sampled['labels'][7] !== 100) {
  $failures[] = 'El muestreo de la grafica no conserva los extremos.';
}

if ($failures) {
  fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
  exit(1);
}

echo "OK: periodos y series del progreso de Home verificados." . PHP_EOL;
