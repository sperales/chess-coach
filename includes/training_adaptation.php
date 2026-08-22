<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const TRAINING_ADAPTATION_VERSION = 1;

function training_adaptation_decision(array $recentOutcomes): array {
  $recent = array_values($recentOutcomes);
  $lastTwo = array_slice($recent, 0, 2);
  $lastThree = array_slice($recent, 0, 3);
  $difficultFailures = count($lastTwo) === 2 && count(array_filter($lastTwo, static function (array $item): bool {
    return ($item['result'] ?? '') === 'failed' && in_array(($item['difficulty'] ?? ''), ['hard', 'critical'], true);
  })) === 2;
  if ($difficultFailures) {
    return ['action' => 'lower_difficulty', 'reason_code' => 'difficulty_recovery', 'adaptation_version' => TRAINING_ADAPTATION_VERSION];
  }
  $autonomousFast = count($lastThree) === 3 && count(array_filter($lastThree, static function (array $item): bool {
    return ($item['result'] ?? '') === 'solved'
      && (int)($item['attempts'] ?? 0) <= 1
      && (int)($item['hint_level'] ?? 0) === 0
      && (int)($item['time_to_first_move_ms'] ?? PHP_INT_MAX) <= 45000;
  })) === 3;
  if ($autonomousFast) {
    return ['action' => 'raise_difficulty', 'reason_code' => 'autonomous_progression', 'adaptation_version' => TRAINING_ADAPTATION_VERSION];
  }
  return ['action' => 'keep', 'reason_code' => 'stable_pacing', 'adaptation_version' => TRAINING_ADAPTATION_VERSION];
}

function training_adaptation_item_difficulty(array $item): string {
  $evidence = json_decode((string)($item['evidence_json'] ?? ''), true);
  $difficulty = is_array($evidence) ? (string)($evidence['difficulty'] ?? '') : '';
  return in_array($difficulty, ['easy', 'medium', 'hard', 'critical'], true) ? $difficulty : 'medium';
}

function training_adaptation_recent_outcomes(int $userId, int $sessionId): array {
  $sql = 'SELECT result,difficulty,attempts,hint_level,time_to_first_move_ms,completed_at FROM (
      SELECT IF(r.status="solved","solved","failed") result,r.difficulty_snapshot difficulty,
             r.attempts_count attempts,r.highest_hint_level hint_level,r.time_to_first_move_ms,r.completed_at
      FROM training_solve_runs r WHERE r.user_id=? AND r.session_id=? AND r.status IN ("solved","failed")
      UNION ALL
      SELECT IF(r.status="completed","solved","failed"),s.difficulty,r.attempts_count,r.highest_hint_level,
             r.time_to_first_move_ms,r.completed_at
      FROM training_scenario_runs r JOIN training_scenarios s ON s.id=r.scenario_id
      WHERE r.user_id=? AND r.session_id=? AND r.status IN ("completed","failed")
    ) outcomes ORDER BY completed_at DESC LIMIT 3';
  $st = db()->prepare($sql);
  $st->execute([$userId, $sessionId, $userId, $sessionId]);
  return $st->fetchAll();
}

function training_adaptation_swap_positions(int $userId, int $sessionId, array $first, array $second, array $decision): bool {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $temporaryPosition = 65535;
    $pdo->prepare('UPDATE training_session_items SET position=? WHERE id=? AND user_id=? AND session_id=? AND status="pending"')
      ->execute([$temporaryPosition, (int)$first['id'], $userId, $sessionId]);
    $evidence = json_decode((string)($second['evidence_json'] ?? ''), true);
    if (!is_array($evidence)) $evidence = [];
    $evidence['adaptation'] = $decision;
    $pdo->prepare('UPDATE training_session_items SET position=?,selection_reason_code=?,reason=?,evidence_json=?,updated_at=NOW()
                   WHERE id=? AND user_id=? AND session_id=? AND status="pending"')
      ->execute([(int)$first['position'],(string)$decision['reason_code'],
        $decision['action'] === 'lower_difficulty'
          ? 'Ajuste de ritmo tras dos actividades difíciles.'
          : 'Aumento gradual tras tres resoluciones autónomas.',
        json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),(int)$second['id'],$userId,$sessionId]);
    $pdo->prepare('UPDATE training_session_items SET position=?,updated_at=NOW() WHERE id=? AND user_id=? AND session_id=? AND status="pending"')
      ->execute([(int)$second['position'], (int)$first['id'], $userId, $sessionId]);
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Training adaptation failed: session=' . $sessionId . ' category=position_swap');
    return false;
  }
}

function training_adapt_pending_session(int $userId, ?int $sessionId): array {
  $mode = strtolower((string)(app_config()['training_selection_mode'] ?? 'shadow'));
  if ($sessionId === null || $sessionId <= 0 || $mode !== 'active') return ['action' => 'keep', 'applied' => false];
  $decision = training_adaptation_decision(training_adaptation_recent_outcomes($userId, $sessionId));
  if ($decision['action'] === 'keep') return $decision + ['applied' => false];
  $st = db()->prepare('SELECT id,position,item_type,evidence_json FROM training_session_items
                       WHERE user_id=? AND session_id=? AND status="pending" ORDER BY position');
  $st->execute([$userId, $sessionId]);
  $pending = $st->fetchAll();
  if (count($pending) < 2) return $decision + ['applied' => false];
  $next = $pending[0];
  $nextDifficulty = training_adaptation_item_difficulty($next);
  $replacement = null;
  foreach (array_slice($pending, 1) as $candidate) {
    $difficulty = training_adaptation_item_difficulty($candidate);
    if ($decision['action'] === 'lower_difficulty' && $next['item_type'] === 'scenario'
      && in_array($nextDifficulty, ['hard', 'critical'], true) && $candidate['item_type'] === 'flash'
      && in_array($difficulty, ['easy', 'medium'], true)) {
      $replacement = $candidate;
      break;
    }
    if ($decision['action'] === 'raise_difficulty' && in_array($nextDifficulty, ['easy', 'medium'], true)
      && in_array($difficulty, ['medium', 'hard'], true)
      && ['easy' => 1, 'medium' => 2, 'hard' => 3][$difficulty] > ['easy' => 1, 'medium' => 2, 'hard' => 3][$nextDifficulty]) {
      $replacement = $candidate;
      break;
    }
  }
  if (!$replacement) return $decision + ['applied' => false];
  return $decision + ['applied' => training_adaptation_swap_positions($userId, $sessionId, $next, $replacement, $decision)];
}
