<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/training_quality.php';

function training_foundation_available(): bool {
  static $available = null;
  if ($available !== null) return $available;
  try {
    $st = db()->query("SHOW TABLES LIKE 'training_opportunities'");
    $available = (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    $available = false;
  }
  return $available;
}

function training_opportunity_objective_for_type(string $type): string {
  return match ($type) {
    'find_mate', 'mate' => 'force_mate',
    'spot_threat' => 'identify_threat',
    'defend_position', 'defense' => 'find_defense',
    'convert_advantage', 'conversion' => 'convert_advantage',
    'find_tactic' => 'find_tactical_resource',
    'avoid_blunder' => 'avoid_evaluation_loss',
    default => 'choose_best_candidate',
  };
}

function training_opportunity_signals(array $row, array $tagCodes, string $kind): array {
  $signals = [];
  $typeKey = $kind === 'scenario' ? 'scenario_type' : 'exercise_type';
  $signals[] = ['type' => $typeKey, 'key' => (string)($row[$typeKey] ?? '')];
  foreach ($tagCodes as $tagCode) $signals[] = ['type' => 'smart_tag', 'key' => $tagCode];
  $ply = (int)($row['ply'] ?? $row['starting_ply'] ?? 0);
  if ($ply > 0 && $ply <= 16) $signals[] = ['type' => 'phase', 'key' => 'opening'];
  if ($ply >= 60) $signals[] = ['type' => 'phase', 'key' => 'endgame'];
  return $signals;
}

function training_opportunity_tags_for_exercise(int $exerciseId): array {
  $st = db()->prepare('SELECT DISTINCT tag_code FROM training_exercise_tags WHERE exercise_id=? ORDER BY tag_code');
  $st->execute([$exerciseId]);
  return array_values(array_filter(array_map('strval', array_column($st->fetchAll(), 'tag_code'))));
}

function training_opportunity_current_analysis_id(int $userId, int $gameId): int {
  $st = db()->prepare('SELECT id FROM game_analysis WHERE user_id=? AND game_id=? AND status="done" ORDER BY id DESC LIMIT 1');
  $st->execute([$userId, $gameId]);
  return (int)($st->fetchColumn() ?: 0);
}

function training_opportunity_candidate_from_row(array $row, string $kind): array {
  $tagCodes = $kind === 'exercise'
    ? training_opportunity_tags_for_exercise((int)$row['id'])
    : array_values(array_filter([(string)($row['source_focus_code'] ?? '')]));
  $taxonomy = training_taxonomy_classify(training_opportunity_signals($row, $tagCodes, $kind));
  $primary = $taxonomy['primary'];
  $type = (string)($kind === 'scenario' ? $row['scenario_type'] : $row['exercise_type']);
  $solution = $kind === 'scenario'
    ? (string)($row['solution_uci'] ?? '')
    : (string)($row['engine_bestmove_uci'] ?: $row['solution_uci']);
  $alternatives = $kind === 'exercise'
    ? array_values(array_filter([(string)($row['accepted_alternative_uci'] ?? '')]))
    : [];
  $sourceSide = $kind === 'scenario' ? 'user' : (string)($row['source_side'] ?? 'user');
  $classification = (string)($row['classification'] ?? 'ok');
  $severityPoints = ['blunder' => 5, 'mistake' => 3, 'inaccuracy' => 1, 'ok' => 0][$classification] ?? 0;
  $recent = !empty($row['analysis_completed_at'])
    && strtotime((string)$row['analysis_completed_at']) >= strtotime('-30 days');
  $relevance = ($sourceSide === 'user' ? 12 : 4) + $severityPoints + ($recent ? 3 : 0);
  if (array_intersect($tagCodes, ['missed_mate', 'allowed_mate', 'lost_winning_position'])) $relevance += 3;
  $decisions = $kind === 'scenario' ? max(2, (int)($row['target_player_moves'] ?? 2)) : 1;
  $candidate = [
    'kind' => $kind,
    'row' => $row,
    'fen' => (string)($kind === 'scenario' ? $row['starting_fen'] : $row['fen']),
    'solution_uci' => $solution,
    'accepted_alternatives' => $alternatives,
    'primary_concept_code' => (string)($primary['code'] ?? ''),
    'concept_confidence_value' => (float)($primary['confidence'] ?? 0),
    'concepts' => $taxonomy,
    'objective_code' => training_opportunity_objective_for_type($type),
    'objective_data' => ['source_type' => $type],
    'objective_evaluable' => $type !== 'other',
    'analysis_current' => (int)$row['analysis_id'] === (int)$row['current_analysis_id'],
    'engine_complete' => !empty($row['engine_complete']),
    'equivalent_alternatives' => 1 + count($alternatives),
    'meaningful_decisions' => $decisions,
    'critical_reply_required' => $kind === 'scenario',
    'score_type' => (string)($row['score_type'] ?? 'cp'),
    'score' => (int)($row['score'] ?? 0),
    'evaluation_cp' => (int)($row['evaluation_cp'] ?? 0),
    'ply' => (int)($row['ply'] ?? $row['starting_ply'] ?? 0),
    'opening_conceptual_value' => in_array('opening_issue', $tagCodes, true),
    'recurrence_count' => 0,
    'played_move_accepted' => $kind === 'exercise'
      && in_array(strtolower((string)($row['played_uci'] ?? '')), array_merge([strtolower($solution)], $alternatives), true),
    'additional_teaching_purpose' => in_array($type, ['spot_threat', 'defend_position', 'find_tactic'], true),
    'source_side' => $sourceSide,
    'opponent_relevance' => $sourceSide !== 'opponent' || (bool)array_intersect($tagCodes, ['allowed_mate', 'missed_mate']),
    'specific_teaching_purpose' => !in_array($type, ['find_best_move', 'other'], true),
    'only_move' => count($alternatives) === 0,
    'alternative_gap_cp' => count($alternatives) ? 30 : 150,
    'relevance' => min(25, $relevance),
    'concept_confidence_score' => (int)round((float)($primary['confidence'] ?? 0) * 15),
    'decision_clarity' => count($alternatives) === 0 ? 15 : (count($alternatives) <= 2 ? 10 : 5),
    'pedagogical_value' => in_array($type, ['other', 'find_best_move'], true) ? 8 : 13,
    'recurrence' => 0,
    'adaptive_fit' => 7,
    'novelty' => 5,
    'format_suitability' => $type === 'other' ? 1 : 5,
    'ambiguity_penalty' => max(0, count($alternatives) - 1) * 4,
    'redundancy_penalty' => 0,
    'complexity_penalty' => $decisions > 3 ? 15 : 0,
    'overexposure_penalty' => 0,
    'tag_codes' => $tagCodes,
  ];
  $candidate['recommended_format'] = training_quality_route_format($candidate);
  if ($candidate['recommended_format'] === 'none') $candidate['format_suitability'] = 0;
  $candidate['difficulty'] = training_quality_estimate_difficulty($candidate);
  return $candidate;
}

function training_opportunity_analysis_rows(int $analysisId, int $userId): array {
  $exerciseSql = 'SELECT te.*, ma.depth_before,ma.bestmove AS current_bestmove,ma.score_before,ma.score_before_type,
                    a.completed_at AS analysis_completed_at,
                    CASE WHEN ma.depth_before IS NOT NULL AND ma.depth_before>0 AND ma.bestmove IS NOT NULL AND ma.score_before IS NOT NULL THEN 1 ELSE 0 END engine_complete,
                    ma.score_before_type score_type,ma.score_before score,
                    CASE WHEN ma.score_before_type="cp" THEN ma.score_before ELSE 0 END evaluation_cp
                  FROM training_exercises te
                  JOIN game_move_analysis ma ON ma.id=te.move_analysis_id AND ma.analysis_id=te.analysis_id
                  JOIN game_analysis a ON a.id=te.analysis_id
                  WHERE te.analysis_id=? AND te.user_id=? AND te.status="active"';
  $st = db()->prepare($exerciseSql);
  $st->execute([$analysisId, $userId]);
  $exercises = $st->fetchAll();
  foreach ($exercises as &$row) $row['current_analysis_id'] = training_opportunity_current_analysis_id($userId, (int)$row['game_id']);
  unset($row);

  $scenarioSql = 'SELECT ts.*, ma.depth_before,ma.bestmove AS solution_uci,
                    a.completed_at AS analysis_completed_at,
                    CASE WHEN ma.depth_before IS NOT NULL AND ma.depth_before>0 AND ma.bestmove IS NOT NULL AND ma.score_before IS NOT NULL THEN 1 ELSE 0 END engine_complete,
                    ma.score_before_type score_type,ma.score_before score,
                    CASE WHEN ma.score_before_type="cp" THEN ma.score_before ELSE 0 END evaluation_cp
                  FROM training_scenarios ts
                  JOIN game_analysis a ON a.id=ts.analysis_id
                  LEFT JOIN game_move_analysis ma ON ma.id=ts.move_analysis_id AND ma.analysis_id=ts.analysis_id
                  WHERE ts.analysis_id=? AND ts.user_id=? AND ts.status="active"';
  $st = db()->prepare($scenarioSql);
  $st->execute([$analysisId, $userId]);
  $scenarios = $st->fetchAll();
  foreach ($scenarios as &$row) $row['current_analysis_id'] = training_opportunity_current_analysis_id($userId, (int)$row['game_id']);
  unset($row);
  return ['exercises' => $exercises, 'scenarios' => $scenarios];
}

function training_opportunity_source_key(array $candidate): string {
  $row = $candidate['row'];
  return hash('sha256', implode(':', [
    $candidate['kind'], (int)($row['analysis_id'] ?? 0), (int)($row['move_analysis_id'] ?? 0), (int)($row['id'] ?? 0),
  ]));
}

function training_opportunity_persist(int $userId, array $candidate): array {
  $identity = training_canonical_identity($candidate);
  $score = training_quality_score($candidate);
  $row = $candidate['row'];
  if (!$identity) $score = ['hard_reject' => true, 'reason_code' => 'canonical_identity_invalid', 'evidence' => [], 'filter_version' => TRAINING_FILTER_VERSION];
  if (!$identity) {
    $audit = db()->prepare('INSERT INTO training_opportunity_audits (user_id,event_code,reason_code,algorithm_version,data_json) VALUES (?,"candidate_rejected",?,?,?)');
    $audit->execute([$userId, $score['reason_code'], TRAINING_FILTER_VERSION, json_encode(['kind' => $candidate['kind'], 'id' => (int)$row['id']])]);
    return ['created' => false, 'rejected' => true, 'reason' => $score['reason_code']];
  }

  $difficulty = $candidate['difficulty']['difficulty'];
  $components = $score['components'] ?? [];
  $publicationState = $score['hard_reject'] ? 'rejected' : $score['publication_state'];
  $rejectionReason = $score['hard_reject'] ? $score['reason_code'] : ($publicationState === 'rejected' ? 'score_below_threshold' : null);
  $existingSt = db()->prepare('SELECT id FROM training_opportunities WHERE user_id=? AND canonical_hash=? AND canonical_version=? LIMIT 1');
  $existingSt->execute([$userId, $identity['hash'], TRAINING_CANONICAL_VERSION]);
  $wasCanonicalDuplicate = (bool)$existingSt->fetchColumn();
  $st = db()->prepare('INSERT INTO training_opportunities
    (user_id,canonical_hash,canonical_version,normalized_fen,side_to_move,objective_code,objective_json,
     primary_solution_uci,accepted_solutions_json,primary_concept_code,concept_confidence,estimated_difficulty,
     meaningful_decisions,recommended_format,relevance_score,concept_confidence_score,decision_clarity_score,
     pedagogical_value_score,recurrence_score,adaptive_fit_score,novelty_score,format_suitability_score,
     ambiguity_penalty,redundancy_penalty,complexity_penalty,overexposure_penalty,pedagogical_score,
     publication_state,rejection_reason_code,rejection_evidence_json,currency_state,filter_version,scoring_version,
     difficulty_version,format_version,review_rule_version,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),normalized_fen=VALUES(normalized_fen),side_to_move=VALUES(side_to_move),
      objective_code=VALUES(objective_code),objective_json=VALUES(objective_json),primary_solution_uci=VALUES(primary_solution_uci),
      accepted_solutions_json=VALUES(accepted_solutions_json),primary_concept_code=VALUES(primary_concept_code),
      concept_confidence=VALUES(concept_confidence),estimated_difficulty=VALUES(estimated_difficulty),
      meaningful_decisions=VALUES(meaningful_decisions),recommended_format=VALUES(recommended_format),
      relevance_score=VALUES(relevance_score),concept_confidence_score=VALUES(concept_confidence_score),
      decision_clarity_score=VALUES(decision_clarity_score),pedagogical_value_score=VALUES(pedagogical_value_score),
      adaptive_fit_score=VALUES(adaptive_fit_score),novelty_score=VALUES(novelty_score),
      format_suitability_score=VALUES(format_suitability_score),ambiguity_penalty=VALUES(ambiguity_penalty),
      redundancy_penalty=VALUES(redundancy_penalty),complexity_penalty=VALUES(complexity_penalty),
      overexposure_penalty=VALUES(overexposure_penalty),pedagogical_score=VALUES(pedagogical_score),
      publication_state=VALUES(publication_state),rejection_reason_code=VALUES(rejection_reason_code),
      rejection_evidence_json=VALUES(rejection_evidence_json),currency_state=VALUES(currency_state),
      filter_version=VALUES(filter_version),scoring_version=VALUES(scoring_version),difficulty_version=VALUES(difficulty_version),
      format_version=VALUES(format_version),review_rule_version=VALUES(review_rule_version),updated_at=NOW()');
  $st->execute([
    $userId,$identity['hash'],TRAINING_CANONICAL_VERSION,$identity['fen'],$identity['side'],$identity['objective'],
    json_encode($identity['objective_data'], JSON_UNESCAPED_UNICODE),$identity['solution'],json_encode($identity['alternatives']),
    $identity['concept'],(float)$candidate['concept_confidence_value'],$difficulty,(int)$candidate['meaningful_decisions'],
    $candidate['recommended_format'],(int)($components['relevance'] ?? 0),(int)($components['concept_confidence'] ?? 0),
    (int)($components['decision_clarity'] ?? 0),(int)($components['pedagogical_value'] ?? 0),
    (int)($components['recurrence'] ?? 0),(int)($components['adaptive_fit'] ?? 0),(int)($components['novelty'] ?? 0),
    (int)($components['format_suitability'] ?? 0),(int)($components['ambiguity_penalty'] ?? 0),
    (int)($components['redundancy_penalty'] ?? 0),(int)($components['complexity_penalty'] ?? 0),
    (int)($components['overexposure_penalty'] ?? 0),(int)($score['score'] ?? 0),$publicationState,$rejectionReason,
    json_encode($score['evidence'] ?? []),$candidate['analysis_current'] ? 'current' : 'stale',TRAINING_FILTER_VERSION,
    TRAINING_SCORING_VERSION,TRAINING_DIFFICULTY_VERSION,TRAINING_FORMAT_VERSION,1,
  ]);
  $opportunityId = (int)db()->lastInsertId();

  foreach (array_merge([$candidate['concepts']['primary']], $candidate['concepts']['secondary']) as $concept) {
    if (!$concept) continue;
    $conceptSt = db()->prepare('INSERT INTO training_opportunity_concepts
      (opportunity_id,concept_code,role,confidence,evidence_json,taxonomy_version,mapping_version)
      VALUES (?,?,?,?,?,?,1)
      ON DUPLICATE KEY UPDATE role=VALUES(role),confidence=VALUES(confidence),evidence_json=VALUES(evidence_json)');
    $conceptSt->execute([$opportunityId,$concept['code'],$concept['role'],(float)$concept['confidence'],json_encode($concept['evidence']),TRAINING_TAXONOMY_VERSION]);
  }

  $analysisFingerprint = hash('sha256', implode(':', [(int)$row['analysis_id'],(string)($row['analysis_completed_at'] ?? ''),(int)($row['depth_before'] ?? 0),(string)($row['current_bestmove'] ?? $row['solution_uci'] ?? '')]));
  $sourceKey = training_opportunity_source_key($candidate);
  $sourceExistsSt = db()->prepare('SELECT 1 FROM training_opportunity_sources WHERE opportunity_id=? AND source_key=? LIMIT 1');
  $sourceExistsSt->execute([$opportunityId, $sourceKey]);
  $sourceAlreadyAttached = (bool)$sourceExistsSt->fetchColumn();
  $sourceSt = db()->prepare('INSERT INTO training_opportunity_sources
    (opportunity_id,user_id,source_key,game_id,analysis_id,move_analysis_id,exercise_id,scenario_id,source_side,analysis_fingerprint,source_valid,evidence_json)
    VALUES (?,?,?,?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE source_valid=1,analysis_fingerprint=VALUES(analysis_fingerprint),updated_at=NOW()');
  $sourceSt->execute([
    $opportunityId,$userId,$sourceKey,(int)($row['game_id'] ?? 0) ?: null,
    (int)($row['analysis_id'] ?? 0) ?: null,(int)($row['move_analysis_id'] ?? 0) ?: null,
    $candidate['kind'] === 'exercise' ? (int)$row['id'] : null,$candidate['kind'] === 'scenario' ? (int)$row['id'] : null,
    $candidate['source_side'], $analysisFingerprint, json_encode(['tags' => $candidate['tag_codes']]),
  ]);
  $linkTable = $candidate['kind'] === 'exercise' ? 'training_exercises' : 'training_scenarios';
  db()->prepare("UPDATE {$linkTable} SET opportunity_id=? WHERE id=? AND user_id=?")->execute([$opportunityId,(int)$row['id'],$userId]);
  db()->prepare('UPDATE training_opportunities o SET
      recurrence_count=(SELECT COUNT(*) FROM training_opportunity_sources s WHERE s.opportunity_id=o.id AND s.source_valid=1),
      recurrence_score=LEAST(10,GREATEST(0,((SELECT COUNT(*) FROM training_opportunity_sources s WHERE s.opportunity_id=o.id AND s.source_valid=1)-1)*2)),
      pedagogical_score=LEAST(100,GREATEST(0,
        relevance_score+concept_confidence_score+decision_clarity_score+pedagogical_value_score+
        LEAST(10,GREATEST(0,((SELECT COUNT(*) FROM training_opportunity_sources s WHERE s.opportunity_id=o.id AND s.source_valid=1)-1)*2))+
        adaptive_fit_score+novelty_score+format_suitability_score-ambiguity_penalty-redundancy_penalty-
        complexity_penalty-overexposure_penalty))
    WHERE o.id=?')->execute([$opportunityId]);
  db()->prepare('UPDATE training_opportunities SET
      publication_state=CASE WHEN rejection_reason_code IS NOT NULL AND rejection_reason_code<>"score_below_threshold" THEN publication_state
        WHEN pedagogical_score>=65 THEN "published" WHEN pedagogical_score>=50 THEN "reserve" ELSE "rejected" END,
      rejection_reason_code=CASE WHEN rejection_reason_code IS NOT NULL AND rejection_reason_code<>"score_below_threshold" THEN rejection_reason_code
        WHEN pedagogical_score<50 THEN "score_below_threshold" ELSE NULL END
    WHERE id=?')->execute([$opportunityId]);
  $finalSt = db()->prepare('SELECT publication_state,pedagogical_score,recurrence_count FROM training_opportunities WHERE id=?');
  $finalSt->execute([$opportunityId]);
  $final = $finalSt->fetch() ?: [];
  $finalState = (string)($final['publication_state'] ?? $publicationState);
  db()->prepare('INSERT INTO training_opportunity_audits (opportunity_id,user_id,event_code,reason_code,algorithm_version,data_json) VALUES (?,?,' . ($finalState === 'published' ? '"candidate_published"' : '"candidate_evaluated"') . ',?,?,?)')
    ->execute([$opportunityId,$userId,$rejectionReason,TRAINING_SCORING_VERSION,json_encode([
      'score' => isset($final['pedagogical_score']) ? (int)$final['pedagogical_score'] : ($score['score'] ?? null),
      'state' => $finalState,
      'source_new' => !$sourceAlreadyAttached,
    ])]);
  return [
    'created' => !$wasCanonicalDuplicate,
    'duplicate_merged' => $wasCanonicalDuplicate && !$sourceAlreadyAttached,
    'opportunity_id' => $opportunityId,
    'state' => (string)($final['publication_state'] ?? $publicationState),
    'score' => isset($final['pedagogical_score']) ? (int)$final['pedagogical_score'] : ($score['score'] ?? null),
    'recurrence_count' => (int)($final['recurrence_count'] ?? 1),
  ];
}

function training_opportunity_sync_analysis(int $analysisId, int $userId): array {
  if (!training_foundation_available()) return ['ok' => true, 'available' => false, 'processed' => 0];
  $rows = training_opportunity_analysis_rows($analysisId, $userId);
  $result = ['ok' => true, 'available' => true, 'processed' => 0, 'published' => 0, 'reserve' => 0, 'rejected' => 0, 'duplicates' => 0, 'errors' => []];
  foreach ([['exercise', $rows['exercises']], ['scenario', $rows['scenarios']]] as [$kind, $items]) {
    foreach ($items as $row) {
      try {
        $candidate = training_opportunity_candidate_from_row($row, $kind);
        $stored = training_opportunity_persist($userId, $candidate);
        $result['processed']++;
        if (!empty($stored['duplicate_merged'])) $result['duplicates']++;
        $state = (string)($stored['state'] ?? ($stored['rejected'] ? 'rejected' : ''));
        if (isset($result[$state])) $result[$state]++;
      } catch (Throwable $e) {
        $result['ok'] = false;
        $result['errors'][] = $e->getMessage();
      }
    }
  }
  return $result;
}

function training_opportunity_invalidate_stale_sources(int $userId): int {
  if (!training_foundation_available()) return 0;
  $sql = 'UPDATE training_opportunity_sources s
          JOIN game_analysis a ON a.id=s.analysis_id
          SET s.source_valid=0,s.updated_at=NOW()
          WHERE s.user_id=? AND s.source_valid=1 AND a.id<>(
            SELECT latest.id FROM game_analysis latest
            WHERE latest.user_id=s.user_id AND latest.game_id=a.game_id AND latest.status="done"
            ORDER BY latest.id DESC LIMIT 1
          )';
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  db()->prepare('UPDATE training_opportunities o SET currency_state="stale",publication_state="inactive",updated_at=NOW()
                 WHERE o.user_id=? AND NOT EXISTS (SELECT 1 FROM training_opportunity_sources s WHERE s.opportunity_id=o.id AND s.source_valid=1)')
    ->execute([$userId]);
  return $st->rowCount();
}

function training_opportunity_backfill_pending_count(int $userId): int {
  if (!training_foundation_available()) return 0;
  $st = db()->prepare('SELECT
    (SELECT COUNT(*) FROM training_exercises te WHERE te.user_id=? AND te.status="active" AND te.opportunity_id IS NULL) +
    (SELECT COUNT(*) FROM training_scenarios ts WHERE ts.user_id=? AND ts.status="active" AND ts.opportunity_id IS NULL)');
  $st->execute([$userId, $userId]);
  return (int)$st->fetchColumn();
}

function training_opportunity_backfill_recalculate_mastery(int $userId, int $runId): int {
  require_once __DIR__ . '/training_mastery.php';
  $st = db()->prepare('SELECT DISTINCT o.primary_concept_code
    FROM training_opportunities o
    WHERE o.user_id=? AND (
      EXISTS (
        SELECT 1 FROM training_exercises e
        JOIN training_solve_runs r ON r.exercise_id=e.id AND r.user_id=e.user_id
        WHERE e.opportunity_id=o.id AND r.status IN ("solved","failed")
      ) OR EXISTS (
        SELECT 1 FROM training_scenarios s
        JOIN training_scenario_runs r ON r.scenario_id=s.id AND r.user_id=s.user_id
        WHERE s.opportunity_id=o.id AND r.status IN ("completed","failed")
      )
    )');
  $st->execute([$userId]);
  $count = 0;
  foreach ($st->fetchAll() as $row) {
    $conceptCode = (string)($row['primary_concept_code'] ?? '');
    if ($conceptCode === '') continue;
    training_mastery_recalculate($userId, $conceptCode, [
      'source_type' => 'foundation_backfill',
      'source_id' => $runId,
    ]);
    $count++;
  }
  return $count;
}

function training_opportunity_backfill_batch(int $userId, int $limit = 100): array {
  $limit = max(1, min(250, $limit));
  // A single analysis can contain many exercises. Bound by analyses as well so
  // each HTTP request remains predictable on shared hosting.
  $analysisLimit = max(1, min(20, (int)ceil($limit / 10)));
  if (!training_foundation_available()) return ['ok' => false, 'message' => 'Ejecuta primero la migración 037.'];
  training_opportunity_invalidate_stale_sources($userId);
  $run = db()->prepare('INSERT INTO training_foundation_backfill_runs (user_id,status,message) VALUES (?,"running","Procesando oportunidades")');
  $run->execute([$userId]);
  $runId = (int)db()->lastInsertId();
  $st = db()->prepare('SELECT DISTINCT analysis_id FROM (
      SELECT analysis_id FROM training_exercises WHERE user_id=? AND status="active" AND opportunity_id IS NULL
      UNION SELECT analysis_id FROM training_scenarios WHERE user_id=? AND status="active" AND opportunity_id IS NULL
    ) pending WHERE analysis_id IS NOT NULL ORDER BY analysis_id DESC LIMIT ' . $analysisLimit);
  $st->execute([$userId, $userId]);
  $analysisIds = array_map('intval', array_column($st->fetchAll(), 'analysis_id'));
  $summary = ['ok' => true, 'analyses' => 0, 'processed' => 0, 'published' => 0, 'reserve' => 0, 'rejected' => 0, 'duplicates' => 0, 'mastery_recalculated' => 0, 'errors' => []];
  foreach ($analysisIds as $analysisId) {
    $result = training_opportunity_sync_analysis($analysisId, $userId);
    $summary['analyses']++;
    foreach (['processed','published','reserve','rejected','duplicates'] as $key) $summary[$key] += (int)($result[$key] ?? 0);
    foreach ($result['errors'] ?? [] as $error) $summary['errors'][] = $error;
  }
  try {
    $summary['mastery_recalculated'] = training_opportunity_backfill_recalculate_mastery($userId, $runId);
  } catch (Throwable $e) {
    $summary['errors'][] = 'No se pudo recalcular el progreso por conceptos en este lote.';
  }
  $summary['ok'] = !$summary['errors'];
  $summary['pending'] = training_opportunity_backfill_pending_count($userId);
  $summary['run_id'] = $runId;
  $cursorSt = db()->prepare('SELECT COALESCE(MAX(id),0) FROM training_exercises WHERE user_id=? AND opportunity_id IS NOT NULL');
  $cursorSt->execute([$userId]);
  $cursorExerciseId = (int)$cursorSt->fetchColumn();
  $cursorSt = db()->prepare('SELECT COALESCE(MAX(id),0) FROM training_scenarios WHERE user_id=? AND opportunity_id IS NOT NULL');
  $cursorSt->execute([$userId]);
  $cursorScenarioId = (int)$cursorSt->fetchColumn();
  db()->prepare('UPDATE training_foundation_backfill_runs SET status=?,cursor_exercise_id=?,cursor_scenario_id=?,processed_count=?,published_count=?,reserve_count=?,rejected_count=?,duplicate_count=?,
      error_count=?,message=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=?')
    ->execute([$summary['ok'] ? 'done' : 'error',$cursorExerciseId,$cursorScenarioId,$summary['processed'],$summary['published'],
      $summary['reserve'],$summary['rejected'],$summary['duplicates'],count($summary['errors']),
      $summary['ok'] ? 'Lote completado.' : 'Lote completado con errores.',$runId,$userId]);
  return $summary;
}
