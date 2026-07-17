<?php
require_once __DIR__ . '/db.php';

const TRAINING_PROGRESS_RULE_VERSION = 1;
const TRAINING_PROGRESS_MAX_HINT_LEVEL = 3;
const TRAINING_PROGRESS_MAX_ATTEMPTS = 5;

function training_progress_hint_level(int $level): int {
  return max(0, min(TRAINING_PROGRESS_MAX_HINT_LEVEL, $level));
}

function training_progress_attempts_count(int $attempts): int {
  return max(0, min(TRAINING_PROGRESS_MAX_ATTEMPTS, $attempts));
}

function training_resolution_quality(string $status, int $attemptsCount, int $highestHintLevel): ?float {
  // Skips and abandoned views are not evidence of chess knowledge.
  if (in_array($status, ['skipped', 'abandoned'], true)) return null;
  if ($status !== 'solved') return 0.0;

  $attemptsCount = max(1, training_progress_attempts_count($attemptsCount));
  $highestHintLevel = training_progress_hint_level($highestHintLevel);
  $attemptCaps = [1 => 100.0, 2 => 90.0, 3 => 80.0, 4 => 65.0, 5 => 50.0];
  $hintCaps = [0 => 100.0, 1 => 85.0, 2 => 65.0, 3 => 45.0];
  return min($attemptCaps[$attemptsCount], $hintCaps[$highestHintLevel]);
}

function training_difficulty_evidence_weight(string $difficulty): float {
  return match (strtolower(trim($difficulty))) {
    'easy' => 0.85,
    'hard' => 1.15,
    'critical' => 1.30,
    default => 1.00,
  };
}

function training_progress_run_for_user(int $runId, int $userId, bool $forUpdate = false): ?array {
  if ($runId <= 0 || $userId <= 0) return null;
  $sql = 'SELECT * FROM training_solve_runs WHERE id=? AND user_id=? LIMIT 1';
  if ($forUpdate) $sql .= ' FOR UPDATE';
  $st = db()->prepare($sql);
  $st->execute([$runId, $userId]);
  $row = $st->fetch();
  return $row ?: null;
}

function training_progress_start_solve_run(int $userId, int $exerciseId, ?int $sessionId = null): array {
  if ($userId <= 0 || $exerciseId <= 0) throw new InvalidArgumentException('Usuario o ejercicio no valido.');
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $exerciseSt = $pdo->prepare('SELECT id,difficulty FROM training_exercises WHERE id=? AND user_id=? AND status="active" LIMIT 1 FOR UPDATE');
    $exerciseSt->execute([$exerciseId, $userId]);
    $exercise = $exerciseSt->fetch();
    if (!$exercise) throw new RuntimeException('Ejercicio no encontrado.');

    $validSessionId = null;
    if ($sessionId && $sessionId > 0) {
      $sessionSt = $pdo->prepare('SELECT id FROM training_sessions WHERE id=? AND user_id=? LIMIT 1');
      $sessionSt->execute([$sessionId, $userId]);
      if ($sessionSt->fetchColumn()) $validSessionId = $sessionId;
    }

    $pdo->prepare('UPDATE training_solve_runs
                   SET status="abandoned",completed_at=NOW(),updated_at=NOW()
                   WHERE user_id=? AND exercise_id=? AND status="active"
                     AND started_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)')
      ->execute([$userId, $exerciseId]);

    $activeSt = $pdo->prepare('SELECT * FROM training_solve_runs
                               WHERE user_id=? AND exercise_id=? AND status="active"
                               ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $activeSt->execute([$userId, $exerciseId]);
    $active = $activeSt->fetch();
    if ($active) {
      $pdo->commit();
      return $active;
    }

    $difficulty = in_array((string)$exercise['difficulty'], ['easy', 'medium', 'hard', 'critical'], true)
      ? (string)$exercise['difficulty']
      : 'medium';
    $weight = training_difficulty_evidence_weight($difficulty);
    $insert = $pdo->prepare('INSERT INTO training_solve_runs
        (user_id,exercise_id,session_id,status,difficulty_snapshot,evidence_weight,scoring_version,started_at,created_at)
        VALUES (?,?,?,"active",?,?,?,NOW(),NOW())');
    $insert->execute([$userId, $exerciseId, $validSessionId, $difficulty, $weight, TRAINING_PROGRESS_RULE_VERSION]);
    $runId = (int)$pdo->lastInsertId();
    $run = training_progress_run_for_user($runId, $userId);
    $pdo->commit();
    if (!$run) throw new RuntimeException('No se pudo recuperar la resolucion creada.');
    return $run;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

function training_progress_record_hint(
  int $userId,
  int $runId,
  int $hintLevel,
  string $hintType,
  string $hintText,
  int $generatorVersion = 1
): array {
  $hintLevel = training_progress_hint_level($hintLevel);
  $hintType = trim($hintType);
  $hintText = trim($hintText);
  if ($hintLevel < 1 || $hintType === '' || $hintText === '') throw new InvalidArgumentException('Pista no valida.');
  if (!preg_match('/^[a-z0-9_]{1,40}$/', $hintType)) throw new InvalidArgumentException('Tipo de pista no valido.');
  if (strlen($hintText) > 255) throw new InvalidArgumentException('Texto de pista demasiado largo.');

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $run = training_progress_run_for_user($runId, $userId, true);
    if (!$run || $run['status'] !== 'active') throw new RuntimeException('Resolucion no disponible.');
    $currentLevel = training_progress_hint_level((int)$run['highest_hint_level']);
    if ($hintLevel > $currentLevel + 1) throw new RuntimeException('Las pistas deben solicitarse en orden.');

    $insert = $pdo->prepare('INSERT INTO training_solve_hints
        (solve_run_id,user_id,exercise_id,hint_level,hint_type,hint_text,generator_version,created_at)
        VALUES (?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $insert->execute([
      $runId,
      $userId,
      (int)$run['exercise_id'],
      $hintLevel,
      $hintType,
      $hintText,
      max(1, $generatorVersion),
    ]);
    $hintId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE training_solve_runs SET highest_hint_level=GREATEST(highest_hint_level,?),updated_at=NOW() WHERE id=? AND user_id=?')
      ->execute([$hintLevel, $runId, $userId]);
    $hintSt = $pdo->prepare('SELECT * FROM training_solve_hints WHERE id=? AND user_id=? LIMIT 1');
    $hintSt->execute([$hintId, $userId]);
    $hint = $hintSt->fetch();
    $pdo->commit();
    if (!$hint) throw new RuntimeException('No se pudo recuperar la pista registrada.');
    return $hint;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

function player_progress_record_event(
  int $userId,
  string $eventKey,
  string $eventType,
  string $sourceType,
  ?int $sourceId,
  ?float $qualityScore,
  float $evidenceWeight = 1.0,
  array $metadata = [],
  ?int $solveRunId = null,
  int $ruleVersion = TRAINING_PROGRESS_RULE_VERSION
): array {
  $eventKey = trim($eventKey);
  $eventType = trim($eventType);
  $sourceType = trim($sourceType);
  if ($userId <= 0 || $eventKey === '' || strlen($eventKey) > 190) throw new InvalidArgumentException('Clave de evento no valida.');
  if (!preg_match('/^[a-z0-9_]{1,50}$/', $eventType)) throw new InvalidArgumentException('Tipo de evento no valido.');
  if (!preg_match('/^[a-z0-9_]{1,40}$/', $sourceType)) throw new InvalidArgumentException('Origen de evento no valido.');
  if ($qualityScore !== null) $qualityScore = max(0.0, min(100.0, $qualityScore));
  $evidenceWeight = max(0.0, min(10.0, $evidenceWeight));
  $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
  if ($metadata && $metadataJson === false) throw new InvalidArgumentException('Metadatos de evento no validos.');

  $st = db()->prepare('INSERT INTO player_progress_events
      (user_id,event_key,event_type,source_type,source_id,solve_run_id,quality_score,evidence_weight,metadata_json,rule_version,occurred_at,created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
      ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
  $st->execute([
    $userId,
    $eventKey,
    $eventType,
    $sourceType,
    $sourceId,
    $solveRunId,
    $qualityScore,
    $evidenceWeight,
    $metadataJson,
    max(1, $ruleVersion),
  ]);
  $eventId = (int)db()->lastInsertId();
  $eventSt = db()->prepare('SELECT * FROM player_progress_events WHERE id=? AND user_id=? LIMIT 1');
  $eventSt->execute([$eventId, $userId]);
  $event = $eventSt->fetch();
  if (!$event) throw new RuntimeException('No se pudo recuperar el evento registrado.');
  return $event;
}

function training_progress_complete_solve_run(
  int $userId,
  int $runId,
  string $status,
  int $attemptsCount,
  ?int $highestHintLevel = null
): array {
  if (!in_array($status, ['solved', 'failed', 'skipped', 'abandoned'], true)) {
    throw new InvalidArgumentException('Estado de resolucion no valido.');
  }
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $run = training_progress_run_for_user($runId, $userId, true);
    if (!$run) throw new RuntimeException('Resolucion no encontrada.');
    if ($run['status'] !== 'active') {
      $pdo->commit();
      return $run;
    }

    $attemptsCount = training_progress_attempts_count($attemptsCount);
    $hintLevel = $highestHintLevel === null
      ? training_progress_hint_level((int)$run['highest_hint_level'])
      : max(training_progress_hint_level((int)$run['highest_hint_level']), training_progress_hint_level($highestHintLevel));
    $quality = training_resolution_quality($status, $attemptsCount, $hintLevel);
    $weight = training_difficulty_evidence_weight((string)$run['difficulty_snapshot']);
    $update = $pdo->prepare('UPDATE training_solve_runs
        SET status=?,attempts_count=?,highest_hint_level=?,quality_score=?,evidence_weight=?,completed_at=NOW(),updated_at=NOW()
        WHERE id=? AND user_id=? AND status="active"');
    $update->execute([$status, $attemptsCount, $hintLevel, $quality, $weight, $runId, $userId]);

    if (in_array($status, ['solved', 'failed'], true)) {
      player_progress_record_event(
        $userId,
        'exercise_run:' . $runId,
        'exercise_resolution',
        'solve_run',
        $runId,
        $quality,
        $weight,
        [
          'exercise_id' => (int)$run['exercise_id'],
          'status' => $status,
          'attempts_count' => $attemptsCount,
          'highest_hint_level' => $hintLevel,
          'difficulty' => (string)$run['difficulty_snapshot'],
        ],
        $runId
      );
    }

    $completed = training_progress_run_for_user($runId, $userId);
    $pdo->commit();
    if (!$completed) throw new RuntimeException('No se pudo recuperar la resolucion completada.');
    return $completed;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}
