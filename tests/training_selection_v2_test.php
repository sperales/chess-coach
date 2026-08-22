<?php
require_once __DIR__ . '/../includes/training_selection_v2.php';

$failures = [];
function expect_selection_v2(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$base = [
  'pedagogical_score' => 70, 'primary_concept_code' => 'threats_defense',
  'next_review_at' => null, 'source_completed_at' => null, 'last_training_result' => null, 'last_selected_at' => null,
];
$focus = training_selection_priority($base, 'threats_defense');
expect_selection_v2($focus['selection_priority'] === 90, 'El foco principal debe sumar 20 sin modificar Pedagogical Score.');
expect_selection_v2($focus['reason_code'] === 'primary_focus_new', 'La razón principal debe persistirse de forma estructurada.');

$recovery = training_selection_priority(array_replace($base, [
  'next_review_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
  'last_training_result' => 'failed',
  'source_completed_at' => date('Y-m-d H:i:s'),
]), 'calculation_candidates');
expect_selection_v2($recovery['selection_priority'] === 115, 'Repaso vencido, partida reciente y recuperación deben sumarse a la prioridad.');
expect_selection_v2($recovery['reason_code'] === 'previous_failure', 'La recuperación de fallo debe explicar por qué aparece hoy.');

$pool = [
  ['id' => 1] + $base,
  ['id' => 2] + array_replace($base, ['pedagogical_score' => 68, 'primary_concept_code' => 'king_safety_mate', 'last_training_result' => 'failed']),
  ['id' => 3] + array_replace($base, ['pedagogical_score' => 80, 'primary_concept_code' => 'threats_defense']),
];
$selected = training_selection_v2_select($pool, 2, 'threats_defense');
expect_selection_v2(count($selected) === 2, 'Selection v2 debe respetar el límite.');
expect_selection_v2((int)$selected[0]['candidate']['id'] === 3, 'Debe seleccionar primero la oportunidad de mayor prioridad.');
expect_selection_v2($selected[1]['selection']['components']['similarity_penalty'] === 0, 'Un concepto distinto no debe recibir penalización por similitud.');
$similarPool = [
  ['id' => 4] + array_replace($base, ['pedagogical_score' => 80]),
  ['id' => 5] + array_replace($base, ['pedagogical_score' => 79]),
];
$similarSelected = training_selection_v2_select($similarPool, 2, 'threats_defense');
expect_selection_v2($similarSelected[1]['selection']['components']['similarity_penalty'] === 12, 'Repetir concepto dentro del entrenamiento debe penalizarse.');

$source = file_get_contents(__DIR__ . '/../includes/coach.php');
expect_selection_v2(str_contains($source, 'training_selection_shadow_compare'), 'Coach debe ejecutar la comparación shadow.');
expect_selection_v2(str_contains($source, 'Shadow selection is observational'), 'Shadow debe aislar fallos y preservar Training legacy.');

if ($failures) {
  fwrite(STDERR, "Fallos de Training Selection v2:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: prioridad, razones, fatiga y aislamiento shadow verificados.\n";
