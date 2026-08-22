<?php

const TRAINING_TAXONOMY_VERSION = 1;
const TRAINING_CONCEPT_MIN_CONFIDENCE = 0.60;

function training_taxonomy_concepts(): array {
  return [
    'tactics_combinations' => 'Táctica y combinaciones',
    'threats_defense' => 'Amenazas y defensa',
    'king_safety_mate' => 'Seguridad del rey y mate',
    'calculation_candidates' => 'Cálculo y candidatos',
    'advantage_conversion' => 'Conversión de ventajas',
    'simplification_exchanges' => 'Simplificación e intercambios',
    'activity_coordination_initiative' => 'Actividad, coordinación e iniciativa',
    'opening_development' => 'Apertura y desarrollo',
    'pawn_structure_positional' => 'Estructura de peones y decisiones posicionales',
    'endgames_technique' => 'Finales y técnica',
  ];
}

function training_taxonomy_signal_mappings(): array {
  return [
    'smart_tag' => [
      'missed_mate' => [['king_safety_mate', .98, 'primary'], ['calculation_candidates', .68, 'secondary']],
      'allowed_mate' => [['king_safety_mate', .98, 'primary'], ['threats_defense', .82, 'secondary']],
      'opening_issue' => [['opening_development', .88, 'primary']],
      'endgame_mistake' => [['endgames_technique', .90, 'primary']],
      'lost_winning_position' => [['advantage_conversion', .94, 'primary'], ['simplification_exchanges', .64, 'secondary']],
      'converted_advantage' => [['advantage_conversion', .76, 'primary']],
      'strong_finish' => [['endgames_technique', .68, 'primary'], ['advantage_conversion', .62, 'secondary']],
      'comeback' => [['threats_defense', .64, 'primary']],
      'blunder_own' => [['calculation_candidates', .66, 'primary']],
      'mistake_own' => [['calculation_candidates', .63, 'primary']],
      'inaccuracy_own' => [['calculation_candidates', .60, 'primary']],
    ],
    'exercise_type' => [
      'find_mate' => [['king_safety_mate', .96, 'primary']],
      'spot_threat' => [['threats_defense', .90, 'primary']],
      'defend_position' => [['threats_defense', .88, 'primary']],
      'convert_advantage' => [['advantage_conversion', .92, 'primary']],
      'find_tactic' => [['tactics_combinations', .84, 'primary'], ['calculation_candidates', .66, 'secondary']],
      'avoid_blunder' => [['calculation_candidates', .78, 'primary'], ['threats_defense', .62, 'secondary']],
      'find_best_move' => [['calculation_candidates', .64, 'primary']],
      'other' => [['activity_coordination_initiative', .55, 'primary']],
    ],
    'scenario_type' => [
      'mate' => [['king_safety_mate', .97, 'primary']],
      'defense' => [['threats_defense', .92, 'primary']],
      'conversion' => [['advantage_conversion', .94, 'primary']],
    ],
    'phase' => [
      'opening' => [['opening_development', .62, 'secondary']],
      'endgame' => [['endgames_technique', .66, 'secondary']],
    ],
  ];
}

function training_taxonomy_classify(array $signals): array {
  $mappings = training_taxonomy_signal_mappings();
  $candidates = [];
  foreach ($signals as $signal) {
    $type = (string)($signal['type'] ?? '');
    $key = (string)($signal['key'] ?? '');
    foreach ($mappings[$type][$key] ?? [] as [$concept, $confidence, $role]) {
      $entry = $candidates[$concept] ?? [
        'code' => $concept,
        'confidence' => 0.0,
        'role_hint' => $role,
        'evidence' => [],
      ];
      $entry['confidence'] = min(1.0, max((float)$entry['confidence'], (float)$confidence)
        + ($entry['evidence'] ? 0.03 : 0.0));
      if ($role === 'primary') $entry['role_hint'] = 'primary';
      $entry['evidence'][] = [
        'signal_type' => $type,
        'signal_key' => $key,
        'value' => $signal['value'] ?? null,
      ];
      $candidates[$concept] = $entry;
    }
  }

  usort($candidates, static function (array $a, array $b): int {
    return ($b['confidence'] <=> $a['confidence'])
      ?: (($a['role_hint'] === 'primary' ? 0 : 1) <=> ($b['role_hint'] === 'primary' ? 0 : 1));
  });
  $primary = $candidates[0] ?? null;
  if (!$primary || $primary['confidence'] < TRAINING_CONCEPT_MIN_CONFIDENCE) {
    return ['primary' => null, 'secondary' => [], 'taxonomy_version' => TRAINING_TAXONOMY_VERSION];
  }
  $primary['role'] = 'primary';
  $secondary = [];
  foreach (array_slice($candidates, 1) as $candidate) {
    if (count($secondary) >= 2 || $candidate['confidence'] < TRAINING_CONCEPT_MIN_CONFIDENCE) break;
    $candidate['role'] = 'secondary';
    $secondary[] = $candidate;
  }
  return ['primary' => $primary, 'secondary' => $secondary, 'taxonomy_version' => TRAINING_TAXONOMY_VERSION];
}

