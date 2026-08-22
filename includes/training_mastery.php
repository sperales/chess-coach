<?php
require_once __DIR__ . '/db.php';

const TRAINING_MASTERY_ALGORITHM_VERSION = 1;
const TRAINING_REVIEW_RULE_VERSION = 1;

function training_mastery_recent_state(array $events): string {
  $recent = array_slice($events, -5);
  if (!$recent) return 'normal';
  $quality = array_values(array_filter(array_map(static fn(array $event) => $event['quality_score'] ?? null, $recent), 'is_numeric'));
  $average = $quality ? array_sum($quality) / count($quality) : 0.0;
  $failures = count(array_filter($recent, static fn(array $event): bool => ($event['result_code'] ?? '') === 'failed'));
  if ($failures >= 3 || $average < 40) return 'priority_review';
  if ($failures >= 2 || $average < 60) return 'attention';
  if (count($recent) >= 3 && $failures === 0 && $average >= 80) return 'in_form';
  return 'normal';
}

function training_mastery_calculate(array $events, ?string $previousState = null, ?int $now = null): array {
  $now ??= time();
  $valid = array_values(array_filter($events, static fn(array $event): bool => in_array(($event['result_code'] ?? ''), ['solved', 'failed', 'completed'], true)));
  usort($valid, static fn(array $a, array $b): int => strtotime((string)$a['completed_at']) <=> strtotime((string)$b['completed_at']));
  $dates = array_values(array_unique(array_map(static fn(array $event): string => date('Y-m-d', strtotime((string)$event['completed_at'])), $valid)));
  $firstAt = $valid ? strtotime((string)$valid[0]['completed_at']) : $now;
  $lastAt = $valid ? strtotime((string)$valid[count($valid) - 1]['completed_at']) : null;
  $windowDays = $valid ? max(1, (int)floor(($lastAt - $firstAt) / 86400) + 1) : 0;
  $weightedQuality = 0.0;
  $totalWeight = 0.0;
  $weightedAutonomy = 0.0;
  $autonomous = 0;
  $highHints = 0;
  $delayed3 = 0;
  $delayed7 = 0;
  $previousAt = null;
  foreach ($valid as $event) {
    $weight = max(0.1, (float)($event['evidence_weight'] ?? 1.0));
    $quality = max(0.0, min(100.0, (float)($event['quality_score'] ?? 0.0)));
    $weightedQuality += $quality * $weight;
    $totalWeight += $weight;
    if (($event['result_code'] ?? '') !== 'failed' && (int)($event['attempts_count'] ?? 1) <= 1 && (int)($event['highest_hint_level'] ?? 0) === 0) {
      $autonomous++;
      $weightedAutonomy += $weight;
    }
    if ((int)($event['highest_hint_level'] ?? 0) >= 2) $highHints++;
    $at = strtotime((string)$event['completed_at']);
    if ($previousAt !== null && ($event['result_code'] ?? '') !== 'failed') {
      $gap = ($at - $previousAt) / 86400;
      if ($gap >= 3) $delayed3++;
      if ($gap >= 7) $delayed7++;
    }
    $previousAt = $at;
  }
  $count = count($valid);
  $averageQuality = $totalWeight > 0 ? round($weightedQuality / $totalWeight, 2) : null;
  $adjusted = $totalWeight > 0 ? round(($weightedAutonomy / $totalWeight) * 100, 2) : null;
  $state = 'starting';
  if ($count >= 10 && count($dates) >= 5 && $windowDays >= 21 && $adjusted >= 75 && $delayed7 >= 2 && $highHints <= max(1, (int)floor($count * 0.2))) {
    $state = 'stable';
  } elseif ($count >= 6 && count($dates) >= 3 && $windowDays >= 7 && $adjusted >= 65 && $delayed3 >= 2) {
    $state = 'consolidating';
  } elseif ($count >= 3 && count($dates) >= 2) {
    $state = 'learning';
  }
  // One weak result never demotes stable evidence; persistent evidence still can.
  if ($previousState === 'stable' && $state !== 'stable') {
    $recent = array_slice($valid, -5);
    $recentFailures = count(array_filter($recent, static fn(array $event): bool => ($event['result_code'] ?? '') === 'failed'));
    $recentHighHints = count(array_filter($recent, static fn(array $event): bool => (int)($event['highest_hint_level'] ?? 0) >= 2));
    if ($recentFailures < 2 && $recentHighHints < 3) $state = 'stable';
  }
  $inactiveDays = $lastAt ? (int)floor(($now - $lastAt) / 86400) : 0;
  $confidence = min(1.0, ($count / 10) * 0.55 + (count($dates) / 5) * 0.25 + min(0.2, $windowDays / 105));
  if ($inactiveDays >= 90) $confidence *= 0.45;
  elseif ($inactiveDays >= 60) $confidence *= 0.70;
  return [
    'mastery_state' => $state,
    'recent_performance_state' => training_mastery_recent_state($valid),
    'confidence' => round(max(0.0, min(1.0, $confidence)), 3),
    'opportunity_count' => $count,
    'distinct_training_dates' => count($dates),
    'autonomous_success_count' => $autonomous,
    'delayed_review_success_count' => $delayed7,
    'adjusted_autonomy_rate' => $adjusted,
    'last_trained_at' => $lastAt ? date('Y-m-d H:i:s', $lastAt) : null,
    'review_pending' => $inactiveDays >= 30,
    'confirmation_required' => $inactiveDays >= 90,
    'evidence' => ['window_days' => $windowDays, 'average_quality' => $averageQuality, 'high_hint_count' => $highHints,
      'delayed_3d' => $delayed3, 'delayed_7d' => $delayed7, 'inactive_days' => $inactiveDays],
  ];
}

function training_review_schedule(string $result, int $attempts, int $hintLevel, string $masteryState, ?string $completedAt = null): array {
  $baseDays = ['starting' => 1, 'learning' => 3, 'consolidating' => 7, 'stable' => 21][$masteryState] ?? 1;
  if ($result === 'failed') $days = 1;
  elseif ($hintLevel >= 3) $days = 1;
  elseif ($hintLevel >= 2 || $attempts >= 4) $days = max(1, (int)floor($baseDays / 3));
  elseif ($hintLevel >= 1 || $attempts >= 2) $days = max(1, (int)floor($baseDays / 2));
  else $days = $baseDays;
  $timestamp = strtotime($completedAt ?: 'now') ?: time();
  return [
    'next_review_at' => date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $timestamp)),
    'interval_days' => $days,
    'reason_code' => $result === 'failed' ? 'failed_recovery' : ($hintLevel >= 2 ? 'supported_review' : ($attempts >= 2 ? 'attempted_review' : 'autonomous_review')),
    'review_rule_version' => TRAINING_REVIEW_RULE_VERSION,
  ];
}

function training_mastery_events_for_concept(int $userId, string $conceptCode): array {
  $sql = 'SELECT "solve" source_type,r.id source_id,r.status result_code,r.quality_score,r.evidence_weight,
                 r.attempts_count,r.highest_hint_level,r.completed_at
          FROM training_solve_runs r
          JOIN training_exercises e ON e.id=r.exercise_id
          JOIN training_opportunities o ON o.id=e.opportunity_id
          WHERE r.user_id=? AND o.primary_concept_code=? AND r.status IN ("solved","failed")
          UNION ALL
          SELECT "scenario",r.id,r.status,r.quality_score,r.evidence_weight,r.attempts_count,r.highest_hint_level,r.completed_at
          FROM training_scenario_runs r
          JOIN training_scenarios s ON s.id=r.scenario_id
          JOIN training_opportunities o ON o.id=s.opportunity_id
          WHERE r.user_id=? AND o.primary_concept_code=? AND r.status IN ("completed","failed")
          ORDER BY completed_at';
  $st = db()->prepare($sql);
  $st->execute([$userId, $conceptCode, $userId, $conceptCode]);
  return $st->fetchAll();
}

function training_mastery_recalculate(int $userId, string $conceptCode, ?array $source = null): ?array {
  if ($userId <= 0 || $conceptCode === '') return null;
  $previousSt = db()->prepare('SELECT * FROM training_concept_mastery WHERE user_id=? AND concept_code=?');
  $previousSt->execute([$userId, $conceptCode]);
  $previous = $previousSt->fetch() ?: null;
  $events = training_mastery_events_for_concept($userId, $conceptCode);
  $calculated = training_mastery_calculate($events, $previous['mastery_state'] ?? null);
  $last = $events ? $events[count($events) - 1] : null;
  $schedule = $last ? training_review_schedule(
    (string)$last['result_code'], (int)$last['attempts_count'], (int)$last['highest_hint_level'], $calculated['mastery_state'], (string)$last['completed_at']
  ) : ['next_review_at' => null];
  $evidence = $calculated['evidence'] + ['confirmation_required' => $calculated['confirmation_required']];
  db()->prepare('INSERT INTO training_concept_mastery
      (user_id,concept_code,mastery_state,recent_performance_state,confidence,opportunity_count,distinct_training_dates,
       autonomous_success_count,delayed_review_success_count,adjusted_autonomy_rate,last_trained_at,next_review_at,review_pending,
       evidence_json,mastery_algorithm_version,recent_algorithm_version,created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())
      ON DUPLICATE KEY UPDATE mastery_state=VALUES(mastery_state),recent_performance_state=VALUES(recent_performance_state),
       confidence=VALUES(confidence),opportunity_count=VALUES(opportunity_count),distinct_training_dates=VALUES(distinct_training_dates),
       autonomous_success_count=VALUES(autonomous_success_count),delayed_review_success_count=VALUES(delayed_review_success_count),
       adjusted_autonomy_rate=VALUES(adjusted_autonomy_rate),last_trained_at=VALUES(last_trained_at),next_review_at=VALUES(next_review_at),
       review_pending=VALUES(review_pending),evidence_json=VALUES(evidence_json),mastery_algorithm_version=VALUES(mastery_algorithm_version),
       recent_algorithm_version=VALUES(recent_algorithm_version),updated_at=NOW()')
    ->execute([$userId,$conceptCode,$calculated['mastery_state'],$calculated['recent_performance_state'],$calculated['confidence'],
      $calculated['opportunity_count'],$calculated['distinct_training_dates'],$calculated['autonomous_success_count'],
      $calculated['delayed_review_success_count'],$calculated['adjusted_autonomy_rate'],$calculated['last_trained_at'],
      $schedule['next_review_at'],$calculated['review_pending'] ? 1 : 0,json_encode($evidence, JSON_UNESCAPED_UNICODE),TRAINING_MASTERY_ALGORITHM_VERSION]);
  if ($last && !empty($source['opportunity_id'])) {
    db()->prepare('UPDATE training_opportunities SET next_review_at=?,review_rule_version=? WHERE id=? AND user_id=?')
      ->execute([$schedule['next_review_at'],TRAINING_REVIEW_RULE_VERSION,(int)$source['opportunity_id'],$userId]);
  }
  $sourceType = (string)($source['source_type'] ?? 'recalculate');
  $sourceId = (int)($source['source_id'] ?? 0);
  $eventKey = $sourceType . ':' . $sourceId . ':concept:' . $conceptCode . ':v' . TRAINING_MASTERY_ALGORITHM_VERSION;
  db()->prepare('INSERT INTO training_mastery_events
      (user_id,event_key,opportunity_id,concept_code,solve_run_id,scenario_run_id,result_code,previous_state,resulting_state,evidence_json,mastery_algorithm_version)
      VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE resulting_state=VALUES(resulting_state),evidence_json=VALUES(evidence_json)')
    ->execute([$userId,$eventKey,$source['opportunity_id'] ?? null,$conceptCode,$sourceType === 'solve' ? $sourceId : null,
      $sourceType === 'scenario' ? $sourceId : null,(string)($last['result_code'] ?? 'recalculated'),$previous['mastery_state'] ?? null,
      $calculated['mastery_state'],json_encode(['calculated' => $calculated, 'schedule' => $schedule], JSON_UNESCAPED_UNICODE),TRAINING_MASTERY_ALGORITHM_VERSION]);
  return $calculated + ['concept_code' => $conceptCode, 'next_review_at' => $schedule['next_review_at']];
}

function training_mastery_record_source(int $userId, string $sourceType, int $sourceId): ?array {
  $table = $sourceType === 'scenario' ? 'training_scenarios' : 'training_exercises';
  $runTable = $sourceType === 'scenario' ? 'training_scenario_runs' : 'training_solve_runs';
  $foreign = $sourceType === 'scenario' ? 'scenario_id' : 'exercise_id';
  $st = db()->prepare("SELECT o.id opportunity_id,o.primary_concept_code FROM {$runTable} r JOIN {$table} t ON t.id=r.{$foreign} JOIN training_opportunities o ON o.id=t.opportunity_id WHERE r.id=? AND r.user_id=? LIMIT 1");
  $st->execute([$sourceId, $userId]);
  $row = $st->fetch();
  if (!$row) return null;
  return training_mastery_recalculate($userId, (string)$row['primary_concept_code'], [
    'source_type' => $sourceType, 'source_id' => $sourceId, 'opportunity_id' => (int)$row['opportunity_id'],
  ]);
}
