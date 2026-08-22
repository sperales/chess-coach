<?php
require_once __DIR__ . '/../includes/training_quality.php';

$failures = [];
function expect_training_quality(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$taxonomy = training_taxonomy_classify([
  ['type' => 'exercise_type', 'key' => 'find_mate'],
  ['type' => 'smart_tag', 'key' => 'missed_mate'],
  ['type' => 'smart_tag', 'key' => 'blunder_own'],
]);
expect_training_quality(($taxonomy['primary']['code'] ?? '') === 'king_safety_mate', 'Mate debe mapear a seguridad del rey como concepto principal.');
expect_training_quality(count($taxonomy['secondary']) <= 2, 'Una oportunidad no debe tener más de dos conceptos secundarios.');
expect_training_quality(($taxonomy['primary']['confidence'] ?? 0) >= .60, 'El concepto principal debe conservar confidence suficiente.');
expect_training_quality(!empty($taxonomy['primary']['evidence']), 'La clasificación debe conservar evidence estructurada.');

$fen = training_canonical_normalize_fen('8/8/8/8/8/8/4K3/7k w - - 7 42');
expect_training_quality($fen === '8/8/8/8/8/8/4K3/7k w - -', 'La identidad canónica debe ignorar contadores de FEN.');
$identityA = training_canonical_identity([
  'fen' => '8/8/8/8/8/8/4K3/7k w - - 0 1', 'solution_uci' => 'e2e3',
  'primary_concept_code' => 'endgames_technique', 'objective_code' => 'find_defense',
  'objective_data' => ['b' => 2, 'a' => 1], 'accepted_alternatives' => ['e2f2'],
]);
$identityB = training_canonical_identity([
  'fen' => '8/8/8/8/8/8/4K3/7k w - - 9 80', 'solution_uci' => 'e2e3',
  'primary_concept_code' => 'endgames_technique', 'objective_code' => 'find_defense',
  'objective_data' => ['a' => 1, 'b' => 2], 'accepted_alternatives' => ['e2f2'],
]);
expect_training_quality(($identityA['hash'] ?? '') === ($identityB['hash'] ?? ''), 'La canonicalización debe ser estable ante contadores y orden de claves.');

$base = [
  'analysis_current' => true, 'fen' => '8/8/8/8/8/8/4K3/7k w - -', 'solution_uci' => 'e2e3',
  'engine_complete' => true, 'concept_confidence_value' => .90, 'equivalent_alternatives' => 1,
  'meaningful_decisions' => 1, 'objective_evaluable' => true, 'score_type' => 'cp', 'score' => 0,
  'ply' => 30, 'source_side' => 'user', 'evaluation_cp' => 0, 'objective_code' => 'find_defense',
  'relevance' => 22, 'concept_confidence_score' => 14, 'decision_clarity' => 15,
  'pedagogical_value' => 14, 'recurrence' => 6, 'adaptive_fit' => 8, 'novelty' => 4,
  'format_suitability' => 5, 'ambiguity_penalty' => 0, 'redundancy_penalty' => 0,
  'complexity_penalty' => 0, 'overexposure_penalty' => 0,
];
$good = training_quality_score($base);
expect_training_quality(!$good['hard_reject'] && $good['publication_state'] === 'published', 'Una defensa clara y relevante debe poder publicarse aunque la evaluación no sea negativa.');
expect_training_quality($good['score'] <= 100, 'El score debe estar limitado a 100.');

$opponent = training_quality_score(array_replace($base, ['source_side' => 'opponent', 'opponent_relevance' => false]));
expect_training_quality(($opponent['reason_code'] ?? '') === 'opponent_without_user_relevance', 'Una jugada rival sin relación pedagógica debe rechazarse.');
$deepMate = training_quality_score(array_replace($base, ['score_type' => 'mate', 'score' => 16]));
expect_training_quality(($deepMate['reason_code'] ?? '') === 'mate_too_deep', 'Mate en 16 no debe publicarse en v1.');
$extreme = training_quality_score(array_replace($base, ['evaluation_cp' => 1400, 'objective_code' => 'maintain_advantage']));
expect_training_quality(($extreme['reason_code'] ?? '') === 'generic_extreme_advantage', 'Una ventaja extrema con objetivo genérico debe rechazarse.');
$duplicate = training_quality_score(array_replace($base, ['canonical_duplicate_better' => true]));
expect_training_quality(($duplicate['reason_code'] ?? '') === 'canonical_duplicate', 'Un duplicado exacto mejor representado debe rechazarse antes del score.');

expect_training_quality(training_quality_route_format($base) === 'flash', 'Una decisión debe enrutar a Flash.');
expect_training_quality(training_quality_route_format(array_replace($base, ['meaningful_decisions' => 2, 'critical_reply_required' => true])) === 'scenario', 'Varias decisiones con respuesta crítica deben enrutar a Scenario.');
expect_training_quality(training_quality_route_format(array_replace($base, ['meaningful_decisions' => 4])) === 'none', 'Más de tres decisiones no deben publicarse en los formatos v1.');

if ($failures) {
  fwrite(STDERR, "Fallos de Training Quality Foundation:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: taxonomía, canonicalización, filtros, score y routing verificados.\n";
