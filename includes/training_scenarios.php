<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/chess_evaluation.php';

const TRAINING_SCENARIO_GENERATOR_VERSION = 1;
const TRAINING_SCENARIO_MIN_PLAYER_MOVES = 2;
const TRAINING_SCENARIO_MAX_PLAYER_MOVES = 6;

function training_scenario_types(): array {
  return [
    'conversion' => ['label' => 'Convierte la ventaja', 'prompt' => 'Mantén la ventaja ante la mejor defensa del rival.'],
    'defense' => ['label' => 'Defiende la posición', 'prompt' => 'Encuentra recursos precisos y mantén la posición jugable.'],
    'mate' => ['label' => 'Encuentra el mate', 'prompt' => 'Construye la secuencia de mate ante la mejor defensa.'],
  ];
}

function training_scenario_score_cp(?int $score, ?string $type): ?int {
  if ($score === null || !in_array($type, ['cp', 'mate'], true)) return null;
  if ($type === 'mate') return $score > 0 ? 100000 - min(99, abs($score)) * 1000 : -100000 + min(99, abs($score)) * 1000;
  return $score;
}

function training_scenario_profile(string $difficulty): array {
  return match ($difficulty) {
    'easy' => ['target_player_moves' => 2, 'max_player_moves' => 3, 'acceptance_loss_cp' => 60],
    'hard' => ['target_player_moves' => 3, 'max_player_moves' => 5, 'acceptance_loss_cp' => 90],
    'critical' => ['target_player_moves' => 3, 'max_player_moves' => 6, 'acceptance_loss_cp' => 100],
    default => ['target_player_moves' => 2, 'max_player_moves' => 4, 'acceptance_loss_cp' => 75],
  };
}

function training_scenario_candidate_type(array $move, array $moveTags, array $gameTags): ?string {
  $moveCodes = training_tag_codes($moveTags);
  $gameCodes = training_tag_codes($gameTags);
  $before = training_scenario_score_cp(
    isset($move['score_before']) ? (int)$move['score_before'] : null,
    $move['score_before_type'] ?? null
  );
  $loss = max(0, (int)($move['centipawn_loss'] ?? 0));
  if ($before === null || !training_valid_solution($move['bestmove'] ?? null)) return null;
  if (($move['score_before_type'] ?? '') === 'mate' && (int)$move['score_before'] > 0) {
    $mateDistance = abs((int)$move['score_before']);
    return $mateDistance >= TRAINING_SCENARIO_MIN_PLAYER_MOVES && $mateDistance <= TRAINING_SCENARIO_MAX_PLAYER_MOVES ? 'mate' : null;
  }
  if (in_array('missed_mate', $moveCodes, true) && ($move['score_before_type'] ?? '') === 'mate') return 'mate';
  if ($before >= 200 && ($loss >= CHESS_CPL_INACCURACY || in_array('lost_winning_position', $gameCodes, true))) return 'conversion';
  if ($before <= 50 && $before >= -600 && ($loss >= CHESS_CPL_INACCURACY || array_intersect($moveCodes, ['allowed_mate', 'blunder_own', 'mistake_own']))) return 'defense';
  return null;
}

function training_scenario_difficulty(array $move, string $type): string {
  if ($type === 'mate') {
    $distance = abs((int)($move['score_before'] ?? 0));
    if ($distance > 0 && $distance <= 3) return 'easy';
    if ($distance <= 6) return 'medium';
    return 'hard';
  }
  $loss = max(0, (int)($move['centipawn_loss'] ?? 0));
  if ($loss >= CHESS_CPL_BLUNDER) return 'critical';
  if ($loss >= CHESS_CPL_MISTAKE) return 'hard';
  if ($loss >= CHESS_CPL_INACCURACY) return 'medium';
  return 'easy';
}

function training_scenario_candidate_priority(array $move, string $type): int {
  $base = ['mate' => 300, 'conversion' => 200, 'defense' => 180][$type] ?? 0;
  return $base + min(500, max(0, (int)($move['centipawn_loss'] ?? 0)));
}

function training_scenario_candidates(array $game, array $moves, array $tags): array {
  $userSide = training_user_side($game, (string)($game['username'] ?? ''));
  if ($userSide === null) return [];
  $bestByType = [];
  foreach ($moves as $move) {
    if (training_move_side((int)($move['ply'] ?? 0)) !== $userSide) continue;
    $moveTags = $tags['move_tags'][(int)($move['id'] ?? 0)] ?? [];
    $gameTags = training_relevant_game_tags($tags['game_tags'] ?? [], (int)($move['ply'] ?? 0));
    $type = training_scenario_candidate_type($move, $moveTags, $gameTags);
    if ($type === null) continue;
    $priority = training_scenario_candidate_priority($move, $type);
    if (!isset($bestByType[$type]) || $priority > $bestByType[$type]['priority']) {
      $bestByType[$type] = ['type' => $type, 'priority' => $priority, 'move' => $move, 'move_tags' => $moveTags, 'game_tags' => $gameTags];
    }
  }
  usort($bestByType, fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
  return array_values($bestByType);
}

function training_insert_scenario(int $userId, array $game, array $candidate, ?string $focusCode = null): array {
  $move = $candidate['move'];
  $type = (string)$candidate['type'];
  $difficulty = training_scenario_difficulty($move, $type);
  $profile = training_scenario_profile($difficulty);
  $types = training_scenario_types();
  $objective = [
    'type' => $type,
    'minimum_player_moves' => $profile['target_player_moves'],
    'minimum_eval_cp' => $type === 'conversion' ? 150 : ($type === 'defense' ? -100 : null),
    'mate_required' => $type === 'mate',
  ];
  $completion = ['opponent_policy' => 'stockfish_bestmove', 'accept_multiple_moves' => true];
  $existing = db()->prepare('SELECT id FROM training_scenarios WHERE user_id=? AND analysis_id=? AND starting_ply=? AND scenario_type=? LIMIT 1');
  $existing->execute([$userId, (int)$game['analysis_id'], (int)$move['ply'], $type]);
  $existingId = (int)($existing->fetchColumn() ?: 0);
  $sql = 'INSERT INTO training_scenarios
            (user_id,game_id,analysis_id,move_analysis_id,starting_ply,starting_fen,player_color,scenario_type,difficulty,title,prompt,
             source_type,source_focus_code,selection_reason,initial_score,initial_score_type,initial_eval_cp,target_player_moves,
             max_player_moves,acceptance_loss_cp,objective_json,completion_json,generator_version,status,created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,"real_game",?,?,?,?,?,?,?,?,?,?,?,"active",NOW())
          ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),difficulty=VALUES(difficulty),title=VALUES(title),prompt=VALUES(prompt),
            source_focus_code=VALUES(source_focus_code),selection_reason=VALUES(selection_reason),initial_score=VALUES(initial_score),
            initial_score_type=VALUES(initial_score_type),initial_eval_cp=VALUES(initial_eval_cp),target_player_moves=VALUES(target_player_moves),
            max_player_moves=VALUES(max_player_moves),acceptance_loss_cp=VALUES(acceptance_loss_cp),objective_json=VALUES(objective_json),
            completion_json=VALUES(completion_json),generator_version=VALUES(generator_version),status="active"';
  db()->prepare($sql)->execute([
    $userId, (int)$game['game_id'], (int)$game['analysis_id'], (int)$move['id'], (int)$move['ply'], (string)$move['fen_before'],
    training_move_side((int)$move['ply']), $type, $difficulty, $types[$type]['label'], $types[$type]['prompt'], $focusCode,
    'Posición real seleccionada por su impacto y valor de entrenamiento.',
    isset($move['score_before']) ? (int)$move['score_before'] : null, $move['score_before_type'] ?? null,
    training_scenario_score_cp(isset($move['score_before']) ? (int)$move['score_before'] : null, $move['score_before_type'] ?? null),
    $profile['target_player_moves'], $profile['max_player_moves'], $profile['acceptance_loss_cp'],
    json_encode($objective, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    json_encode($completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), TRAINING_SCENARIO_GENERATOR_VERSION,
  ]);
  return ['id' => (int)db()->lastInsertId(), 'created' => $existingId === 0, 'type' => $type];
}

function training_generate_scenarios_for_analysis(int $analysisId, int $userId, ?string $focusCode = null): array {
  $game = training_analysis_game($analysisId, $userId);
  if (!$game) return ['ok' => false, 'error' => 'Análisis no encontrado o no completado.'];
  $st = db()->prepare('SELECT * FROM game_move_analysis WHERE analysis_id=? ORDER BY ply');
  $st->execute([$analysisId]);
  $moves = $st->fetchAll();
  $candidates = training_scenario_candidates($game, $moves, training_tags_for_analysis($analysisId, $userId));
  $created = 0;
  foreach ($candidates as $candidate) {
    $result = training_insert_scenario($userId, $game, $candidate, $focusCode);
    if (!empty($result['created'])) $created++;
  }
  db()->prepare('INSERT INTO training_scenario_generations (analysis_id,user_id,generator_version,candidate_count,scenario_count,processed_at)
                 VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE candidate_count=VALUES(candidate_count),scenario_count=VALUES(scenario_count),processed_at=NOW()')
    ->execute([$analysisId, $userId, TRAINING_SCENARIO_GENERATOR_VERSION, count($candidates), $created]);
  return ['ok' => true, 'processed_candidates' => count($candidates), 'created_scenarios' => $created];
}

function training_scenario_backfill_pending_count(int $userId): int {
  $sql = 'SELECT COUNT(*) FROM game_analysis a
          WHERE a.user_id=? AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            AND NOT EXISTS (SELECT 1 FROM training_scenario_generations sg WHERE sg.analysis_id=a.id AND sg.user_id=? AND sg.generator_version=' . TRAINING_SCENARIO_GENERATOR_VERSION . ')';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  return (int)$st->fetchColumn();
}

function training_scenario_backfill_batch(int $userId, int $limit = 10): array {
  $limit = max(1, min(50, $limit));
  $sql = 'SELECT a.id FROM game_analysis a WHERE a.user_id=? AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            AND NOT EXISTS (SELECT 1 FROM training_scenario_generations sg WHERE sg.analysis_id=a.id AND sg.user_id=? AND sg.generator_version=' . TRAINING_SCENARIO_GENERATOR_VERSION . ')
          ORDER BY COALESCE(a.completed_at,a.updated_at,a.created_at) DESC,a.id DESC LIMIT ' . $limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId]);
  $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
  $created = 0;
  $errors = [];
  foreach ($ids as $analysisId) {
    try {
      $result = training_generate_scenarios_for_analysis($analysisId, $userId);
      $created += (int)($result['created_scenarios'] ?? 0);
    } catch (Throwable $e) {
      $errors[] = public_error_message($e);
    }
  }
  return [
    'ok' => !$errors,
    'processed_analyses' => count($ids),
    'created_scenarios' => $created,
    'pending' => training_scenario_backfill_pending_count($userId),
    'errors' => $errors,
    'message' => $errors ? 'Backfill de escenarios completado con errores parciales.' : 'Escenarios generados correctamente.',
  ];
}
