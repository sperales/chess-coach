<?php
$failures = [];

function expect_profile_maintenance(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$profile = file_get_contents(__DIR__ . '/../profile.php');
$analysisQueue = file_get_contents(__DIR__ . '/../includes/analysis_queue.php');
$training = file_get_contents(__DIR__ . '/../includes/training.php');

foreach ([
  'Backfill de Smart Tags',
  'Backfill de ejercicios',
  'Actualizar contenido de ejercicios',
  'Enriquecer ejercicios con Stockfish',
  'Generar escenarios multi-jugada',
  'Regenerar ADN',
] as $obsoleteControl) {
  expect_profile_maintenance(
    strpos($profile, $obsoleteControl) === false,
    "El control obsoleto '{$obsoleteControl}' no debe aparecer en Ajustes."
  );
}

expect_profile_maintenance(strpos($profile, 'Calidad y conceptos de entrenamiento') !== false, 'El backfill de calidad 1.6 debe seguir disponible.');
expect_profile_maintenance(strpos($profile, 'Backfill de aperturas') !== false, 'El backfill histórico de aperturas debe seguir disponible.');
expect_profile_maintenance(strpos($analysisQueue, 'smart_tag_generate_for_analysis($analysisId, $userId)') !== false, 'Smart Tags debe generarse al completar el análisis.');
expect_profile_maintenance(strpos($analysisQueue, 'training_generate_for_analysis($analysisId, $userId)') !== false, 'Los ejercicios deben generarse al completar el análisis.');
expect_profile_maintenance(strpos($analysisQueue, 'player_dna_refresh_after_analysis(') !== false, 'El ADN debe actualizarse al completar el análisis.');
expect_profile_maintenance(strpos($training, 'training_generate_scenarios_for_analysis($analysisId, $userId, $focusCode)') !== false, 'Los escenarios deben generarse con los ejercicios.');

$listStart = strpos($training, 'function training_list_exercises(');
$listEnd = $listStart === false ? false : strpos($training, 'function ', $listStart + 10);
$listSource = ($listStart !== false && $listEnd !== false) ? substr($training, $listStart, $listEnd - $listStart) : '';
expect_profile_maintenance($listSource !== '', 'Debe poder inspeccionarse el selector de ejercicios.');
expect_profile_maintenance(strpos($listSource, 'content_version') === false, 'El selector no debe excluir ejercicios pendientes de enriquecimiento v3.');

if ($failures) {
  fwrite(STDERR, "Fallos de mantenimiento de Ajustes:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: controles vigentes, automatismos y elegibilidad de ejercicios verificados.\n";
