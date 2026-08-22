<?php
require_once __DIR__ . '/db.php';

function training_shadow_metric_summary(array $rows): array {
  $quality = [];
  $overlap = 0;
  $legacy = 0;
  $selected = 0;
  $stoppedEarly = 0;
  foreach ($rows as $row) {
    $comparison = is_array($row) ? $row : json_decode((string)$row, true);
    if (!is_array($comparison)) continue;
    if (is_numeric($comparison['average_quality'] ?? null)) $quality[] = (float)$comparison['average_quality'];
    $overlap += (int)($comparison['overlap_count'] ?? 0);
    $legacy += count($comparison['legacy_opportunity_ids'] ?? []);
    $selected += count($comparison['v2_opportunity_ids'] ?? []);
    if (!empty($comparison['composition']['stopped_early'])) $stoppedEarly++;
  }
  return [
    'runs' => count($rows),
    'average_quality' => $quality ? round(array_sum($quality) / count($quality), 2) : null,
    'overlap_rate' => min($legacy, $selected) > 0 ? round($overlap * 100 / min($legacy, $selected), 1) : null,
    'stopped_early_runs' => $stoppedEarly,
  ];
}

function training_foundation_metrics(int $userId): array {
  $statesSt = db()->prepare('SELECT publication_state,COUNT(*) total FROM training_opportunities
                             WHERE user_id=? GROUP BY publication_state');
  $statesSt->execute([$userId]);
  $states = ['published' => 0, 'reserve' => 0, 'rejected' => 0, 'inactive' => 0, 'superseded' => 0];
  foreach ($statesSt->fetchAll() as $row) $states[(string)$row['publication_state']] = (int)$row['total'];

  $canonicalSt = db()->prepare('SELECT COUNT(*) canonical_count,COALESCE(SUM(source_count),0) source_count,
                                       COALESCE(SUM(GREATEST(source_count-1,0)),0) duplicates_avoided
                                FROM (
                                  SELECT o.id,COUNT(s.id) source_count
                                  FROM training_opportunities o
                                  LEFT JOIN training_opportunity_sources s ON s.opportunity_id=o.id AND s.source_valid=1
                                  WHERE o.user_id=? GROUP BY o.id
                                ) canonical');
  $canonicalSt->execute([$userId]);
  $canonical = $canonicalSt->fetch() ?: [];

  $reasonsSt = db()->prepare('SELECT COALESCE(rejection_reason_code,"unknown") reason_code,COUNT(*) total
                              FROM training_opportunities WHERE user_id=? AND publication_state="rejected"
                              GROUP BY rejection_reason_code ORDER BY total DESC,reason_code LIMIT 8');
  $reasonsSt->execute([$userId]);
  $rejections = array_map(static fn(array $row): array => [
    'reason_code' => (string)$row['reason_code'],
    'count' => (int)$row['total'],
  ], $reasonsSt->fetchAll());

  $shadowSt = db()->prepare('SELECT comparison_json FROM training_selection_runs
                             WHERE user_id=? AND mode="shadow" AND status="done"
                             ORDER BY id DESC LIMIT 20');
  $shadowSt->execute([$userId]);
  $shadow = training_shadow_metric_summary(array_column($shadowSt->fetchAll(), 'comparison_json'));

  return [
    'inventory' => $states,
    'canonical_count' => (int)($canonical['canonical_count'] ?? 0),
    'source_count' => (int)($canonical['source_count'] ?? 0),
    'duplicates_avoided' => (int)($canonical['duplicates_avoided'] ?? 0),
    'top_rejection_reasons' => $rejections,
    'shadow' => $shadow,
  ];
}
