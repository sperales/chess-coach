<?php
require_once __DIR__ . '/../includes/training_progress.php';

$qualityCases = [
  ['Primer intento limpio', 'solved', 1, 0, 100.0],
  ['Segundo intento limpio', 'solved', 2, 0, 90.0],
  ['Tercer intento limpio', 'solved', 3, 0, 80.0],
  ['Cuarto intento limpio', 'solved', 4, 0, 65.0],
  ['Quinto intento limpio', 'solved', 5, 0, 50.0],
  ['Pista de idea', 'solved', 1, 1, 85.0],
  ['Pista de pieza', 'solved', 1, 2, 65.0],
  ['Pista de accion y zona', 'solved', 1, 3, 45.0],
  ['Intentos y pista combinados', 'solved', 4, 1, 65.0],
  ['Intentos fuera de rango', 'solved', 99, 0, 50.0],
  ['Pista fuera de rango', 'solved', 1, 99, 45.0],
  ['Resolucion sin intentos normalizada', 'solved', 0, 0, 100.0],
  ['Fallado', 'failed', 5, 3, 0.0],
  ['Saltado excluido', 'skipped', 0, 0, null],
  ['Abandonado excluido', 'abandoned', 0, 0, null],
];

$failures = [];
foreach ($qualityCases as [$label, $status, $attempts, $hintLevel, $expected]) {
  $actual = training_resolution_quality($status, $attempts, $hintLevel);
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

$weightCases = [
  ['easy', 0.85],
  ['medium', 1.00],
  ['hard', 1.15],
  ['critical', 1.30],
  ['unknown', 1.00],
];
foreach ($weightCases as [$difficulty, $expected]) {
  $actual = training_difficulty_evidence_weight($difficulty);
  if ($actual !== $expected) {
    $failures[] = 'Peso ' . $difficulty . ': esperado ' . $expected . ', recibido ' . $actual;
  }
}

if ($failures) {
  fwrite(STDERR, "Fallos de progreso de entrenamiento:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo 'OK: ' . (count($qualityCases) + count($weightCases)) . " casos de progreso de entrenamiento.\n";
