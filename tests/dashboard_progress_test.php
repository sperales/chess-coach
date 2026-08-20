<?php
require_once __DIR__ . '/../includes/dashboard.php';

$dashboardScript = file_get_contents(__DIR__ . '/../assets/js/dashboard.js');

$failures = [];
if (!str_contains($dashboardScript, 'homeTrainingActionUrl(coachPlan, dashboardData.active_training)')
    || !str_contains($dashboardScript, "return 'training.php?start=1'")) {
  $failures[] = 'El CTA principal de Nova debe continuar el plan activo o iniciar uno nuevo.';
}
$items = [
  ['played_at' => '2026-07-01', 'imported_at' => null, 'accuracy' => 80.0, 'user_result' => 'win'],
  ['played_at' => '2026-07-05', 'imported_at' => null, 'accuracy' => 60.0, 'user_result' => 'loss'],
];

$all = dashboard_progress_points($items, '2026-07-01', '2026-07-05');
if ($all['labels'] !== ['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-05']) {
  $failures[] = 'El historico no genera exactamente un punto por dia.';
}
if ($all['accuracy'] !== [80.0, 80.0, 80.0, 80.0, 70.0]) {
  $failures[] = 'La serie acumulada de Accuracy no es correcta.';
}
if ($all['win_rate'] !== [100.0, 100.0, 100.0, 100.0, 50.0]) {
  $failures[] = 'La serie acumulada de Win rate no es correcta.';
}

$filtered = dashboard_progress_points($items, '2026-07-03', '2026-07-05');
if ($filtered['accuracy'] !== [80.0, 80.0, 70.0] || $filtered['win_rate'] !== [100.0, 100.0, 50.0]) {
  $failures[] = 'El filtro temporal no conserva el acumulado anterior al periodo.';
}
if (array_slice($all['accuracy'], -3) !== $filtered['accuracy'] || array_slice($all['win_rate'], -3) !== $filtered['win_rate']) {
  $failures[] = 'Los periodos solapados no muestran los mismos valores para las mismas fechas.';
}

$snapshot = dashboard_daily_snapshot_series(['2026-07-01', '2026-07-05'], [500, 550], '2026-07-03', '2026-07-05');
if ($snapshot['values'] !== [500, 500, 550]) $failures[] = 'La serie diaria de rendimiento no arrastra el ultimo valor conocido.';

$sampled = dashboard_sample_series(range(1, 100), range(101, 200), 8);
if (count($sampled['labels']) !== 8 || $sampled['labels'][0] !== 1 || $sampled['labels'][7] !== 100) {
  $failures[] = 'El muestreo de la grafica no conserva los extremos.';
}

if ($failures) {
  fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
  exit(1);
}

echo "OK: periodos y series del progreso de Home verificados." . PHP_EOL;
