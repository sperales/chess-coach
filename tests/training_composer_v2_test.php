<?php
require_once __DIR__ . '/../includes/training_composer_v2.php';

$failures = [];
function expect_composer(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}
function composer_item(int $id, int $quality, string $concept, string $format, string $reason, ?string $canonical = null): array {
  return [
    'candidate' => [
      'id' => $id, 'canonical_key' => $canonical ?? 'key-' . $id, 'pedagogical_score' => $quality,
      'primary_concept_code' => $concept, 'recommended_format' => $format,
      'publication_state' => 'published', 'currency_state' => 'current',
    ],
    'selection' => ['selection_priority' => $quality, 'reason_code' => $reason, 'components' => []],
  ];
}

expect_composer(training_composer_profile_for_target(3)['code'] === 'short', 'Tres actividades deben usar perfil corto.');
expect_composer(training_composer_profile_for_target(5)['code'] === 'standard', 'Cinco actividades deben usar perfil estándar.');
expect_composer(training_composer_profile_for_target(8)['code'] === 'long', 'Ocho actividades deben usar perfil largo.');

$ranked = [
  composer_item(1, 90, 'threats_defense', 'flash', 'primary_focus_new'),
  composer_item(2, 86, 'threats_defense', 'scenario', 'due_review'),
  composer_item(3, 82, 'threats_defense', 'flash', 'previous_failure'),
  composer_item(4, 79, 'calculation_candidates', 'scenario', 'secondary_focus'),
  composer_item(5, 75, 'endgames_technique', 'flash', 'maintenance'),
  composer_item(6, 74, 'conversion_technique', 'flash', 'recent_game'),
  composer_item(7, 72, 'opening_development', 'scenario', 'recent_game'),
];
$standard = training_composer_v2_compose($ranked, training_composer_profile_for_target(5), 'threats_defense', 'calculation_candidates');
expect_composer($standard['selected_count'] === 5, 'El perfil estándar debe seleccionar cinco oportunidades de calidad cuando existen.');
expect_composer($standard['mix']['primary_focus'] >= 3, 'El foco principal debe ocupar al menos el 60 % del plan estándar disponible.');
expect_composer($standard['mix']['review'] >= 3, 'El plan debe priorizar repaso/recuperación sobre novedad.');
expect_composer($standard['mix']['recent_game'] <= 1, 'Solo debe entrar una oportunidad fuerte de partida reciente.');

$lowQuality = [
  composer_item(10, 80, 'threats_defense', 'flash', 'due_review', 'same'),
  composer_item(11, 79, 'threats_defense', 'scenario', 'previous_failure', 'same'),
  composer_item(12, 64, 'calculation_candidates', 'flash', 'maintenance'),
];
$short = training_composer_v2_compose($lowQuality, training_composer_profile_for_target(3), 'threats_defense');
expect_composer($short['selected_count'] === 1, 'No debe duplicar una posición canónica ni rellenar con calidad de reserva.');
expect_composer($short['stopped_early'] === true, 'Una sesión corta y buena debe preferirse a rellenar la cuota.');

if ($failures) {
  fwrite(STDERR, "Fallos de Training Composer v2:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: perfiles, mezcla, calidad, deduplicación y parada temprana verificados.\n";
