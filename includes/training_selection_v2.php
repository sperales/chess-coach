<?php
require_once __DIR__ . '/training_opportunities.php';
require_once __DIR__ . '/training_composer_v2.php';

const TRAINING_SELECTION_VERSION = 1;

function training_selection_mode(): string {
  $mode = strtolower((string)(app_config()['training_selection_mode'] ?? 'shadow'));
  if ($mode === 'disabled') $mode = 'legacy';
  return in_array($mode, ['legacy', 'shadow', 'active'], true) ? $mode : 'shadow';
}

function training_selection_priority(array $candidate, ?string $primaryFocus = null, ?string $secondaryFocus = null, array $sessionConcepts = []): array {
  $due = !empty($candidate['next_review_at']) && strtotime((string)$candidate['next_review_at']) <= time() ? 20 : 0;
  $focus = ($candidate['primary_concept_code'] ?? '') === $primaryFocus ? 20
    : (($candidate['primary_concept_code'] ?? '') === $secondaryFocus ? 10 : 0);
  $recentGame = !empty($candidate['source_completed_at']) && strtotime((string)$candidate['source_completed_at']) >= strtotime('-14 days') ? 10 : 0;
  $recovery = ($candidate['last_training_result'] ?? '') === 'failed' ? 15 : 0;
  $fatigue = !empty($candidate['last_selected_at']) && strtotime((string)$candidate['last_selected_at']) >= strtotime('-3 days') ? 15 : 0;
  $similarity = in_array((string)($candidate['primary_concept_code'] ?? ''), $sessionConcepts, true) ? 12 : 0;
  $priority = (int)($candidate['pedagogical_score'] ?? 0) + $due + $focus + $recentGame + $recovery - $fatigue - $similarity;
  $reason = $recovery ? 'previous_failure' : ($due ? 'due_review' : ($focus >= 20 ? 'primary_focus_new'
    : ($focus ? 'secondary_focus' : ($recentGame ? 'recent_game' : 'maintenance'))));
  return [
    'selection_priority' => $priority,
    'reason_code' => $reason,
    'components' => [
      'due_review_bonus' => $due, 'focus_bonus' => $focus, 'recent_game_bonus' => $recentGame,
      'recovery_bonus' => $recovery, 'fatigue_penalty' => $fatigue, 'similarity_penalty' => $similarity,
    ],
    'selection_version' => TRAINING_SELECTION_VERSION,
  ];
}

function training_selection_v2_pool(int $userId, int $limit = 200): array {
  $limit = max(1, min(500, $limit));
  $sql = 'SELECT o.*,
            MAX(a.completed_at) source_completed_at,
            MAX(s.exercise_id) source_exercise_id,
            MAX(s.scenario_id) source_scenario_id,
            MAX(CASE WHEN te.last_training_result="failed" THEN "failed" ELSE NULL END) last_training_result
          FROM training_opportunities o
          LEFT JOIN training_opportunity_sources s ON s.opportunity_id=o.id AND s.source_valid=1
          LEFT JOIN game_analysis a ON a.id=s.analysis_id
          LEFT JOIN training_exercises te ON te.id=s.exercise_id
          WHERE o.user_id=? AND o.publication_state="published" AND o.currency_state="current"
            AND o.recommended_format IN ("flash","scenario")
          GROUP BY o.id
          ORDER BY o.pedagogical_score DESC,o.id DESC LIMIT ' . $limit;
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  return $st->fetchAll();
}

function training_selection_reason_text(string $reasonCode): string {
  return match ($reasonCode) {
    'previous_failure' => 'Recupera una posición anterior que todavía necesita consolidación.',
    'due_review' => 'Repaso programado para conservar lo aprendido.',
    'primary_focus_new' => 'Trabaja directamente el foco principal definido por el Coach.',
    'secondary_focus' => 'Refuerza un foco secundario sin desplazar la prioridad principal.',
    'recent_game' => 'Conecta el entrenamiento con una partida reciente.',
    default => 'Mantiene una práctica equilibrada con una oportunidad de calidad.',
  };
}

function training_selection_v2_blueprint(array $legacyBlueprint, array $selected): array {
  $items = [];
  foreach ($selected as $selectedItem) {
    $candidate = $selectedItem['candidate'];
    $format = (string)$candidate['recommended_format'];
    $exerciseId = $format === 'flash' ? (int)($candidate['source_exercise_id'] ?? 0) : 0;
    $scenarioId = $format === 'scenario' ? (int)($candidate['source_scenario_id'] ?? 0) : 0;
    if (($format === 'flash' && !$exerciseId) || ($format === 'scenario' && !$scenarioId)) continue;
    $items[] = [
      'position' => count($items) + 1,
      'item_type' => $format,
      'exercise_id' => $exerciseId ?: null,
      'scenario_id' => $scenarioId ?: null,
      'opportunity_id' => (int)$candidate['id'],
      'concept_code' => (string)$candidate['primary_concept_code'],
      'reason' => training_selection_reason_text((string)$selectedItem['selection']['reason_code']),
      'selection_reason_code' => (string)$selectedItem['selection']['reason_code'],
      'selection_version' => TRAINING_SELECTION_VERSION,
      'evidence' => ['selection_priority' => $selectedItem['selection']['selection_priority'], 'components' => $selectedItem['selection']['components']],
    ];
  }
  if (!$items) return $legacyBlueprint;
  $blueprint = $legacyBlueprint;
  $blueprint['coach_version'] = max(2, (int)($blueprint['coach_version'] ?? 1));
  $blueprint['items'] = $items;
  $blueprint['item_count'] = count($items);
  $blueprint['estimated_duration_min'] = max(3, (int)ceil(array_sum(array_map(static fn(array $item): int => $item['item_type'] === 'scenario' ? 3 : 1, $items)) * 1.4));
  return $blueprint;
}

function training_selection_v2_select(array $pool, int $limit, ?string $primaryFocus = null, ?string $secondaryFocus = null): array {
  $ranked = [];
  $sessionConcepts = [];
  while ($pool && count($ranked) < $limit) {
    $bestIndex = null;
    $best = null;
    foreach ($pool as $index => $candidate) {
      $selection = training_selection_priority($candidate, $primaryFocus, $secondaryFocus, $sessionConcepts);
      if ($best === null || $selection['selection_priority'] > $best['selection']['selection_priority']) {
        $bestIndex = $index;
        $best = ['candidate' => $candidate, 'selection' => $selection];
      }
    }
    if ($bestIndex === null) break;
    array_splice($pool, $bestIndex, 1);
    $best['rank'] = count($ranked) + 1;
    $ranked[] = $best;
    $sessionConcepts[] = (string)$best['candidate']['primary_concept_code'];
  }
  return $ranked;
}

function training_selection_legacy_opportunity_ids(array $blueprint): array {
  $ids = [];
  foreach ($blueprint['items'] ?? [] as $item) {
    $table = ($item['item_type'] ?? 'flash') === 'scenario' ? 'training_scenarios' : 'training_exercises';
    $id = ($item['item_type'] ?? 'flash') === 'scenario' ? (int)($item['scenario_id'] ?? 0) : (int)($item['exercise_id'] ?? 0);
    if (!$id) continue;
    $st = db()->prepare("SELECT opportunity_id FROM {$table} WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $opportunityId = (int)($st->fetchColumn() ?: 0);
    if ($opportunityId) $ids[] = $opportunityId;
  }
  return $ids;
}

function training_selection_shadow_compare(int $userId, array $legacyBlueprint, ?string $primaryFocus = null, ?string $secondaryFocus = null): ?array {
  $mode = training_selection_mode();
  if ($mode === 'legacy' || !training_foundation_available()) return null;
  $pool = training_selection_v2_pool($userId);
  $target = max(1, (int)($legacyBlueprint['item_count'] ?? 5));
  $ranked = training_selection_v2_select($pool, min(count($pool), max($target * 4, $target)), $primaryFocus, $secondaryFocus);
  $composition = training_composer_v2_compose($ranked, training_composer_profile_for_target($target), $primaryFocus, $secondaryFocus);
  $selected = $composition['selected'];
  $legacyIds = training_selection_legacy_opportunity_ids($legacyBlueprint);
  $v2Ids = array_map(static fn(array $item): int => (int)$item['candidate']['id'], $selected);
  $comparison = [
    'legacy_opportunity_ids' => $legacyIds,
    'v2_opportunity_ids' => $v2Ids,
    'overlap_count' => count(array_intersect($legacyIds, $v2Ids)),
    'legacy_unmapped_count' => max(0, count($legacyBlueprint['items'] ?? []) - count($legacyIds)),
    'concepts' => array_values(array_unique(array_map(static fn(array $item): string => (string)$item['candidate']['primary_concept_code'], $selected))),
    'average_quality' => $selected ? round(array_sum(array_map(static fn(array $item): int => (int)$item['candidate']['pedagogical_score'], $selected)) / count($selected), 2) : null,
    'composition' => ['profile' => $composition['profile']['code'], 'mix' => $composition['mix'], 'stopped_early' => $composition['stopped_early']],
  ];
  $run = db()->prepare('INSERT INTO training_selection_runs
    (user_id,mode,legacy_version,selection_version,pool_size,legacy_count,selected_count,comparison_json,status)
    VALUES (?,?,1,?,?,?,?,?,"done")');
  $run->execute([$userId,$mode,TRAINING_SELECTION_VERSION,count($pool),count($legacyBlueprint['items'] ?? []),count($selected),json_encode($comparison)]);
  $runId = (int)db()->lastInsertId();
  $insert = db()->prepare('INSERT INTO training_selection_items
    (selection_run_id,opportunity_id,rank_no,selection_priority,reason_code,reason_evidence_json,
     due_review_bonus,focus_bonus,recent_game_bonus,recovery_bonus,fatigue_penalty,similarity_penalty)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
  foreach ($selected as $item) {
    $components = $item['selection']['components'];
    $insert->execute([
      $runId,(int)$item['candidate']['id'],$item['rank'],$item['selection']['selection_priority'],$item['selection']['reason_code'],
      json_encode(['primary_focus' => $primaryFocus, 'secondary_focus' => $secondaryFocus]),
      $components['due_review_bonus'],$components['focus_bonus'],$components['recent_game_bonus'],$components['recovery_bonus'],
      $components['fatigue_penalty'],$components['similarity_penalty'],
    ]);
  }
  return ['run_id' => $runId, 'mode' => $mode, 'comparison' => $comparison, 'selected' => $selected, 'composition' => $composition,
    'blueprint' => $mode === 'active' ? training_selection_v2_blueprint($legacyBlueprint, $selected) : null];
}
