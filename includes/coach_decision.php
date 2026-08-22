<?php
require_once __DIR__ . '/training_mastery.php';
require_once __DIR__ . '/training_taxonomy.php';

const COACH_DECISION_ALGORITHM_VERSION = 1;

function coach_decision_score(array $candidate): array {
  $severity = max(0, min(30, (int)($candidate['recent_severity'] ?? 0)));
  $recurrence = max(0, min(25, (int)($candidate['recurrence'] ?? 0)));
  $masteryGap = max(0, min(20, (int)($candidate['mastery_gap'] ?? 0)));
  $transfer = max(0, min(15, (int)($candidate['transfer_failure'] ?? 0)));
  $trainability = max(0, min(10, (int)($candidate['trainability'] ?? 0)));
  $lowConfidence = max(0, min(20, (int)($candidate['low_confidence_penalty'] ?? 0)));
  $overexposure = max(0, min(15, (int)($candidate['overexposure_penalty'] ?? 0)));
  $stable = max(0, min(20, (int)($candidate['stable_without_issue_penalty'] ?? 0)));
  return [
    'score' => $severity + $recurrence + $masteryGap + $transfer + $trainability - $lowConfidence - $overexposure - $stable,
    'components' => [
      'recent_severity' => $severity, 'recurrence' => $recurrence, 'mastery_gap' => $masteryGap,
      'transfer_failure' => $transfer, 'trainability' => $trainability,
      'low_confidence_penalty' => $lowConfidence, 'overexposure_penalty' => $overexposure,
      'stable_without_issue_penalty' => $stable,
    ],
  ];
}

function coach_decision_rank(array $candidates): array {
  $ranked = [];
  foreach ($candidates as $candidate) {
    $scored = coach_decision_score($candidate);
    $candidate['priority_score'] = $scored['score'];
    $candidate['score_components'] = $scored['components'];
    $ranked[] = $candidate;
  }
  usort($ranked, static fn(array $a, array $b): int => ($b['priority_score'] <=> $a['priority_score']) ?: strcmp((string)$a['concept_code'], (string)$b['concept_code']));
  return $ranked;
}

function coach_decision_label(string $conceptCode): string {
  $concepts = training_taxonomy_concepts();
  return (string)($concepts[$conceptCode] ?? 'Entrenamiento recomendado');
}

function coach_decision_candidates(int $userId): array {
  $sql = 'SELECT c.code concept_code,c.label,
            COUNT(DISTINCT o.id) opportunity_count,
            COALESCE(MAX(o.relevance_score),0) max_relevance,
            COALESCE(SUM(CASE WHEN o.recurrence_count>1 THEN LEAST(o.recurrence_count,5) ELSE 0 END),0) recurrence_signal,
            COALESCE(SUM(CASE WHEN o.last_selected_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) recent_exposure,
            m.mastery_state,m.recent_performance_state,m.confidence,m.adjusted_autonomy_rate,m.review_pending
          FROM training_concepts c
          LEFT JOIN training_opportunities o ON o.primary_concept_code=c.code AND o.user_id=?
            AND o.publication_state="published" AND o.currency_state="current"
          LEFT JOIN training_concept_mastery m ON m.user_id=? AND m.concept_code=c.code
          WHERE c.active=1 GROUP BY c.code,c.label,m.mastery_state,m.recent_performance_state,m.confidence,m.adjusted_autonomy_rate,m.review_pending';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  $candidates = [];
  foreach ($st->fetchAll() as $row) {
    $count = (int)$row['opportunity_count'];
    if ($count <= 0) continue;
    $confidence = $row['confidence'] === null ? min(1, $count / 5) : (float)$row['confidence'];
    $mastery = (string)($row['mastery_state'] ?: 'starting');
    $recent = (string)($row['recent_performance_state'] ?: 'normal');
    $candidate = [
      'concept_code' => (string)$row['concept_code'], 'label' => (string)$row['label'], 'opportunity_count' => $count,
      'recent_severity' => min(30, (int)round((int)$row['max_relevance'] * 0.30)),
      'recurrence' => min(25, (int)$row['recurrence_signal'] * 3),
      'mastery_gap' => ['starting' => 20, 'learning' => 15, 'consolidating' => 8, 'stable' => 0][$mastery] ?? 20,
      'transfer_failure' => ['priority_review' => 15, 'attention' => 10, 'normal' => 3, 'in_form' => 0][$recent] ?? 3,
      'trainability' => min(10, $count * 2),
      'low_confidence_penalty' => $confidence < 0.35 ? 20 : ($confidence < 0.60 ? 10 : 0),
      'overexposure_penalty' => min(15, (int)$row['recent_exposure'] * 5),
      'stable_without_issue_penalty' => $mastery === 'stable' && !in_array($recent, ['attention','priority_review'], true) ? 20 : 0,
      'confidence' => $confidence, 'mastery_state' => $mastery, 'recent_performance_state' => $recent,
      'review_pending' => (bool)$row['review_pending'], 'adjusted_autonomy_rate' => $row['adjusted_autonomy_rate'],
    ];
    // Low confidence cannot win from a weak generic signal.
    if ($confidence < 0.35 && (int)$row['recurrence_signal'] < 2 && (int)$row['max_relevance'] < 80) continue;
    $candidates[] = $candidate;
  }
  return coach_decision_rank($candidates);
}

function coach_decision_current(int $userId): ?array {
  $st = db()->prepare('SELECT * FROM coach_decisions WHERE user_id=? AND is_current=1 ORDER BY id DESC LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  if (!$row) return null;
  $row['evidence'] = json_decode((string)$row['evidence_json'], true) ?: [];
  $row['scores'] = json_decode((string)$row['scores_json'], true) ?: [];
  return $row;
}

function coach_decision_is_due(int $userId, array $decision): bool {
  if (!empty($decision['reassess_after_at']) && strtotime((string)$decision['reassess_after_at']) <= time()) return true;
  $training = db()->prepare('SELECT COUNT(DISTINCT DATE(completed_at)) FROM training_sessions WHERE user_id=? AND status="completed" AND completed_at>?');
  $training->execute([$userId, $decision['created_at']]);
  if ((int)$training->fetchColumn() >= (int)$decision['reassess_after_training_days']) return true;
  $games = db()->prepare('SELECT COUNT(*) FROM games WHERE user_id=? AND created_at>?');
  $games->execute([$userId, $decision['created_at']]);
  return (int)$games->fetchColumn() >= (int)$decision['reassess_after_games'];
}

function coach_decision_has_serious_override(array $current, array $ranked): bool {
  $candidate = $ranked[0] ?? null;
  if (!$candidate || (string)($candidate['concept_code'] ?? '') === (string)($current['primary_concept_code'] ?? '')) return false;
  $components = $candidate['score_components'] ?? [];
  return (float)($candidate['confidence'] ?? 0) >= 0.60
    && (int)($components['recent_severity'] ?? 0) >= 24
    && (int)($components['recurrence'] ?? 0) >= 12;
}

function coach_decision_for_user(int $userId, bool $force = false): ?array {
  $current = coach_decision_current($userId);
  $ranked = coach_decision_candidates($userId);
  if (!$force && $current && !coach_decision_is_due($userId, $current) && !coach_decision_has_serious_override($current, $ranked)) return $current;
  if (!$ranked) return $current;
  $primary = $ranked[0];
  $secondary = $ranked[1] ?? null;
  $reasonCode = $primary['review_pending'] ? 'due_review' : ($primary['recent_performance_state'] === 'priority_review' ? 'recent_deterioration' : 'highest_learning_priority');
  $reasonText = match ($reasonCode) {
    'due_review' => 'Este concepto necesita una revisión para conservar lo aprendido.',
    'recent_deterioration' => 'Los resultados recientes muestran que este patrón necesita atención.',
    default => 'Es la combinación más clara de recurrencia, margen de mejora y oportunidades útiles.',
  };
  $objective = 'Trabajar ' . strtolower(coach_decision_label((string)$primary['concept_code'])) . ' con posiciones de calidad y comprobar la autonomía.';
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare('UPDATE coach_decisions SET is_current=0,superseded_at=NOW() WHERE user_id=? AND is_current=1')->execute([$userId]);
    $insert = $pdo->prepare('INSERT INTO coach_decisions
      (user_id,is_current,primary_concept_code,secondary_concept_code,confidence,reason_code,reason_text,session_objective,
       evidence_json,scores_json,reassess_after_training_days,reassess_after_games,reassess_after_at,algorithm_version)
      VALUES (?,1,?,?,?,?,?,?,?,?,3,10,DATE_ADD(NOW(),INTERVAL 7 DAY),?)');
    $insert->execute([$userId,$primary['concept_code'],$secondary['concept_code'] ?? null,max(0.0,min(1.0,(float)$primary['confidence'])),
      $reasonCode,$reasonText,$objective,json_encode(['primary' => $primary, 'secondary' => $secondary], JSON_UNESCAPED_UNICODE),
      json_encode(array_map(static fn(array $item): array => ['concept_code' => $item['concept_code'], 'priority_score' => $item['priority_score'], 'components' => $item['score_components']], $ranked), JSON_UNESCAPED_UNICODE),
      COACH_DECISION_ALGORITHM_VERSION]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    return coach_decision_current($userId);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

function coach_decision_public(?array $decision): ?array {
  if (!$decision) return null;
  return [
    'id' => (int)$decision['id'], 'primary_concept_code' => (string)$decision['primary_concept_code'],
    'primary_label' => coach_decision_label((string)$decision['primary_concept_code']),
    'secondary_concept_code' => $decision['secondary_concept_code'] ?: null,
    'secondary_label' => $decision['secondary_concept_code'] ? coach_decision_label((string)$decision['secondary_concept_code']) : null,
    'confidence' => (float)$decision['confidence'], 'reason_code' => (string)$decision['reason_code'],
    'reason' => (string)$decision['reason_text'], 'session_objective' => (string)$decision['session_objective'],
    'created_at' => (string)$decision['created_at'], 'reassess_after_at' => $decision['reassess_after_at'],
  ];
}
