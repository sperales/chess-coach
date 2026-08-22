<?php
require_once __DIR__ . '/../includes/coach_decision.php';

$failures = [];
function expect_coach_decision(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$strong = coach_decision_score([
  'recent_severity' => 30, 'recurrence' => 25, 'mastery_gap' => 20, 'transfer_failure' => 15, 'trainability' => 10,
]);
expect_coach_decision($strong['score'] === 100, 'El modelo positivo aprobado debe sumar 100 como máximo bruto.');

$stable = coach_decision_score([
  'recent_severity' => 10, 'recurrence' => 5, 'mastery_gap' => 0, 'transfer_failure' => 0, 'trainability' => 10,
  'stable_without_issue_penalty' => 20, 'overexposure_penalty' => 15,
]);
expect_coach_decision($stable['score'] === -10, 'Un concepto estable y sobreexpuesto debe perder prioridad.');

$ranked = coach_decision_rank([
  ['concept_code' => 'advantage_conversion', 'recent_severity' => 10, 'trainability' => 10],
  ['concept_code' => 'threats_defense', 'recent_severity' => 25, 'recurrence' => 10, 'trainability' => 10],
]);
expect_coach_decision($ranked[0]['concept_code'] === 'threats_defense', 'La decisión debe ordenar por prioridad auditable.');
expect_coach_decision(coach_decision_label('king_safety_mate') !== 'Entrenamiento recomendado', 'La decisión debe exponer una etiqueta canónica en español.');

$override = coach_decision_has_serious_override(
  ['primary_concept_code' => 'threats_defense'],
  [[
    'concept_code' => 'king_safety_mate', 'confidence' => .75,
    'score_components' => ['recent_severity' => 25, 'recurrence' => 14],
  ]]
);
expect_coach_decision($override, 'Una señal nueva, seria, repetida y fiable debe permitir reevaluar el foco antes del plazo.');
$weakOverride = coach_decision_has_serious_override(
  ['primary_concept_code' => 'threats_defense'],
  [[
    'concept_code' => 'king_safety_mate', 'confidence' => .45,
    'score_components' => ['recent_severity' => 25, 'recurrence' => 14],
  ]]
);
expect_coach_decision(!$weakOverride, 'Una señal de baja confianza no debe desestabilizar el foco actual.');

$coachSource = file_get_contents(__DIR__ . '/../includes/coach.php');
expect_coach_decision(str_contains($coachSource, "'coach_decision' => \$coachDecision"), 'Training debe consumir la Coach Decision única.');
expect_coach_decision(str_contains($coachSource, 'Coach Decision unavailable'), 'El rollout debe conservar compatibilidad si falta la migración.');

if ($failures) {
  fwrite(STDERR, "Fallos de Coach Decision:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: scoring, penalizaciones, orden y consumo unificado verificados.\n";
