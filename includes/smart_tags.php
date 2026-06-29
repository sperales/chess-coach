<?php

function smart_tag_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
}

function smart_tag_score_to_white(?int $score, string $type, ?string $fen): int {
  $raw = (int)($score ?? 0);
  $side = smart_tag_fen_side_to_move($fen);
  if ($type === 'mate') {
    $cpForSideToMove = $raw > 0
      ? 100000 - abs($raw) * 1000
      : -100000 + abs($raw) * 1000;
    return $side === 'w' ? $cpForSideToMove : -$cpForSideToMove;
  }
  return $side === 'w' ? $raw : -$raw;
}

function smart_tag_user_side(array $game, string $username): ?string {
  $user = strtolower(trim($username));
  $white = strtolower(trim((string)($game['white_player'] ?? '')));
  $black = strtolower(trim((string)($game['black_player'] ?? '')));
  if ($user !== '' && $white !== '' && $user === $white) return 'w';
  if ($user !== '' && $black !== '' && $user === $black) return 'b';
  return null;
}

function smart_tag_move_side(array $move): string {
  return ((int)($move['ply'] ?? 0)) % 2 === 1 ? 'w' : 'b';
}

function smart_tag_eval_for_side(int $evalWhite, string $side): int {
  return $side === 'w' ? $evalWhite : -$evalWhite;
}

function smart_tag_details(array $data): string {
  return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function smart_tag_clear_analysis(int $analysisId, int $userId): void {
  db()->prepare('DELETE FROM move_tags WHERE analysis_id=? AND user_id=?')->execute([$analysisId, $userId]);
  db()->prepare('DELETE FROM game_tags WHERE analysis_id=? AND user_id=?')->execute([$analysisId, $userId]);
}

function smart_tag_insert_move(int $userId, int $gameId, int $analysisId, array $move, string $tagCode, string $severity, float $confidence, array $details = []): void {
  $st = db()->prepare('INSERT INTO move_tags (user_id,game_id,analysis_id,move_analysis_id,ply,tag_code,confidence,severity,details_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE confidence=VALUES(confidence), severity=VALUES(severity), details_json=VALUES(details_json)');
  $st->execute([
    $userId,
    $gameId,
    $analysisId,
    (int)$move['id'],
    (int)$move['ply'],
    $tagCode,
    number_format($confidence, 2, '.', ''),
    $severity,
    $details ? smart_tag_details($details) : null,
  ]);
}

function smart_tag_insert_game(int $userId, int $gameId, int $analysisId, string $tagCode, float $confidence, int $evidenceCount, ?int $primaryPly, array $details = []): void {
  $st = db()->prepare('INSERT INTO game_tags (user_id,game_id,analysis_id,tag_code,confidence,evidence_count,primary_ply,details_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE confidence=VALUES(confidence), evidence_count=VALUES(evidence_count), primary_ply=VALUES(primary_ply), details_json=VALUES(details_json)');
  $st->execute([
    $userId,
    $gameId,
    $analysisId,
    $tagCode,
    number_format($confidence, 2, '.', ''),
    $evidenceCount,
    $primaryPly,
    $details ? smart_tag_details($details) : null,
  ]);
}

function smart_tag_move_payload(array $move, string $userSide): array {
  $beforeWhite = smart_tag_score_to_white(
    is_null($move['score_before']) ? null : (int)$move['score_before'],
    $move['score_before_type'] ?? 'cp',
    $move['fen_before'] ?? ''
  );
  $afterWhite = smart_tag_score_to_white(
    is_null($move['score_after']) ? null : (int)$move['score_after'],
    $move['score_after_type'] ?? 'cp',
    $move['fen_after'] ?? ''
  );
  $movingSide = smart_tag_move_side($move);
  return [
    'row' => $move,
    'ply' => (int)$move['ply'],
    'san' => (string)($move['san'] ?? ''),
    'uci' => (string)($move['uci'] ?? ''),
    'loss' => (int)($move['centipawn_loss'] ?? 0),
    'classification' => (string)($move['classification'] ?? 'ok'),
    'moving_side' => $movingSide,
    'is_user_move' => $movingSide === $userSide,
    'before_white' => $beforeWhite,
    'after_white' => $afterWhite,
    'before_user' => smart_tag_eval_for_side($beforeWhite, $userSide),
    'after_user' => smart_tag_eval_for_side($afterWhite, $userSide),
    'score_before_type' => (string)($move['score_before_type'] ?? 'cp'),
    'score_after_type' => (string)($move['score_after_type'] ?? 'cp'),
  ];
}

function smart_tag_generate_for_analysis(int $analysisId, int $userId): array {
  $st = db()->prepare('SELECT a.id AS analysis_id, a.game_id, a.user_id, a.status, g.white_player, g.black_player, g.result_raw, g.user_result, u.username
                       FROM game_analysis a
                       JOIN games g ON g.id=a.game_id
                       JOIN users u ON u.id=a.user_id
                       WHERE a.id=? AND a.user_id=?');
  $st->execute([$analysisId, $userId]);
  $game = $st->fetch();
  if (!$game) return ['ok' => false, 'error' => 'Análisis no encontrado.'];

  $movesSt = db()->prepare('SELECT * FROM game_move_analysis WHERE analysis_id=? ORDER BY ply');
  $movesSt->execute([$analysisId]);
  $moves = $movesSt->fetchAll();
  smart_tag_clear_analysis($analysisId, $userId);
  if (!$moves) return ['ok' => true, 'game_tags' => 0, 'move_tags' => 0, 'message' => 'No hay jugadas analizadas.'];

  $gameId = (int)$game['game_id'];
  $userSide = smart_tag_user_side($game, (string)$game['username']);
  if ($userSide === null) {
    return ['ok' => true, 'game_tags' => 0, 'move_tags' => 0, 'message' => 'No se pudo identificar el color del usuario.'];
  }

  $taggedMoves = 0;
  $payloads = array_map(fn($move) => smart_tag_move_payload($move, $userSide), $moves);
  $ownMoves = array_values(array_filter($payloads, fn($move) => $move['is_user_move']));
  $ownCritical = [];

  foreach ($ownMoves as $move) {
    $details = [
      'ply' => $move['ply'],
      'san' => $move['san'],
      'uci' => $move['uci'],
      'loss' => $move['loss'],
      'before_user' => $move['before_user'],
      'after_user' => $move['after_user'],
    ];
    if ($move['classification'] === 'blunder') {
      smart_tag_insert_move($userId, $gameId, $analysisId, $move['row'], 'blunder_own', 'critical', 1.0, $details);
      $ownCritical[] = $move;
      $taggedMoves++;
    } elseif ($move['classification'] === 'mistake') {
      smart_tag_insert_move($userId, $gameId, $analysisId, $move['row'], 'mistake_own', 'high', 1.0, $details);
      $ownCritical[] = $move;
      $taggedMoves++;
    } elseif ($move['classification'] === 'inaccuracy') {
      smart_tag_insert_move($userId, $gameId, $analysisId, $move['row'], 'inaccuracy_own', 'low', 1.0, $details);
      $taggedMoves++;
    }

    if ($move['score_before_type'] === 'mate' && $move['before_user'] > 50000 && $move['after_user'] < 50000) {
      smart_tag_insert_move($userId, $gameId, $analysisId, $move['row'], 'missed_mate', 'critical', 0.95, $details);
      $taggedMoves++;
    }
    if ($move['score_after_type'] === 'mate' && $move['after_user'] < -50000) {
      smart_tag_insert_move($userId, $gameId, $analysisId, $move['row'], 'allowed_mate', 'critical', 0.95, $details);
      $taggedMoves++;
    }
  }

  $gameTags = 0;
  $totalPly = count($payloads);
  $openingCritical = array_values(array_filter($ownCritical, fn($move) => $move['ply'] <= 16));
  if ($openingCritical) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'opening_issue', 0.9, count($openingCritical), $openingCritical[0]['ply'], ['plies' => array_column($openingCritical, 'ply')]);
    $gameTags++;
  }

  $endgameStart = max(1, (int)floor($totalPly * 0.75));
  $endgameCritical = array_values(array_filter($ownCritical, fn($move) => $move['ply'] >= $endgameStart && $totalPly >= 24));
  if ($endgameCritical) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'endgame_mistake', 0.9, count($endgameCritical), $endgameCritical[0]['ply'], ['plies' => array_column($endgameCritical, 'ply')]);
    $gameTags++;
  }

  $lostWinning = array_values(array_filter($ownMoves, fn($move) => $move['before_user'] >= 300 && $move['after_user'] <= 80 && $move['loss'] >= 150));
  if ($lostWinning) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'lost_winning_position', 0.95, count($lostWinning), $lostWinning[0]['ply'], ['plies' => array_column($lostWinning, 'ply')]);
    $gameTags++;
  }

  $minUserEval = null;
  $maxUserEval = null;
  foreach ($payloads as $move) {
    $afterUser = smart_tag_eval_for_side($move['after_white'], $userSide);
    $minUserEval = $minUserEval === null ? $afterUser : min($minUserEval, $afterUser);
    $maxUserEval = $maxUserEval === null ? $afterUser : max($maxUserEval, $afterUser);
  }

  $userResult = (string)($game['user_result'] ?? 'unknown');
  if (in_array($userResult, ['win', 'draw'], true) && $minUserEval !== null && $minUserEval <= -300) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'comeback', 0.85, 1, null, ['min_user_eval' => $minUserEval, 'result' => $userResult]);
    $gameTags++;
  }
  if ($userResult === 'win' && $maxUserEval !== null && $maxUserEval >= 300) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'converted_advantage', 0.85, 1, null, ['max_user_eval' => $maxUserEval, 'result' => $userResult]);
    $gameTags++;
  }

  $ownBlunders = count(array_filter($ownMoves, fn($move) => $move['classification'] === 'blunder'));
  $ownMistakes = count(array_filter($ownMoves, fn($move) => $move['classification'] === 'mistake'));
  $ownInaccuracies = count(array_filter($ownMoves, fn($move) => $move['classification'] === 'inaccuracy'));
  if (count($ownMoves) >= 10 && $ownBlunders === 0 && $ownMistakes === 0 && $ownInaccuracies <= 1) {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'precise_game', 0.8, 1, null, ['own_inaccuracies' => $ownInaccuracies]);
    $gameTags++;
  }

  $finishMoves = array_slice($ownMoves, -max(3, (int)ceil(count($ownMoves) * 0.25)));
  $finishLoss = array_sum(array_map(fn($move) => min(1000, $move['loss']), $finishMoves));
  if (count($finishMoves) >= 3 && $finishLoss <= 80 && $userResult !== 'loss') {
    smart_tag_insert_game($userId, $gameId, $analysisId, 'strong_finish', 0.75, count($finishMoves), $finishMoves[0]['ply'], ['finish_loss' => $finishLoss]);
    $gameTags++;
  }

  return [
    'ok' => true,
    'game_tags' => $gameTags,
    'move_tags' => $taggedMoves,
    'user_side' => $userSide,
  ];
}

function smart_tag_backfill_pending_count(int $userId): int {
  $sql = 'SELECT COUNT(*)
          FROM game_analysis a
          WHERE a.user_id=?
            AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            AND NOT EXISTS (SELECT 1 FROM game_tags gt WHERE gt.analysis_id=a.id AND gt.user_id=?)';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  return (int)$st->fetchColumn();
}

function smart_tag_backfill_batch(int $userId, int $limit = 10, string $trigger = 'profile-page'): array {
  $limit = max(1, min(50, $limit));
  $started = microtime(true);
  $pendingBefore = smart_tag_backfill_pending_count($userId);
  $runId = null;

  try {
    $run = db()->prepare('INSERT INTO smart_tag_runs (user_id,trigger_source,status,started_at) VALUES (?,?,"running",NOW())');
    $run->execute([$userId, $trigger]);
    $runId = (int)db()->lastInsertId();
  } catch (Throwable $e) {
    $runId = null;
  }

  $sql = 'SELECT a.id
          FROM game_analysis a
          WHERE a.user_id=?
            AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            AND NOT EXISTS (SELECT 1 FROM game_tags gt WHERE gt.analysis_id=a.id AND gt.user_id=?)
          ORDER BY COALESCE(a.completed_at,a.updated_at,a.created_at) DESC, a.id DESC
          LIMIT '.(int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  $analysisIds = array_map('intval', array_column($st->fetchAll(), 'id'));

  $processed = 0;
  $tagged = 0;
  $errors = 0;
  $messages = [];

  foreach ($analysisIds as $analysisId) {
    try {
      $result = smart_tag_generate_for_analysis($analysisId, $userId);
      $processed++;
      if (!empty($result['game_tags']) || !empty($result['move_tags'])) $tagged++;
      if (empty($result['ok'])) {
        $errors++;
        if (!empty($result['error'])) $messages[] = $result['error'];
      }
    } catch (Throwable $e) {
      $processed++;
      $errors++;
      $messages[] = $e->getMessage();
    }
  }

  $pendingAfter = smart_tag_backfill_pending_count($userId);
  $durationMs = (int)round((microtime(true) - $started) * 1000);
  $message = $processed === 0
    ? 'No hay partidas pendientes de etiquetar.'
    : 'Backfill de Smart Tags ejecutado correctamente.';
  if ($errors > 0) $message = 'Backfill completado con errores parciales.';

  if ($runId) {
    try {
      $up = db()->prepare('UPDATE smart_tag_runs SET status=?, processed_games=?, tagged_games=?, error_count=?, duration_ms=?, message=?, error_message=?, completed_at=NOW() WHERE id=?');
      $up->execute([
        $errors > 0 ? 'error' : 'done',
        $processed,
        $tagged,
        $errors,
        $durationMs,
        $message,
        $messages ? implode(' | ', array_slice($messages, 0, 5)) : null,
        $runId,
      ]);
    } catch (Throwable $e) {
      // Backfill result should still be returned even if run logging fails.
    }
  }

  return [
    'ok' => $errors === 0,
    'run_id' => $runId,
    'processed_games' => $processed,
    'tagged_games' => $tagged,
    'error_count' => $errors,
    'pending_before' => $pendingBefore,
    'pending_after' => $pendingAfter,
    'duration_ms' => $durationMs,
    'message' => $message,
    'errors' => $messages,
  ];
}
