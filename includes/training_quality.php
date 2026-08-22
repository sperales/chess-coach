<?php
require_once __DIR__ . '/training_taxonomy.php';
require_once __DIR__ . '/training_canonical.php';

const TRAINING_FILTER_VERSION = 1;
const TRAINING_SCORING_VERSION = 1;
const TRAINING_DIFFICULTY_VERSION = 1;
const TRAINING_FORMAT_VERSION = 1;

function training_quality_config(): array {
  return [
    'concept_min_confidence' => .60,
    'max_equivalent_alternatives' => 4,
    'max_meaningful_decisions' => 3,
    'max_mate_player_moves' => 6,
    'opening_max_ply' => 16,
    'extreme_advantage_cp' => 1200,
    'published_threshold' => 65,
    'reserve_threshold' => 50,
  ];
}

function training_quality_hard_filter(array $candidate): ?array {
  $config = training_quality_config();
  $reject = static fn(string $code, array $evidence = []): array => [
    'reason_code' => $code,
    'evidence' => $evidence,
    'filter_version' => TRAINING_FILTER_VERSION,
  ];
  if (empty($candidate['analysis_current'])) return $reject('analysis_not_current');
  if (!training_canonical_normalize_fen((string)($candidate['fen'] ?? ''))
      || !training_canonical_normalize_uci($candidate['solution_uci'] ?? null)) {
    return $reject('invalid_position_or_solution');
  }
  if (empty($candidate['engine_complete'])) return $reject('engine_analysis_incomplete');
  if ((float)($candidate['concept_confidence_value'] ?? 0) < $config['concept_min_confidence']) {
    return $reject('concept_confidence_too_low', ['confidence' => (float)($candidate['concept_confidence_value'] ?? 0)]);
  }
  if (!empty($candidate['canonical_duplicate_better'])) return $reject('canonical_duplicate');
  if ((int)($candidate['equivalent_alternatives'] ?? 1) > $config['max_equivalent_alternatives']
      && empty($candidate['objective_evaluable'])) {
    return $reject('excessive_ambiguity');
  }
  if ((int)($candidate['meaningful_decisions'] ?? 1) > $config['max_meaningful_decisions']
      || !empty($candidate['depth_disproportionate'])) {
    return $reject('complexity_out_of_scope');
  }
  if (($candidate['score_type'] ?? '') === 'mate'
      && abs((int)($candidate['score'] ?? 0)) > $config['max_mate_player_moves']) {
    return $reject('mate_too_deep');
  }
  if ((int)($candidate['ply'] ?? 99) <= $config['opening_max_ply']
      && empty($candidate['opening_conceptual_value'])
      && empty($candidate['recurrence_count'])) {
    return $reject('ordinary_early_opening');
  }
  if (!empty($candidate['played_move_accepted']) && empty($candidate['additional_teaching_purpose'])) {
    return $reject('played_move_still_accepted');
  }
  if (($candidate['source_side'] ?? 'user') === 'opponent' && empty($candidate['opponent_relevance'])) {
    return $reject('opponent_without_user_relevance');
  }
  if (abs((int)($candidate['evaluation_cp'] ?? 0)) >= $config['extreme_advantage_cp']
      && ($candidate['objective_code'] ?? '') === 'maintain_advantage'
      && empty($candidate['specific_teaching_purpose'])) {
    return $reject('generic_extreme_advantage');
  }
  return null;
}

function training_quality_route_format(array $candidate): string {
  $decisions = (int)($candidate['meaningful_decisions'] ?? 0);
  if (empty($candidate['objective_evaluable'])) return 'none';
  if ($decisions === 1) return 'flash';
  if ($decisions >= 2 && $decisions <= 3 && !empty($candidate['critical_reply_required'])) return 'scenario';
  return 'none';
}

function training_quality_estimate_difficulty(array $candidate): array {
  $points = 0;
  $points += min(3, max(0, (int)($candidate['equivalent_alternatives'] ?? 1) - 1));
  $points += min(4, max(0, (int)($candidate['meaningful_decisions'] ?? 1) - 1) * 2);
  $points += !empty($candidate['only_move']) ? 2 : 0;
  $points += (int)($candidate['alternative_gap_cp'] ?? 200) < 80 ? 2 : 0;
  $points += in_array($candidate['primary_concept_code'] ?? '', ['calculation_candidates', 'pawn_structure_positional'], true) ? 1 : 0;
  $difficulty = $points >= 8 ? 'critical' : ($points >= 5 ? 'hard' : ($points >= 2 ? 'medium' : 'easy'));
  return ['difficulty' => $difficulty, 'points' => $points, 'version' => TRAINING_DIFFICULTY_VERSION];
}

function training_quality_score(array $candidate): array {
  $hardReject = training_quality_hard_filter($candidate);
  if ($hardReject) return ['hard_reject' => true] + $hardReject;

  $limits = [
    'relevance' => 25, 'concept_confidence' => 15, 'decision_clarity' => 15,
    'pedagogical_value' => 15, 'recurrence' => 10, 'adaptive_fit' => 10,
    'novelty' => 5, 'format_suitability' => 5, 'ambiguity_penalty' => 20,
    'redundancy_penalty' => 25, 'complexity_penalty' => 15, 'overexposure_penalty' => 15,
  ];
  $components = [];
  foreach ($limits as $key => $limit) {
    $inputKey = $key === 'concept_confidence' ? 'concept_confidence_score' : $key;
    $components[$key] = max(0, min($limit, (int)($candidate[$inputKey] ?? 0)));
  }
  $positive = $components['relevance'] + $components['concept_confidence'] + $components['decision_clarity']
    + $components['pedagogical_value'] + $components['recurrence'] + $components['adaptive_fit']
    + $components['novelty'] + $components['format_suitability'];
  $penalties = $components['ambiguity_penalty'] + $components['redundancy_penalty']
    + $components['complexity_penalty'] + $components['overexposure_penalty'];
  $total = max(0, min(100, $positive - $penalties));
  $config = training_quality_config();
  $state = $total >= $config['published_threshold'] ? 'published'
    : ($total >= $config['reserve_threshold'] ? 'reserve' : 'rejected');
  return [
    'hard_reject' => false,
    'score' => $total,
    'publication_state' => $state,
    'components' => $components,
    'scoring_version' => TRAINING_SCORING_VERSION,
  ];
}
