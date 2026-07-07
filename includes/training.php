<?php
require_once __DIR__ . '/db.php';

function training_exercise_types(): array {
  return [
    'recommended' => [
      'label' => 'Recomendado para mí',
      'description' => 'Ejercicios priorizados por tus errores, Smart Tags y foco actual.',
    ],
    'find_best_move' => [
      'label' => 'Encuentra la mejor jugada',
      'description' => 'Busca la mejor alternativa disponible en la posición.',
    ],
    'avoid_blunder' => [
      'label' => 'Evita la omisión grave',
      'description' => 'Corrige una jugada que cambió de forma seria la evaluación.',
    ],
    'find_mate' => [
      'label' => 'Encuentra el mate',
      'description' => 'Aprovecha una secuencia de mate detectada por el análisis.',
    ],
    'spot_threat' => [
      'label' => 'Detecta la amenaza rival',
      'description' => 'Encuentra la respuesta que evita una amenaza importante.',
    ],
    'find_tactic' => [
      'label' => 'Encuentra la táctica',
      'description' => 'Entrena golpes tácticos nacidos de tus partidas.',
    ],
    'defend_position' => [
      'label' => 'Defiende la posición',
      'description' => 'Encuentra el recurso defensivo que mantiene la partida viva.',
    ],
    'convert_advantage' => [
      'label' => 'Convierte la ventaja',
      'description' => 'Practica posiciones donde había que transformar una ventaja.',
    ],
    'other' => [
      'label' => 'Otros',
      'description' => 'Posiciones instructivas de baja prioridad, incluidas jugadas del rival.',
    ],
  ];
}

function training_exercise_type_label(string $type): string {
  $types = training_exercise_types();
  return $types[$type]['label'] ?? $types['other']['label'];
}

function training_move_side(int $ply): string {
  return $ply % 2 === 1 ? 'w' : 'b';
}

function training_user_side(array $game, string $username): ?string {
  $user = strtolower(trim($username));
  if ($user === '') return null;
  if ($user === strtolower(trim((string)($game['white_player'] ?? '')))) return 'w';
  if ($user === strtolower(trim((string)($game['black_player'] ?? '')))) return 'b';
  return null;
}

function training_source_side(array $move, ?string $userSide): string {
  if ($userSide === null) return 'opponent';
  return training_move_side((int)($move['ply'] ?? 0)) === $userSide ? 'user' : 'opponent';
}

function training_valid_solution(?string $uci): bool {
  $uci = strtolower(trim((string)$uci));
  if ($uci === '' || $uci === '(none)' || $uci === '0000') return false;
  return (bool)preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $uci);
}

function training_tag_codes(array $tags): array {
  $codes = [];
  foreach ($tags as $tag) {
    $code = (string)($tag['tag_code'] ?? '');
    if ($code !== '') $codes[$code] = true;
  }
  return array_keys($codes);
}

function training_exercise_type_for_candidate(array $move, array $moveTags, array $gameTags, string $sourceSide): string {
  if ($sourceSide === 'opponent') return 'other';

  $codes = array_merge(training_tag_codes($moveTags), training_tag_codes($gameTags));
  $classification = (string)($move['classification'] ?? 'ok');
  $loss = (int)($move['centipawn_loss'] ?? 0);
  $beforeType = (string)($move['score_before_type'] ?? 'cp');

  if (in_array('missed_mate', $codes, true) || $beforeType === 'mate') return 'find_mate';
  if (in_array('allowed_mate', $codes, true)) return 'spot_threat';
  if (in_array('lost_winning_position', $codes, true)) return 'convert_advantage';
  if (in_array('endgame_mistake', $codes, true)) return 'defend_position';
  if ($classification === 'blunder' || $loss >= 300) return 'avoid_blunder';
  if ($classification === 'mistake' || in_array('mistake_own', $codes, true)) return 'find_tactic';

  return 'find_best_move';
}

function training_difficulty_for_candidate(array $move, array $moveTags = []): string {
  $loss = (int)($move['centipawn_loss'] ?? 0);
  $classification = (string)($move['classification'] ?? 'ok');
  $codes = training_tag_codes($moveTags);

  if (in_array('missed_mate', $codes, true) || in_array('allowed_mate', $codes, true)) return 'critical';
  if ($classification === 'blunder' || $loss >= 300) return 'hard';
  if ($classification === 'mistake' || $loss >= 150) return 'medium';
  return 'easy';
}

function training_priority_score(array $move, array $moveTags, array $gameTags, string $sourceSide, ?string $focusCode = null): int {
  $score = $sourceSide === 'user' ? 20 : 5;
  $classification = (string)($move['classification'] ?? 'ok');
  $loss = (int)($move['centipawn_loss'] ?? 0);
  $codes = array_merge(training_tag_codes($moveTags), training_tag_codes($gameTags));

  if ($sourceSide === 'opponent') {
    return max(1, min(30, $score + min(20, (int)floor($loss / 50))));
  }

  if ($classification === 'blunder') $score += 80;
  elseif ($classification === 'mistake') $score += 55;
  elseif ($classification === 'inaccuracy') $score += 25;

  $tagWeights = [
    'missed_mate' => 120,
    'allowed_mate' => 110,
    'blunder_own' => 100,
    'lost_winning_position' => 80,
    'mistake_own' => 70,
    'endgame_mistake' => 60,
    'opening_issue' => 30,
    'inaccuracy_own' => 25,
  ];
  foreach ($codes as $code) {
    $score += $tagWeights[$code] ?? 0;
  }

  $focusTags = [
    'tactics' => ['blunder_own', 'mistake_own', 'missed_mate', 'allowed_mate'],
    'accuracy' => ['inaccuracy_own'],
    'opening' => ['opening_issue'],
    'endgame' => ['endgame_mistake'],
    'conversion' => ['lost_winning_position'],
  ];
  if ($focusCode && !empty($focusTags[$focusCode]) && array_intersect($codes, $focusTags[$focusCode])) {
    $score += 40;
  }

  return max(1, min(999, $score));
}

function training_prompt_for_type(string $type, string $sideToMove): string {
  $side = $sideToMove === 'b' ? 'negras' : 'blancas';
  return match ($type) {
    'avoid_blunder' => "Evita la omisión grave. Juegan {$side}.",
    'find_mate' => "Encuentra el mate. Juegan {$side}.",
    'spot_threat' => "Detecta la amenaza rival y encuentra la mejor defensa. Juegan {$side}.",
    'find_tactic' => "Encuentra la táctica. Juegan {$side}.",
    'defend_position' => "Defiende la posición. Juegan {$side}.",
    'convert_advantage' => "Convierte la ventaja. Juegan {$side}.",
    'other' => "Encuentra la mejor jugada en esta posición. Juegan {$side}.",
    default => "Encuentra la mejor jugada. Juegan {$side}.",
  };
}

function training_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
}

function training_feedback_success(string $type): string {
  return match ($type) {
    'avoid_blunder' => 'Correcto. Has evitado la jugada que rompía la posición.',
    'find_mate' => 'Correcto. Esa era la jugada crítica para aprovechar la red de mate.',
    'spot_threat' => 'Correcto. Has encontrado el recurso que neutraliza la amenaza.',
    'find_tactic' => 'Correcto. Esa era la táctica indicada por el análisis.',
    'defend_position' => 'Correcto. Has encontrado una defensa importante.',
    'convert_advantage' => 'Correcto. Esa jugada ayuda a transformar la ventaja.',
    default => 'Correcto. Coincide con la mejor jugada almacenada por Stockfish.',
  };
}

function training_feedback_failure(string $type): string {
  return match ($type) {
    'avoid_blunder' => 'Todavía no. Busca una jugada que evite la caída grande de evaluación.',
    'find_mate' => 'Todavía no. Hay una jugada de mate o una continuación decisiva.',
    'spot_threat' => 'Todavía no. Revisa qué amenaza tiene el rival.',
    'find_tactic' => 'Todavía no. Busca una idea táctica concreta.',
    'defend_position' => 'Todavía no. La posición necesita una defensa precisa.',
    'convert_advantage' => 'Todavía no. Busca cómo mantener o aumentar la ventaja.',
    default => 'Todavía no. Intenta encontrar la mejor jugada de la posición.',
  };
}

function training_stats_for_user(int $userId): array {
  $st = db()->prepare('SELECT
      COUNT(*) AS total,
      SUM(status="active" AND resolved_at IS NULL) AS pending,
      SUM(resolved_at IS NOT NULL) AS resolved,
      SUM(status="archived") AS archived
    FROM training_exercises
    WHERE user_id=?');
  $st->execute([$userId]);
  $row = $st->fetch() ?: [];

  $attempts = db()->prepare('SELECT
      COUNT(*) AS total_attempts,
      SUM(result="solved") AS solved_attempts,
      SUM(result="failed") AS failed_attempts,
      SUM(result="skipped") AS skipped_attempts,
      AVG(NULLIF(duration_ms,0)) AS avg_duration_ms
    FROM training_attempts
    WHERE user_id=?');
  $attempts->execute([$userId]);
  $attemptRow = $attempts->fetch() ?: [];

  return [
    'total' => (int)($row['total'] ?? 0),
    'pending' => (int)($row['pending'] ?? 0),
    'resolved' => (int)($row['resolved'] ?? 0),
    'archived' => (int)($row['archived'] ?? 0),
    'attempts' => [
      'total' => (int)($attemptRow['total_attempts'] ?? 0),
      'solved' => (int)($attemptRow['solved_attempts'] ?? 0),
      'failed' => (int)($attemptRow['failed_attempts'] ?? 0),
      'skipped' => (int)($attemptRow['skipped_attempts'] ?? 0),
      'avg_duration_ms' => $attemptRow['avg_duration_ms'] === null ? null : (int)round((float)$attemptRow['avg_duration_ms']),
    ],
  ];
}

function training_analysis_game(int $analysisId, int $userId): ?array {
  $st = db()->prepare('SELECT a.id AS analysis_id, a.game_id, a.user_id, a.status,
                              g.white_player, g.black_player, g.user_result, g.result_raw,
                              u.username
                       FROM game_analysis a
                       JOIN games g ON g.id=a.game_id
                       JOIN users u ON u.id=a.user_id
                       WHERE a.id=? AND a.user_id=? AND a.status IN ("running","done")');
  $st->execute([$analysisId, $userId]);
  $row = $st->fetch();
  return $row ?: null;
}

function training_tags_for_analysis(int $analysisId, int $userId): array {
  $gameSt = db()->prepare('SELECT gt.tag_code, d.label, d.category, d.severity
                           FROM game_tags gt
                           JOIN smart_tag_definitions d ON d.code=gt.tag_code
                           WHERE gt.analysis_id=? AND gt.user_id=?');
  $gameSt->execute([$analysisId, $userId]);
  $gameTags = $gameSt->fetchAll();

  $moveSt = db()->prepare('SELECT mt.move_analysis_id, mt.tag_code, d.label, d.category, mt.severity
                           FROM move_tags mt
                           JOIN smart_tag_definitions d ON d.code=mt.tag_code
                           WHERE mt.analysis_id=? AND mt.user_id=?');
  $moveSt->execute([$analysisId, $userId]);
  $moveTags = [];
  foreach ($moveSt->fetchAll() as $tag) {
    $moveTags[(int)$tag['move_analysis_id']][] = $tag;
  }

  return ['game_tags' => $gameTags, 'move_tags' => $moveTags];
}

function training_candidate_is_useful(array $move, array $moveTags, array $gameTags, string $sourceSide): bool {
  if (!training_valid_solution($move['bestmove'] ?? null)) return false;

  $classification = (string)($move['classification'] ?? 'ok');
  $loss = (int)($move['centipawn_loss'] ?? 0);
  if ($sourceSide === 'opponent') return true;
  if ($classification !== 'ok' || $loss >= 70) return true;

  $codes = array_merge(training_tag_codes($moveTags), training_tag_codes($gameTags));
  $usefulTags = [
    'missed_mate',
    'allowed_mate',
    'lost_winning_position',
    'endgame_mistake',
    'opening_issue',
    'blunder_own',
    'mistake_own',
    'inaccuracy_own',
  ];
  return (bool)array_intersect($codes, $usefulTags);
}

function training_insert_exercise_tags(int $exerciseId, array $tags, string $source): void {
  if (!$tags) return;
  $st = db()->prepare('INSERT INTO training_exercise_tags (exercise_id,tag_code,source,created_at)
                       VALUES (?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE tag_code=tag_code');
  foreach (training_tag_codes($tags) as $code) {
    $st->execute([$exerciseId, $code, $source]);
  }
}

function training_insert_exercise(int $userId, array $game, array $move, array $moveTags, array $gameTags, ?string $focusCode = null): array {
  $sourceSide = training_source_side($move, training_user_side($game, (string)($game['username'] ?? '')));
  if (!training_candidate_is_useful($move, $moveTags, $gameTags, $sourceSide)) {
    return ['ok' => true, 'created' => false, 'skipped' => true, 'reason' => 'not-useful'];
  }

  $type = training_exercise_type_for_candidate($move, $moveTags, $gameTags, $sourceSide);
  $difficulty = training_difficulty_for_candidate($move, $moveTags);
  $priority = training_priority_score($move, $moveTags, $gameTags, $sourceSide, $focusCode);
  $fen = (string)($move['fen_before'] ?? '');
  $sideToMove = training_fen_side_to_move($fen);
  $solution = strtolower(trim((string)($move['bestmove'] ?? '')));

  $exists = db()->prepare('SELECT id FROM training_exercises WHERE user_id=? AND move_analysis_id=? AND exercise_type=? LIMIT 1');
  $exists->execute([$userId, (int)$move['id'], $type]);
  $existingId = (int)($exists->fetchColumn() ?: 0);

  $sql = 'INSERT INTO training_exercises
            (user_id,game_id,analysis_id,move_analysis_id,ply,source_side,exercise_type,fen,solution_uci,solution_san,
             played_uci,played_san,centipawn_loss,classification,difficulty,priority_score,source_focus_code,prompt,
             feedback_success,feedback_failure,status,created_at)
          VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, \'active\', NOW())
          ON DUPLICATE KEY UPDATE
            id=LAST_INSERT_ID(id),
            fen=VALUES(fen),
            solution_uci=VALUES(solution_uci),
            solution_san=VALUES(solution_san),
            played_uci=VALUES(played_uci),
            played_san=VALUES(played_san),
            centipawn_loss=VALUES(centipawn_loss),
            classification=VALUES(classification),
            difficulty=VALUES(difficulty),
            priority_score=VALUES(priority_score),
            source_focus_code=VALUES(source_focus_code),
            prompt=VALUES(prompt),
            feedback_success=VALUES(feedback_success),
            feedback_failure=VALUES(feedback_failure)';
  $st = db()->prepare($sql);
  $st->execute([
    $userId,
    (int)$game['game_id'],
    (int)$game['analysis_id'],
    (int)$move['id'],
    (int)$move['ply'],
    $sourceSide,
    $type,
    $fen,
    $solution,
    null,
    strtolower(trim((string)($move['uci'] ?? ''))),
    (string)($move['san'] ?? ''),
    (int)($move['centipawn_loss'] ?? 0),
    (string)($move['classification'] ?? 'ok'),
    $difficulty,
    $priority,
    $focusCode,
    training_prompt_for_type($type, $sideToMove),
    training_feedback_success($type),
    training_feedback_failure($type),
  ]);

  $exerciseId = (int)db()->lastInsertId();
  training_insert_exercise_tags($exerciseId, $moveTags, 'move');
  training_insert_exercise_tags($exerciseId, $gameTags, 'game');

  return [
    'ok' => true,
    'exercise_id' => $exerciseId,
    'created' => $existingId === 0,
    'skipped' => $existingId !== 0,
    'type' => $type,
    'source_side' => $sourceSide,
  ];
}

function training_generate_for_analysis(int $analysisId, int $userId, ?string $focusCode = null): array {
  $game = training_analysis_game($analysisId, $userId);
  if (!$game) return ['ok' => false, 'error' => 'Análisis no encontrado o no completado.'];

  $movesSt = db()->prepare('SELECT * FROM game_move_analysis WHERE analysis_id=? ORDER BY ply');
  $movesSt->execute([$analysisId]);
  $moves = $movesSt->fetchAll();
  if (!$moves) {
    return ['ok' => true, 'processed_moves' => 0, 'created_exercises' => 0, 'skipped_existing' => 0, 'message' => 'No hay jugadas analizadas.'];
  }

  $tags = training_tags_for_analysis($analysisId, $userId);
  $created = 0;
  $skipped = 0;
  $processed = 0;
  $errors = [];

  foreach ($moves as $move) {
    $processed++;
    try {
      $moveTags = $tags['move_tags'][(int)$move['id']] ?? [];
      $result = training_insert_exercise($userId, $game, $move, $moveTags, $tags['game_tags'], $focusCode);
      if (!empty($result['created'])) $created++;
      elseif (!empty($result['skipped'])) $skipped++;
    } catch (Throwable $e) {
      $errors[] = $e->getMessage();
    }
  }

  return [
    'ok' => !$errors,
    'processed_moves' => $processed,
    'created_exercises' => $created,
    'skipped_existing' => $skipped,
    'error_count' => count($errors),
    'errors' => $errors,
    'message' => $errors ? 'Generación de ejercicios completada con errores parciales.' : 'Ejercicios generados correctamente.',
  ];
}

function training_backfill_pending_count(int $userId): int {
  $sql = 'SELECT COUNT(*)
          FROM game_analysis a
          WHERE a.user_id=?
            AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            AND NOT EXISTS (SELECT 1 FROM training_exercises te WHERE te.analysis_id=a.id AND te.user_id=?)';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  return (int)$st->fetchColumn();
}

function training_backfill_batch(int $userId, int $limit = 10, string $trigger = 'profile-page'): array {
  $limit = max(1, min(50, $limit));
  $started = microtime(true);
  $pendingBefore = training_backfill_pending_count($userId);
  $runId = null;

  try {
    $run = db()->prepare('INSERT INTO training_generation_runs (user_id,trigger_source,status,started_at) VALUES (?,?,"running",NOW())');
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
            AND NOT EXISTS (SELECT 1 FROM training_exercises te WHERE te.analysis_id=a.id AND te.user_id=?)
          ORDER BY COALESCE(a.completed_at,a.updated_at,a.created_at) DESC, a.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  $analysisIds = array_map('intval', array_column($st->fetchAll(), 'id'));

  $processedMoves = 0;
  $created = 0;
  $skipped = 0;
  $errors = [];

  foreach ($analysisIds as $analysisId) {
    try {
      $result = training_generate_for_analysis($analysisId, $userId);
      $processedMoves += (int)($result['processed_moves'] ?? 0);
      $created += (int)($result['created_exercises'] ?? 0);
      $skipped += (int)($result['skipped_existing'] ?? 0);
      foreach (($result['errors'] ?? []) as $error) $errors[] = $error;
      if (empty($result['ok']) && !empty($result['error'])) $errors[] = $result['error'];
    } catch (Throwable $e) {
      $errors[] = $e->getMessage();
    }
  }

  $pendingAfter = training_backfill_pending_count($userId);
  $durationMs = (int)round((microtime(true) - $started) * 1000);
  $message = $analysisIds
    ? 'Backfill de ejercicios ejecutado correctamente.'
    : 'No hay análisis pendientes de convertir en ejercicios.';
  if ($errors) $message = 'Backfill de ejercicios completado con errores parciales.';

  if ($runId) {
    try {
      $up = db()->prepare('UPDATE training_generation_runs
                           SET status=?, processed_moves=?, created_exercises=?, skipped_existing=?,
                               error_count=?, duration_ms=?, message=?, error_message=?, completed_at=NOW()
                           WHERE id=?');
      $up->execute([
        $errors ? 'error' : 'done',
        $processedMoves,
        $created,
        $skipped,
        count($errors),
        $durationMs,
        $message,
        $errors ? implode(' | ', array_slice($errors, 0, 5)) : null,
        $runId,
      ]);
    } catch (Throwable $e) {
      // The backfill result should still be returned if run logging fails.
    }
  }

  return [
    'ok' => !$errors,
    'run_id' => $runId,
    'processed_analyses' => count($analysisIds),
    'processed_moves' => $processedMoves,
    'created_exercises' => $created,
    'skipped_existing' => $skipped,
    'error_count' => count($errors),
    'pending_before' => $pendingBefore,
    'pending_after' => $pendingAfter,
    'duration_ms' => $durationMs,
    'message' => $message,
    'errors' => $errors,
  ];
}
