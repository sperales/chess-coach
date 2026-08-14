<?php
require_once __DIR__ . '/training_scenarios.php';
require_once __DIR__ . '/scenario_engine.php';
require_once __DIR__ . '/chess_notation.php';
require_once __DIR__ . '/coach_messages.php';
require_once __DIR__ . '/training_progress.php';

function training_scenario_for_user(int $scenarioId, int $userId): ?array {
  $st = db()->prepare('SELECT s.*,g.white_player,g.black_player,g.played_at FROM training_scenarios s LEFT JOIN games g ON g.id=s.game_id WHERE s.id=? AND s.user_id=? AND s.status="active"');
  $st->execute([$scenarioId, $userId]);
  $row = $st->fetch();
  if (!$row) return null;
  $row['objective'] = coach_decode_json($row['objective_json'] ?? null);
  $row['completion'] = coach_decode_json($row['completion_json'] ?? null);
  return $row;
}

function training_scenario_run_for_user(int $runId, int $userId): ?array {
  $st = db()->prepare('SELECT * FROM training_scenario_runs WHERE id=? AND user_id=?');
  $st->execute([$runId, $userId]);
  return $st->fetch() ?: null;
}

function training_scenario_events_for_run(int $runId, int $userId): array {
  $st = db()->prepare('SELECT sequence_no,actor,event_type,move_uci,move_san,decision_bucket,accepted,feedback_text,metadata_json,created_at
                       FROM training_scenario_events WHERE run_id=? AND user_id=? ORDER BY sequence_no');
  $st->execute([$runId, $userId]);
  return array_map(static function (array $row): array {
    $row['sequence_no'] = (int)$row['sequence_no'];
    $row['accepted'] = $row['accepted'] === null ? null : (bool)$row['accepted'];
    $row['metadata'] = coach_decode_json($row['metadata_json'] ?? null);
    unset($row['metadata_json']);
    return $row;
  }, $st->fetchAll());
}

function training_scenario_player_score(array $evaluation, string $fen, string $playerColor): ?int {
  $score = training_scenario_score_cp(isset($evaluation['score']) ? (int)$evaluation['score'] : null, $evaluation['score_type'] ?? null);
  if ($score === null) return null;
  return chess_fen_side_to_move($fen) === $playerColor ? $score : -$score;
}

function training_scenario_decision(int $beforeCp, int $afterCp, int $threshold, bool $matchesBest): array {
  $loss = max(0, $beforeCp - $afterCp);
  $bucket = $matchesBest || $loss <= CHESS_CPL_BEST_EQUIVALENCE ? 'optimal'
    : ($loss <= $threshold ? 'acceptable' : ($loss < CHESS_CPL_BLUNDER ? 'problematic' : 'critical'));
  return ['accepted' => in_array($bucket, ['optimal', 'acceptable'], true), 'loss_cp' => $loss, 'bucket' => $bucket];
}

function training_scenario_is_checkmate(string $fen): bool {
  $state = chess_state_from_fen($fen);
  return $state ? in_check($state, $state['turn']) && count(pseudo_moves($state)) === 0 : false;
}

function training_scenario_objective_met(array $scenario, int $playerMoves, int $currentEvalCp, bool $checkmate): bool {
  if ($scenario['scenario_type'] === 'mate') return $checkmate;
  if ($playerMoves < (int)$scenario['target_player_moves']) return false;
  if ($scenario['scenario_type'] === 'conversion') return $currentEvalCp >= 150;
  return $currentEvalCp >= -100;
}

function training_scenario_event(int $runId, int $userId, string $actor, string $eventType, array $data = []): void {
  $seq = db()->prepare('SELECT COALESCE(MAX(sequence_no),0)+1 FROM training_scenario_events WHERE run_id=?');
  $seq->execute([$runId]);
  $sql = 'INSERT INTO training_scenario_events
    (run_id,user_id,sequence_no,actor,event_type,fen_before,fen_after,move_uci,move_san,score_before_cp,score_after_cp,
     centipawn_loss,decision_bucket,accepted,feedback_text,metadata_json,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
  db()->prepare($sql)->execute([
    $runId, $userId, (int)$seq->fetchColumn(), $actor, $eventType, $data['fen_before'] ?? null, $data['fen_after'] ?? null,
    $data['move_uci'] ?? null, $data['move_san'] ?? null, $data['score_before_cp'] ?? null, $data['score_after_cp'] ?? null,
    $data['centipawn_loss'] ?? null, $data['decision_bucket'] ?? null, array_key_exists('accepted', $data) ? (int)(bool)$data['accepted'] : null,
    $data['feedback_text'] ?? null, isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
  ]);
}

function training_scenario_public(array $scenario, ?array $run = null): array {
  return [
    'id' => (int)$scenario['id'], 'type' => (string)$scenario['scenario_type'], 'title' => (string)$scenario['title'],
    'prompt' => (string)$scenario['prompt'], 'difficulty' => (string)$scenario['difficulty'], 'player_color' => (string)$scenario['player_color'],
    'target_player_moves' => (int)$scenario['target_player_moves'], 'max_player_moves' => (int)$scenario['max_player_moves'],
    'source' => ['game_id' => (int)$scenario['game_id'], 'white' => (string)$scenario['white_player'], 'black' => (string)$scenario['black_player'], 'ply' => (int)$scenario['starting_ply']],
    'run' => $run ? [
      'id' => (int)$run['id'], 'fen' => (string)$run['current_fen'], 'status' => (string)$run['status'],
      'player_moves' => (int)$run['player_moves_count'], 'attempts' => (int)$run['attempts_count'], 'highest_hint_level' => (int)$run['highest_hint_level'],
      'duration_ms' => (int)$run['duration_ms'], 'quality_score' => $run['quality_score'] === null ? null : (float)$run['quality_score'],
    ] : null,
  ];
}

function training_scenario_start(int $userId, int $scenarioId, ?int $sessionId = null): array {
  $scenario = training_scenario_for_user($scenarioId, $userId);
  if (!$scenario) throw new InvalidArgumentException('Escenario no encontrado.');
  $active = db()->prepare('SELECT * FROM training_scenario_runs WHERE scenario_id=? AND user_id=? AND status="active" ORDER BY id DESC LIMIT 1');
  $active->execute([$scenarioId, $userId]);
  $run = $active->fetch();
  if (!$run) {
    $itemId = null;
    if ($sessionId) {
      $item = db()->prepare('SELECT id FROM training_session_items WHERE session_id=? AND user_id=? AND scenario_id=? LIMIT 1');
      $item->execute([$sessionId, $userId, $scenarioId]);
      $itemId = $item->fetchColumn() ?: null;
    }
    $eval = training_scenario_engine()->evaluate((string)$scenario['starting_fen']);
    $playerEval = training_scenario_player_score($eval, (string)$scenario['starting_fen'], (string)$scenario['player_color']);
    db()->prepare('INSERT INTO training_scenario_runs (scenario_id,user_id,session_id,session_item_id,current_fen,current_eval_cp,status,started_at) VALUES (?,?,?,?,?,?,"active",NOW())')
      ->execute([$scenarioId, $userId, $sessionId, $itemId, $scenario['starting_fen'], $playerEval]);
    $run = training_scenario_run_for_user((int)db()->lastInsertId(), $userId);
    training_scenario_event((int)$run['id'], $userId, 'system', 'start', ['fen_after' => $scenario['starting_fen'], 'score_after_cp' => $playerEval]);
  } elseif ($sessionId && empty($run['session_item_id'])) {
    $item = db()->prepare('SELECT id FROM training_session_items WHERE session_id=? AND user_id=? AND scenario_id=? LIMIT 1');
    $item->execute([$sessionId, $userId, $scenarioId]);
    $itemId = $item->fetchColumn() ?: null;
    if ($itemId) {
      db()->prepare('UPDATE training_scenario_runs SET session_id=?,session_item_id=?,updated_at=NOW() WHERE id=?')
        ->execute([$sessionId, $itemId, (int)$run['id']]);
      $run = training_scenario_run_for_user((int)$run['id'], $userId);
    }
  }
  return ['ok' => true, 'scenario' => training_scenario_public($scenario, $run)];
}

function training_scenario_move(int $userId, int $runId, string $moveUci): array {
  $run = training_scenario_run_for_user($runId, $userId);
  if (!$run || $run['status'] !== 'active') throw new InvalidArgumentException('El escenario ya no está activo.');
  $scenario = training_scenario_for_user((int)$run['scenario_id'], $userId);
  if (!$scenario) throw new InvalidArgumentException('Escenario no encontrado.');
  $fen = (string)$run['current_fen'];
  if (chess_fen_side_to_move($fen) !== $scenario['player_color']) throw new RuntimeException('La posición no corresponde al turno del jugador.');
  $fenAfterUser = chess_apply_uci_to_fen($fen, $moveUci);
  if ($fenAfterUser === null) return ['ok' => false, 'error' => 'La jugada no es legal en esta posición.', 'accepted' => false];

  $engine = training_scenario_engine();
  $before = $engine->evaluate($fen);
  $after = $engine->evaluate($fenAfterUser);
  $beforeCp = training_scenario_player_score($before, $fen, (string)$scenario['player_color']);
  $afterCp = training_scenario_player_score($after, $fenAfterUser, (string)$scenario['player_color']);
  if ($beforeCp === null || $afterCp === null) throw new RuntimeException('Stockfish no devolvió una evaluación completa.');
  $decision = training_scenario_decision($beforeCp, $afterCp, (int)$scenario['acceptance_loss_cp'], strtolower($moveUci) === strtolower((string)($before['bestmove'] ?? '')));
  $attempts = (int)$run['attempts_count'] + 1;
  $san = chess_uci_to_san($fen, $moveUci) ?? chess_uci_fallback($moveUci);
  $moveFeedback = $decision['accepted']
    ? ($decision['bucket'] === 'optimal' ? 'Muy buena decisión. Mantienes todo el potencial de la posición.' : 'Buena alternativa. Mantiene el objetivo del escenario.')
    : 'La jugada cede demasiado valor. La posición se mantiene para que pruebes otra idea.';
  training_scenario_event($runId, $userId, 'user', $decision['accepted'] ? 'move' : 'retry', [
    'fen_before' => $fen, 'fen_after' => $fenAfterUser, 'move_uci' => strtolower($moveUci), 'move_san' => $san,
    'score_before_cp' => $beforeCp, 'score_after_cp' => $afterCp, 'centipawn_loss' => $decision['loss_cp'],
    'decision_bucket' => $decision['bucket'], 'accepted' => $decision['accepted'], 'feedback_text' => $moveFeedback,
  ]);
  if (!$decision['accepted']) {
    db()->prepare('UPDATE training_scenario_runs SET attempts_count=?,updated_at=NOW() WHERE id=?')->execute([$attempts, $runId]);
    return ['ok' => true, 'accepted' => false, 'decision' => $decision, 'fen' => $fen, 'feedback' => $moveFeedback];
  }

  $playerMoves = (int)$run['player_moves_count'] + 1;
  $checkmate = training_scenario_is_checkmate($fenAfterUser);
  $afterState = chess_state_from_fen($fenAfterUser);
  $terminalAfterUser = $afterState ? count(pseudo_moves($afterState)) === 0 : false;
  $completed = $terminalAfterUser
    ? training_scenario_objective_met($scenario, $playerMoves, $afterCp, $checkmate)
    : false;
  $currentFen = $fenAfterUser;
  $currentEval = $afterCp;
  $opponent = null;
  if (!$terminalAfterUser) {
    $opponentUci = strtolower((string)($after['bestmove'] ?? ''));
    $fenAfterOpponent = chess_apply_uci_to_fen($fenAfterUser, $opponentUci);
    if ($fenAfterOpponent === null) throw new RuntimeException('Stockfish no devolvió una respuesta legal completa.');
    $opponentSan = chess_uci_to_san($fenAfterUser, $opponentUci) ?? chess_uci_fallback($opponentUci);
    $nextEval = $engine->evaluate($fenAfterOpponent);
    $currentEval = training_scenario_player_score($nextEval, $fenAfterOpponent, (string)$scenario['player_color']);
    if ($currentEval === null) throw new RuntimeException('Stockfish no devolvió la evaluación de continuación.');
    training_scenario_event($runId, $userId, 'opponent', 'move', [
      'fen_before' => $fenAfterUser, 'fen_after' => $fenAfterOpponent, 'move_uci' => $opponentUci, 'move_san' => $opponentSan,
      'score_before_cp' => $afterCp, 'score_after_cp' => $currentEval, 'accepted' => true,
    ]);
    $opponent = ['uci' => $opponentUci, 'san' => $opponentSan];
    $currentFen = $fenAfterOpponent;
    $completed = $scenario['scenario_type'] === 'mate'
      ? false
      : training_scenario_objective_met($scenario, $playerMoves, $currentEval, false);
  }
  $currentState = chess_state_from_fen($currentFen);
  $terminalCurrent = $currentState ? count(pseudo_moves($currentState)) === 0 : false;
  $failed = !$completed && ($terminalAfterUser || $terminalCurrent || $playerMoves >= (int)$scenario['max_player_moves']);
  $status = $completed ? 'completed' : ($failed ? 'failed' : 'active');
  $qualityAttempts = 1 + max(0, $attempts - $playerMoves);
  $quality = $status === 'active' ? null : training_resolution_quality($completed ? 'solved' : 'failed', $qualityAttempts, (int)$run['highest_hint_level']);
  $weight = training_difficulty_evidence_weight((string)$scenario['difficulty']);
  db()->prepare('UPDATE training_scenario_runs SET current_fen=?,current_eval_cp=?,player_moves_count=?,attempts_count=?,status=?,
                 duration_ms=IF(?="active",duration_ms,TIMESTAMPDIFF(MICROSECOND,started_at,NOW()) DIV 1000),quality_score=?,evidence_weight=?,
                 completed_at=IF(?="active",NULL,NOW()),updated_at=NOW() WHERE id=?')
    ->execute([$currentFen, $currentEval, $playerMoves, $attempts, $status, $status, $quality, $weight, $status, $runId]);
  if ($run['session_item_id'] && $status !== 'active') {
    db()->prepare('UPDATE training_session_items SET status=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=?')
      ->execute([$completed ? 'completed' : 'failed', (int)$run['session_item_id'], $userId]);
  }
  if ($status !== 'active') training_scenario_event($runId, $userId, 'system', 'completion', ['fen_after' => $currentFen, 'score_after_cp' => $currentEval, 'accepted' => $completed]);
  if ($status !== 'active') {
    try {
      player_progress_record_event(
        $userId, 'scenario_run:' . $runId, 'exercise_resolution', 'scenario_run', $runId, $quality, $weight,
        ['scenario_id' => (int)$scenario['id'], 'status' => $status, 'attempts_count' => $attempts,
         'retry_count' => max(0, $attempts - $playerMoves), 'highest_hint_level' => (int)$run['highest_hint_level'],
         'difficulty' => (string)$scenario['difficulty'], 'player_moves' => $playerMoves]
      );
    } catch (Throwable $progressError) {
      error_log('Scenario progress event failed: ' . $progressError->getMessage());
    }
  }
  $updated = training_scenario_run_for_user($runId, $userId);
  return [
    'ok' => true, 'accepted' => true, 'decision' => $decision, 'player_move' => ['uci' => strtolower($moveUci), 'san' => $san],
    'opponent_move' => $opponent, 'player_fen' => $fenAfterUser, 'completed' => $completed, 'failed' => $failed, 'scenario' => training_scenario_public($scenario, $updated),
    'feedback' => $completed ? 'Objetivo completado ante la mejor defensa de Stockfish.' : ($failed ? 'Se alcanzó el límite del escenario sin cumplir el objetivo.' : 'Buena decisión. Stockfish ha respondido con su mejor defensa.'),
  ];
}

function training_scenario_hint(int $userId, int $runId, int $level): array {
  $run = training_scenario_run_for_user($runId, $userId);
  if (!$run || $run['status'] !== 'active') throw new InvalidArgumentException('El escenario ya no está activo.');
  $scenario = training_scenario_for_user((int)$run['scenario_id'], $userId);
  $eval = training_scenario_engine()->evaluate((string)$run['current_fen']);
  $best = strtolower((string)($eval['bestmove'] ?? ''));
  $level = max(1, min(3, $level));
  $from = substr($best, 0, 2);
  $to = substr($best, 2, 2);
  $zone = ((int)$to[1] >= 5 ? 'la mitad superior' : 'la mitad inferior') . ' del tablero';
  $text = $level === 1 ? 'Busca el recurso principal en ' . $zone . '.'
    : ($level === 2 ? 'La pieza que debes considerar está en ' . $from . '.'
      : match ($scenario['scenario_type']) {
        'mate' => 'Prioriza jaques y limita las casillas de escape del rey.',
        'defense' => 'Busca una jugada que reduzca la iniciativa rival y mantenga piezas defendidas.',
        default => 'Evita conceder contrajuego y conserva las piezas activas.',
      });
  db()->prepare('UPDATE training_scenario_runs SET highest_hint_level=GREATEST(highest_hint_level,?),updated_at=NOW() WHERE id=?')->execute([$level, $runId]);
  training_scenario_event($runId, $userId, 'coach', 'hint', ['feedback_text' => $text, 'metadata' => ['level' => $level, 'origin_square' => $level === 2 ? $from : null]]);
  return ['ok' => true, 'level' => $level, 'text' => $text, 'origin_square' => $level === 2 ? $from : null];
}

function training_scenario_why(int $userId, int $runId): array {
  $run = training_scenario_run_for_user($runId, $userId);
  if (!$run) throw new InvalidArgumentException('Escenario no encontrado.');
  $scenario = training_scenario_for_user((int)$run['scenario_id'], $userId);
  $text = match ($scenario['scenario_type']) {
    'mate' => 'El objetivo es mantener una secuencia forzada: cada decisión debe restringir las defensas del rey.',
    'defense' => 'El objetivo es resistir contra la mejor continuación rival, no memorizar una única jugada.',
    default => 'El objetivo es conservar la ventaja contra respuestas precisas, evitando decisiones que cedan demasiado valor.',
  };
  db()->prepare('UPDATE training_scenario_runs SET why_count=why_count+1,updated_at=NOW() WHERE id=?')->execute([$runId]);
  training_scenario_event($runId, $userId, 'coach', 'explanation', ['feedback_text' => $text]);
  return ['ok' => true, 'text' => $text];
}

function training_scenario_skip(int $userId, int $runId): array {
  $run = training_scenario_run_for_user($runId, $userId);
  if (!$run || $run['status'] !== 'active') throw new InvalidArgumentException('El escenario ya no está activo.');
  db()->prepare('UPDATE training_scenario_runs SET status="skipped",completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$runId]);
  if ($run['session_item_id']) db()->prepare('UPDATE training_session_items SET status="skipped",completed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=?')->execute([(int)$run['session_item_id'], $userId]);
  training_scenario_event($runId, $userId, 'system', 'skip');
  return ['ok' => true, 'skipped' => true];
}
