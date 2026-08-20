<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/chess_server.php';
require_once __DIR__ . '/stockfish.php';
require_once __DIR__ . '/adaptive_analysis.php';
require_once __DIR__ . '/smart_tags.php';
require_once __DIR__ . '/training.php';
require_once __DIR__ . '/openings.php';

function analysis_status_values(): array {
  return ['queued', 'running', 'done', 'error', 'cancelled'];
}

function queue_stats_scope(?int $userId = null): array {
  $params = [];
  $where = '';
  if ($userId !== null) {
    $where = ' WHERE user_id=?';
    $params[] = $userId;
  }
  $st = db()->prepare("SELECT
      SUM(status='queued') AS queued,
      SUM(status='running') AS running,
      SUM(status='done') AS done,
      SUM(status='error') AS errors,
      SUM(status='cancelled') AS cancelled,
      COUNT(*) AS total
    FROM game_analysis{$where}");
  $st->execute($params);
  $r = $st->fetch() ?: [];
  return [
    'queued' => (int)($r['queued'] ?? 0),
    'running' => (int)($r['running'] ?? 0),
    'done' => (int)($r['done'] ?? 0),
    'errors' => (int)($r['errors'] ?? 0),
    'cancelled' => (int)($r['cancelled'] ?? 0),
    'total' => (int)($r['total'] ?? 0),
    'pending_total' => (int)($r['queued'] ?? 0) + (int)($r['running'] ?? 0),
  ];
}

function queue_stats(int $userId): array {
  return queue_stats_scope($userId);
}

function queue_game_analysis(int $gameId, int $userId, bool $force = false): array {
  $st = db()->prepare('SELECT id FROM games WHERE id=? AND user_id=?');
  $st->execute([$gameId, $userId]);
  if (!$st->fetchColumn()) return ['ok' => false, 'error' => 'Partida no encontrada.'];

  if (!$force) {
    $existing = db()->prepare('SELECT id,status FROM game_analysis WHERE game_id=? AND user_id=? AND status IN ("queued","running","done") ORDER BY id DESC LIMIT 1');
    $existing->execute([$gameId, $userId]);
    $row = $existing->fetch();
    if ($row) return ['ok' => true, 'analysis_id' => (int)$row['id'], 'status' => $row['status'], 'existing' => true];
  }

  $cfg = engine_config();
  $ins = db()->prepare('INSERT INTO game_analysis (game_id,user_id,engine_name,engine_depth,status,current_ply,total_ply,attempts,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
  $ins->execute([$gameId, $userId, 'Stockfish', (int)($cfg['depth'] ?? 10), 'queued', 0, 0, 0]);
  return ['ok' => true, 'analysis_id' => (int)db()->lastInsertId(), 'status' => 'queued', 'existing' => false];
}

function queue_missing_games(int $userId): array {
  $sql = 'SELECT g.id FROM games g
          LEFT JOIN game_analysis a ON a.id=(SELECT id FROM game_analysis WHERE game_id=g.id AND user_id=? ORDER BY id DESC LIMIT 1)
          WHERE g.user_id=? AND (a.id IS NULL OR a.status IN ("error","cancelled"))
          ORDER BY COALESCE(g.played_at, g.imported_at) DESC, g.id DESC';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  $added = 0; $existing = 0;
  foreach ($st->fetchAll() as $row) {
    $r = queue_game_analysis((int)$row['id'], $userId, false);
    if (!empty($r['existing'])) $existing++; elseif (!empty($r['ok'])) $added++;
  }
  return ['ok' => true, 'added' => $added, 'existing' => $existing];
}

function queue_retry_errors(int $userId): array {
  $st = db()->prepare('UPDATE game_analysis
                       SET status="queued", error_message=NULL, current_ply=0, total_ply=0,
                           completed_at=NULL, started_at=NULL, engine_exit_code=NULL,
                           engine_error_code=NULL, engine_error_message=NULL, updated_at=NOW()
                       WHERE user_id=? AND status IN ("error","cancelled")');
  $st->execute([$userId]);
  return ['ok' => true, 'updated' => $st->rowCount()];
}

function queue_cancel_waiting(int $userId): array {
  $st = db()->prepare('UPDATE game_analysis SET status="cancelled", error_message=NULL, completed_at=NOW(), updated_at=NOW() WHERE user_id=? AND status="queued"');
  $st->execute([$userId]);
  return ['ok' => true, 'updated' => $st->rowCount()];
}

function queue_request_cancel(int $analysisId, int $userId): array {
  $st = db()->prepare('SELECT status FROM game_analysis WHERE id=? AND user_id=?');
  $st->execute([$analysisId, $userId]);
  $status = $st->fetchColumn();
  if (!$status) return ['ok' => false, 'error' => 'Análisis no encontrado.'];
  if ($status === 'running') {
    db()->prepare('UPDATE game_analysis SET cancel_requested=1, updated_at=NOW() WHERE id=? AND user_id=?')->execute([$analysisId, $userId]);
    return ['ok' => true, 'cancel_requested' => true];
  }
  if ($status === 'queued') {
    db()->prepare('UPDATE game_analysis SET status="cancelled", completed_at=NOW(), updated_at=NOW() WHERE id=? AND user_id=?')->execute([$analysisId, $userId]);
    return ['ok' => true, 'cancelled' => true];
  }
  return ['ok' => true, 'status' => $status];
}

function queue_total_count(int $userId): int {
  $st = db()->prepare('SELECT COUNT(*) FROM game_analysis WHERE user_id=?');
  $st->execute([$userId]);
  return (int)$st->fetchColumn();
}

function queue_list(int $userId, int $limit = 50, int $offset = 0): array {
  $limit = max(1, min(200, $limit));
  $offset = max(0, $offset);
  $sql = 'SELECT a.id AS analysis_id, a.game_id, a.status, a.engine_name, a.engine_version, a.engine_build,
                 a.engine_depth, a.engine_threads, a.engine_hash_mb, a.current_ply, a.total_ply,
                 a.blunders, a.mistakes, a.inaccuracies, a.attempts, a.error_message, a.created_at, a.started_at, a.completed_at, a.updated_at,
                 g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at, g.event_name, g.site, g.source
          FROM game_analysis a
          JOIN games g ON g.id=a.game_id
          WHERE a.user_id=?
          ORDER BY FIELD(a.status, "running","queued","error","cancelled","done"), a.created_at DESC
          LIMIT '.(int)$limit.' OFFSET '.(int)$offset;
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  return $st->fetchAll();
}

function find_next_queued_analysis(?int $userId = null): ?array {
  queue_recover_stale_analyses($userId);
  $params = [];
  $where = 'a.status="queued"';
  if ($userId !== null) { $where .= ' AND a.user_id=?'; $params[] = $userId; }
  $sql = 'SELECT a.id, a.user_id FROM game_analysis a JOIN games g ON g.id=a.game_id WHERE '.$where.' ORDER BY a.created_at ASC, a.id ASC LIMIT 1';
  $st = db()->prepare($sql);
  $st->execute($params);
  $row = $st->fetch();
  return $row ?: null;
}

function queue_recover_stale_analyses(?int $userId = null): array {
  $cfg = engine_config();
  $staleMinutes = max(5, (int)($cfg['queue_stale_minutes'] ?? 30));
  $maxAttempts = max(1, (int)($cfg['analysis_max_attempts'] ?? 2));
  $params = [];
  $scope = '';
  if ($userId !== null) {
    $scope = ' AND user_id=?';
    $params[] = $userId;
  }

  $failed = db()->prepare('UPDATE game_analysis
                           SET status="error", completed_at=NOW(), updated_at=NOW(),
                               error_message="El proceso de Stockfish quedó interrumpido y agotó sus reintentos.",
                               engine_error_code="stale_worker",
                               engine_error_message="Stale running analysis exceeded the configured job attempts."
                           WHERE status="running" AND attempts>=?
                             AND updated_at<DATE_SUB(NOW(), INTERVAL '.(int)$staleMinutes.' MINUTE)'.$scope);
  $failed->execute(array_merge([$maxAttempts], $params));

  $requeued = db()->prepare('UPDATE game_analysis
                             SET status="queued", current_ply=0, completed_at=NULL, started_at=NULL,
                                 updated_at=NOW(), error_message="Reintentando un análisis interrumpido.",
                                 engine_error_code="stale_worker",
                                 engine_error_message="Stale running analysis was automatically requeued."
                             WHERE status="running" AND attempts<?
                               AND updated_at<DATE_SUB(NOW(), INTERVAL '.(int)$staleMinutes.' MINUTE)'.$scope);
  $requeued->execute(array_merge([$maxAttempts], $params));

  return ['requeued' => $requeued->rowCount(), 'failed' => $failed->rowCount()];
}

function process_next_analysis_job(?int $userId = null): array {
  $next = find_next_queued_analysis($userId);
  if (!$next) return ['ok' => true, 'processed' => false, 'message' => 'No hay análisis pendientes.'];
  return process_analysis_job((int)$next['id'], (int)$next['user_id']);
}

function stockfish_eval_history_with_retry(
  array $history,
  StockfishRunner &$runner,
  int &$evaluationCount,
  int $restartEvery,
  int $maxRetries,
  int &$retryCount,
  ?int $nodeBudget = null
): array {
  if ($restartEvery > 0 && $evaluationCount > 0 && $evaluationCount % $restartEvery === 0) {
    $runner->close();
    $runner = stockfish_runner();
  }

  $attempt = 0;
  while (true) {
    try {
      $evaluation = $nodeBudget !== null
        ? $runner->evalMovesWithNodes($history, $nodeBudget)
        : $runner->evalMoves($history);
      $evaluationCount++;
      return $evaluation;
    } catch (StockfishException $error) {
      if ($attempt >= $maxRetries) throw $error;
      $attempt++;
      $retryCount++;
      $runner->close();
      $runner = stockfish_runner();
    }
  }
}

function analysis_move_row(array $move, int $ply, array $before, array $after): array {
  $metrics = adaptive_analysis_move_metrics($move, $before, $after);
  $row = [
    'ply' => $ply,
    'san' => $move['san'],
    'uci' => $move['uci'],
    'fen_before' => $move['fen_before'],
    'fen_after' => $move['fen_after'],
    'bestmove' => $before['bestmove'],
    'bestmove_after' => $after['bestmove'],
    'score_before' => $before['score'],
    'score_before_type' => $before['score_type'],
    'score_after' => $after['score'],
    'score_after_type' => $after['score_type'],
    'depth_before' => stockfish_evaluation_metric($before, 'depth'),
    'depth_after' => stockfish_evaluation_metric($after, 'depth'),
    'seldepth_before' => stockfish_evaluation_metric($before, 'seldepth'),
    'seldepth_after' => stockfish_evaluation_metric($after, 'seldepth'),
    'nodes_before' => stockfish_evaluation_metric($before, 'nodes'),
    'nodes_after' => stockfish_evaluation_metric($after, 'nodes'),
    'time_before_ms' => stockfish_evaluation_metric($before, 'time_ms'),
    'time_after_ms' => stockfish_evaluation_metric($after, 'time_ms'),
    'nps_before' => stockfish_evaluation_metric($before, 'nps'),
    'nps_after' => stockfish_evaluation_metric($after, 'nps'),
    'hashfull_before' => stockfish_evaluation_metric($before, 'hashfull'),
    'hashfull_after' => stockfish_evaluation_metric($after, 'hashfull'),
    'pv_before_uci' => stockfish_evaluation_pv($before),
    'pv_after_uci' => stockfish_evaluation_pv($after),
    'centipawn_loss' => $metrics['loss'],
    'classification' => 'ok',
  ];
  $assessment = chess_move_assessment($row);
  $row['centipawn_loss'] = $assessment['effective_loss'];
  $row['classification'] = $assessment['storage_classification'];
  return $row;
}

function stockfish_evaluation_metric(array $evaluation, string $key): ?int {
  return array_key_exists($key, $evaluation) && $evaluation[$key] !== null
    ? max(0, (int)$evaluation[$key])
    : null;
}

function stockfish_evaluation_pv(array $evaluation): ?string {
  $pv = is_array($evaluation['pv'] ?? null) ? $evaluation['pv'] : [];
  return $pv ? implode(' ', $pv) : null;
}

function stockfish_failure_details(Throwable $error, ?StockfishRunner $runner = null): array {
  $code = 'pipeline_error';
  $exitCode = $runner?->exitCode();
  $details = trim($error->getMessage());
  if ($error instanceof StockfishException) {
    $code = $error->reason();
    $exitCode = $error->engineExitCode() ?? $exitCode;
    $stderr = trim($error->stderrOutput());
    if ($stderr !== '') $details .= "\n".$stderr;
  } elseif ($runner instanceof StockfishRunner) {
    $stderr = trim($runner->stderrOutput());
    if ($stderr !== '') $details .= "\n".$stderr;
  }
  return [
    'code' => substr($code, 0, 40),
    'exit_code' => $exitCode,
    'message' => substr($details, 0, 4000),
  ];
}

function process_analysis_job(int $analysisId, int $userId): array {
  if (!stockfish_process_lock_acquire()) {
    return [
      'ok' => true,
      'processed' => false,
      'analysis_id' => $analysisId,
      'status' => 'busy',
      'message' => 'Ya hay otro proceso de Stockfish en ejecución.',
    ];
  }

  try {
    return process_analysis_job_with_engine($analysisId, $userId);
  } finally {
    stockfish_process_lock_release();
  }
}

function process_analysis_job_with_engine(int $analysisId, int $userId): array {
  $st = db()->prepare('SELECT a.*, g.pgn, g.white_player, g.black_player, u.username
                       FROM game_analysis a
                       JOIN games g ON g.id=a.game_id
                       JOIN users u ON u.id=a.user_id
                       WHERE a.id=? AND a.user_id=?');
  $st->execute([$analysisId, $userId]);
  $a = $st->fetch();
  if (!$a) return ['ok' => false, 'error' => 'Análisis no encontrado.'];
  if ($a['status'] === 'done') return ['ok' => true, 'processed' => false, 'analysis_id' => $analysisId, 'status' => 'done'];
  if ($a['status'] === 'cancelled') return ['ok' => true, 'processed' => false, 'analysis_id' => $analysisId, 'status' => 'cancelled'];
  if ($a['status'] !== 'queued') return ['ok' => true, 'processed' => false, 'analysis_id' => $analysisId, 'status' => $a['status']];

  $claim = db()->prepare('UPDATE game_analysis
                          SET status="running", attempts=attempts+1, started_at=NOW(), completed_at=NULL,
                              current_ply=0, updated_at=NOW(), error_message=NULL, cancel_requested=0,
                              engine_evaluations=0, engine_deep_evaluations=0, engine_retry_count=0,
                              engine_nodes=0, engine_time_ms=0,
                              engine_exit_code=NULL, engine_error_code=NULL, engine_error_message=NULL
                          WHERE id=? AND user_id=? AND status="queued"');
  $claim->execute([$analysisId, $userId]);
  if ($claim->rowCount() !== 1) {
    return ['ok' => true, 'processed' => false, 'analysis_id' => $analysisId, 'status' => 'running'];
  }

  $runner = null;
  $engineRetryCount = 0;
  $engineEvaluationCount = 0;
  $engineDeepEvaluationCount = 0;
  $engineNodes = 0;
  $engineTimeMs = 0;
  try {
    $moves = pgn_to_uci_positions($a['pgn']);
    $cfg = engine_config();
    $max = (int)($cfg['max_halfmoves'] ?? 90);
    $moves = array_slice($moves, 0, $max);
    $total = count($moves);
    db()->prepare('UPDATE game_analysis SET total_ply=?, current_ply=0, updated_at=NOW() WHERE id=?')->execute([$total, $analysisId]);

    $rows = [];
    $adaptive = adaptive_analysis_enabled($cfg);
    $adaptiveProfile = $adaptive ? adaptive_analysis_profile($cfg) : null;
    $restartEvery = $adaptive
      ? max(0, (int)($cfg['adaptive_restart_after_evaluations'] ?? 0))
      : max(0, (int)($cfg['restart_after_evaluations'] ?? 40));
    $evaluationRetries = max(0, min(3, (int)($cfg['evaluation_retries'] ?? 1)));
    $progressEvery = max(1, (int)($cfg['progress_update_every_plies'] ?? 5));
    $runner = stockfish_runner();
    $identity = $runner->identity();
    $profile = $adaptive
      ? [
          'mode' => 'adaptive_nodes',
          'value' => $adaptiveProfile['baseline_nodes'],
          'secondary_value' => $adaptiveProfile['critical_nodes'],
        ]
      : array_merge($runner->searchProfile(), ['secondary_value' => null]);
    db()->prepare('UPDATE game_analysis
                   SET engine_name=?, engine_version=?, engine_build=?, engine_depth=?,
                       engine_threads=?, engine_hash_mb=?, engine_search_mode=?, engine_search_value=?,
                       engine_secondary_search_value=?, updated_at=NOW()
                   WHERE id=?')->execute([
      $identity['name'],
      $identity['version'],
      $identity['build'],
      (int)($cfg['depth'] ?? 10),
      max(1, (int)($cfg['threads'] ?? 1)),
      max(1, (int)($cfg['hash_mb'] ?? 32)),
      $profile['mode'],
      $profile['value'],
      $profile['secondary_value'],
      $analysisId,
    ]);

    try {
      $cancelStatement = db()->prepare('SELECT cancel_requested FROM game_analysis WHERE id=?');
      $progressStatement = db()->prepare('UPDATE game_analysis
                                          SET current_ply=?, engine_evaluations=?, engine_deep_evaluations=?,
                                              engine_retry_count=?, engine_nodes=?, engine_time_ms=?, updated_at=NOW()
                                          WHERE id=?');
      $cancelRequested = function () use ($cancelStatement, $analysisId): bool {
        $cancelStatement->execute([$analysisId]);
        return (int)$cancelStatement->fetchColumn() === 1;
      };
      $recordProgress = function (int $ply) use (
        $progressStatement,
        $analysisId,
        &$engineEvaluationCount,
        &$engineDeepEvaluationCount,
        &$engineRetryCount,
        &$engineNodes,
        &$engineTimeMs
      ): void {
        $progressStatement->execute([
          $ply,
          $engineEvaluationCount,
          $engineDeepEvaluationCount,
          $engineRetryCount,
          $engineNodes,
          $engineTimeMs,
          $analysisId,
        ]);
      };
      $cancelResult = function () use (
        $analysisId,
        &$runner,
        &$engineEvaluationCount,
        &$engineDeepEvaluationCount,
        &$engineRetryCount,
        &$engineNodes,
        &$engineTimeMs
      ): array {
        db()->prepare('UPDATE game_analysis
                       SET status="cancelled", completed_at=NOW(), updated_at=NOW(),
                           engine_evaluations=?, engine_deep_evaluations=?, engine_retry_count=?,
                           engine_nodes=?, engine_time_ms=?, engine_exit_code=?
                       WHERE id=?')->execute([
          $engineEvaluationCount,
          $engineDeepEvaluationCount,
          $engineRetryCount,
          $engineNodes,
          $engineTimeMs,
          $runner->exitCode(),
          $analysisId,
        ]);
        return ['ok' => true, 'processed' => true, 'analysis_id' => $analysisId, 'status' => 'cancelled'];
      };

      $evaluations = [];
      $history = [];
      $baselineNodes = $adaptive ? $adaptiveProfile['baseline_nodes'] : null;
      for ($positionIndex = 0; $positionIndex <= $total; $positionIndex++) {
        if ($positionIndex > 0) $history[] = (string)$moves[$positionIndex - 1]['uci'];
        $evaluation = stockfish_eval_history_with_retry(
          $history,
          $runner,
          $engineEvaluationCount,
          $restartEvery,
          $evaluationRetries,
          $engineRetryCount,
          $baselineNodes
        );
        $evaluations[$positionIndex] = $evaluation;
        $engineNodes += (int)($evaluation['nodes'] ?? 0);
        $engineTimeMs += (int)($evaluation['time_ms'] ?? 0);

        if ($positionIndex > 0 && ($positionIndex % $progressEvery === 0 || $positionIndex === $total)) {
          if ($cancelRequested()) return $cancelResult();
          $visiblePly = $adaptive && $positionIndex === $total ? max(0, $total - 1) : $positionIndex;
          $recordProgress($visiblePly);
        }
      }

      if ($adaptive) {
        $criticalPositions = adaptive_analysis_critical_positions($moves, $evaluations, $cfg);
        foreach ($criticalPositions as $deepIndex => $positionIndex) {
          $history = [];
          for ($historyIndex = 0; $historyIndex < $positionIndex; $historyIndex++) {
            $history[] = (string)$moves[$historyIndex]['uci'];
          }
          $evaluation = stockfish_eval_history_with_retry(
            $history,
            $runner,
            $engineEvaluationCount,
            $restartEvery,
            $evaluationRetries,
            $engineRetryCount,
            $adaptiveProfile['critical_nodes']
          );
          $evaluations[$positionIndex] = $evaluation;
          $engineDeepEvaluationCount++;
          $engineNodes += (int)($evaluation['nodes'] ?? 0);
          $engineTimeMs += (int)($evaluation['time_ms'] ?? 0);
          if (($deepIndex + 1) % $progressEvery === 0 || $deepIndex + 1 === count($criticalPositions)) {
            if ($cancelRequested()) return $cancelResult();
            $recordProgress(max(0, $total - 1));
          }
        }
      }

      foreach ($moves as $index => $move) {
        $rows[] = analysis_move_row($move, $index + 1, $evaluations[$index], $evaluations[$index + 1]);
      }
    } finally {
      $runner->close();
    }

    db()->prepare('DELETE FROM game_move_analysis WHERE analysis_id=?')->execute([$analysisId]);
    $mi = db()->prepare('INSERT INTO game_move_analysis
      (analysis_id,ply,san,uci,fen_before,fen_after,bestmove,bestmove_after,
       score_before,score_before_type,score_after,score_after_type,
       depth_before,depth_after,seldepth_before,seldepth_after,nodes_before,nodes_after,
       time_before_ms,time_after_ms,nps_before,nps_after,hashfull_before,hashfull_after,
       pv_before_uci,pv_after_uci,centipawn_loss,classification)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $counts = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];
    $playerSide = player_perspective_side($a, (string)($a['username'] ?? ''));
    foreach ($rows as $r) {
      if (player_perspective_is_own_move((int)$r['ply'], $playerSide) && isset($counts[$r['classification']])) {
        $counts[$r['classification']]++;
      }
      $mi->execute([
        $analysisId,$r['ply'],$r['san'],$r['uci'],$r['fen_before'],$r['fen_after'],
        $r['bestmove'],$r['bestmove_after'],$r['score_before'],$r['score_before_type'],
        $r['score_after'],$r['score_after_type'],$r['depth_before'],$r['depth_after'],
        $r['seldepth_before'],$r['seldepth_after'],$r['nodes_before'],$r['nodes_after'],
        $r['time_before_ms'],$r['time_after_ms'],$r['nps_before'],$r['nps_after'],
        $r['hashfull_before'],$r['hashfull_after'],$r['pv_before_uci'],$r['pv_after_uci'],
        $r['centipawn_loss'],$r['classification'],
      ]);
    }

    try {
      smart_tag_generate_for_analysis($analysisId, $userId);
    } catch (Throwable $tagError) {
      // Smart Tags are derived metadata; a tagging failure must not invalidate a completed Stockfish analysis.
    }
    try {
      openings_refresh_analysis_profile($analysisId, $userId);
    } catch (Throwable $openingError) {
      // Opening profiles are derived metadata; generation can be retried from the profile backfill.
    }
    try {
      training_generate_for_analysis($analysisId, $userId);
    } catch (Throwable $trainingError) {
      // Training exercises are derived metadata; generation can be retried from the profile backfill.
    }
    db()->prepare('UPDATE game_analysis
                   SET status="done", completed_at=NOW(), updated_at=NOW(), blunders=?, mistakes=?, inaccuracies=?,
                       current_ply=?, total_ply=?, engine_evaluations=?, engine_retry_count=?,
                       engine_deep_evaluations=?, engine_nodes=?, engine_time_ms=?, engine_exit_code=?, engine_error_code=NULL,
                       engine_error_message=NULL
                   WHERE id=?')->execute([
      $counts['blunder'],
      $counts['mistake'],
      $counts['inaccuracy'],
      $total,
      $total,
      $engineEvaluationCount,
      $engineRetryCount,
      $engineDeepEvaluationCount,
      $engineNodes,
      $engineTimeMs,
      $runner?->exitCode(),
      $analysisId,
    ]);
    try {
      player_progress_recalculate($userId, 'analysis_completed');
    } catch (Throwable $progressError) {
      // El progreso es derivado y no debe invalidar un analisis Stockfish completado.
      error_log('Player progress recalculation failed: ' . $progressError->getMessage());
    }
    try {
      require_once __DIR__ . '/player_dna.php';
      player_dna_refresh_after_analysis($userId, (string)($a['username'] ?? ''), $analysisId);
    } catch (Throwable $dnaError) {
      // El ADN es derivado y puede regenerarse manualmente desde el perfil.
      error_log('Player DNA refresh failed: ' . $dnaError->getMessage());
    }
    return ['ok' => true, 'processed' => true, 'analysis_id' => $analysisId, 'status' => 'done', 'summary' => $counts];
  } catch (Throwable $e) {
    $publicMessage = public_error_message($e);
    if ($runner instanceof StockfishRunner) $runner->close();
    $failure = stockfish_failure_details($e, $runner);
    db()->prepare('UPDATE game_analysis
                   SET status="error", error_message=?, completed_at=NOW(), updated_at=NOW(),
                       engine_evaluations=?, engine_retry_count=?, engine_nodes=?, engine_time_ms=?,
                       engine_deep_evaluations=?, engine_exit_code=?, engine_error_code=?, engine_error_message=?
                   WHERE id=?')->execute([
      $publicMessage,
      $engineEvaluationCount,
      $engineRetryCount,
      $engineNodes,
      $engineTimeMs,
      $engineDeepEvaluationCount,
      $failure['exit_code'],
      $failure['code'],
      $failure['message'],
      $analysisId,
    ]);
    return ['ok' => false, 'processed' => true, 'error' => $publicMessage, 'analysis_id' => $analysisId, 'status' => 'error'];
  }
}

function worker_log_run(array $payload): void {
  try {
    $st = db()->prepare('INSERT INTO analysis_worker_runs (user_id,trigger_source,queue_before,queue_after,processed_count,success_count,error_count,last_analysis_id,last_status,duration_ms,message,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $st->execute([
      $payload['user_id'] ?? null,
      $payload['trigger_source'] ?? 'unknown',
      (int)($payload['queue_before'] ?? 0),
      (int)($payload['queue_after'] ?? 0),
      (int)($payload['processed_count'] ?? 0),
      (int)($payload['success_count'] ?? 0),
      (int)($payload['error_count'] ?? 0),
      $payload['last_analysis_id'] ?? null,
      $payload['last_status'] ?? null,
      (int)($payload['duration_ms'] ?? 0),
      $payload['message'] ?? null,
    ]);
  } catch (Throwable $e) {
    // No bloquear la aplicación si falla el log del worker.
  }
}

function worker_run_batch(?int $userId = null, int $batch = 1, string $trigger = 'manual-page'): array {
  $batch = max(1, $batch);
  $started = microtime(true);
  $before = queue_stats_scope($userId);
  $processed = 0; $success = 0; $errors = 0; $lastAnalysisId = null; $lastStatus = null; $messages = [];

  for ($i = 0; $i < $batch; $i++) {
    $r = process_next_analysis_job($userId);
    if (empty($r['processed'])) {
      $messages[] = $r['message'] ?? 'No hay análisis pendientes.';
      break;
    }
    $processed++;
    $lastAnalysisId = $r['analysis_id'] ?? $lastAnalysisId;
    $lastStatus = $r['status'] ?? $lastStatus;
    if (!empty($r['ok'])) $success++; else $errors++;
    if (!empty($r['error'])) $messages[] = $r['error'];
  }

  $after = queue_stats_scope($userId);
  $durationMs = (int)round((microtime(true) - $started) * 1000);
  $message = trim(implode(' | ', array_filter($messages)));
  if ($message === '') {
    if ($processed === 0) $message = 'No había análisis pendientes.';
    elseif ($errors > 0) $message = 'Se han producido errores durante la ejecución del worker.';
    else $message = 'Worker ejecutado correctamente.';
  }

  worker_log_run([
    'user_id' => $userId,
    'trigger_source' => $trigger,
    'queue_before' => $before['pending_total'] ?? 0,
    'queue_after' => $after['pending_total'] ?? 0,
    'processed_count' => $processed,
    'success_count' => $success,
    'error_count' => $errors,
    'last_analysis_id' => $lastAnalysisId,
    'last_status' => $lastStatus,
    'duration_ms' => $durationMs,
    'message' => $message,
  ]);

  return [
    'ok' => $errors === 0,
    'processed' => $processed > 0,
    'processed_count' => $processed,
    'success_count' => $success,
    'error_count' => $errors,
    'analysis_id' => $lastAnalysisId,
    'status' => $lastStatus,
    'duration_ms' => $durationMs,
    'message' => $message,
    'queue_before' => $before,
    'queue_after' => $after,
  ];
}

function worker_history(int $userId, int $limit = 20): array {
  $limit = max(1, min(100, $limit));
  $sql = 'SELECT wr.*, ga.game_id, g.white_player, g.black_player, g.played_at, g.result_raw
          FROM analysis_worker_runs wr
          LEFT JOIN game_analysis ga ON ga.id = wr.last_analysis_id AND ga.user_id=?
          LEFT JOIN games g ON g.id = ga.game_id
          WHERE wr.user_id IS NULL OR wr.user_id=?
          ORDER BY wr.created_at DESC, wr.id DESC
          LIMIT '.(int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return $st->fetchAll();
}

function worker_summary(int $userId): array {
  $queue = queue_stats($userId);
  $st = db()->prepare('SELECT * FROM analysis_worker_runs WHERE user_id IS NULL OR user_id=? ORDER BY created_at DESC, id DESC LIMIT 1');
  $st->execute([$userId]);
  $last = $st->fetch() ?: null;

  $avgSt = db()->prepare('SELECT SUM(duration_ms) AS total_ms, SUM(processed_count) AS total_games FROM analysis_worker_runs WHERE (user_id IS NULL OR user_id=?) AND success_count > 0 AND processed_count > 0');
  $avgSt->execute([$userId]);
  $avg = $avgSt->fetch() ?: [];
  $avgSeconds = (!empty($avg['total_games'])) ? round(((int)$avg['total_ms']) / (int)$avg['total_games'] / 1000, 1) : null;

  $errSt = db()->prepare('SELECT error_count, processed_count, success_count FROM analysis_worker_runs WHERE user_id IS NULL OR user_id=? ORDER BY created_at DESC, id DESC LIMIT 10');
  $errSt->execute([$userId]);
  $consecutive = 0;
  foreach ($errSt->fetchAll() as $row) {
    if ((int)($row['error_count'] ?? 0) > 0 && (int)($row['success_count'] ?? 0) === 0) $consecutive++;
    else break;
  }

  $cron = cron_config();
  $expectedMinutes = max(1, (int)($cron['expected_interval_minutes'] ?? 5));
  $nextRun = null;
  if (!empty($last['created_at'])) {
    $ts = strtotime($last['created_at']);
    if ($ts) $nextRun = date('Y-m-d H:i:s', $ts + ($expectedMinutes * 60));
  }
  $token = (string)($cron['worker_token'] ?? '');
  $maskedToken = $token ? substr($token, 0, 8) . '…' . substr($token, -6) : '';

  return [
    'queue' => $queue,
    'last_run' => $last,
    'avg_seconds_per_game' => $avgSeconds,
    'consecutive_errors' => $consecutive,
    'cron_protected' => $token !== '',
    'masked_token' => $maskedToken,
    'expected_interval_minutes' => $expectedMinutes,
    'next_run_estimated_at' => $nextRun,
    'worker_path' => 'worker/analyze_queue.php',
  ];
}
