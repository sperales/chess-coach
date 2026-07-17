<?php
require_once __DIR__ . '/training_progress.php';

const PLAYER_PROGRESS_CALCULATION_VERSION = 1;
const PLAYER_PROGRESS_EXERCISE_WINDOW = 30;
const PLAYER_PROGRESS_GAME_WINDOW = 10;
const PLAYER_PROGRESS_AUTONOMY_MIN_SAMPLES = 6;

function player_progress_clamp(float $value, float $minimum = 0.0, float $maximum = 100.0): float {
  return max($minimum, min($maximum, $value));
}

function player_progress_accuracy_from_acpl(float $acpl): float {
  if ($acpl <= 0) return 100.0;
  return round(player_progress_clamp(100 * exp(-$acpl / 220)), 2);
}

function player_progress_discipline_score(int $blunders, int $mistakes, int $inaccuracies, int $moveCount): float {
  $weightedErrors = max(0, $blunders) * 3.0 + max(0, $mistakes) * 1.5 + max(0, $inaccuracies) * 0.5;
  $rate = $weightedErrors / max(1, $moveCount);
  return round(player_progress_clamp(100.0 - $rate * 100.0), 2);
}

function player_progress_result_score(string $result): float {
  return match ($result) {
    'win' => 100.0,
    'draw' => 60.0,
    'loss' => 35.0,
    default => 50.0,
  };
}

function player_progress_game_quality(float $accuracy, float $discipline, string $result): float {
  return round(
    player_progress_clamp($accuracy) * 0.65
      + player_progress_clamp($discipline) * 0.25
      + player_progress_result_score($result) * 0.10,
    2
  );
}

function player_progress_weighted_average(array $samples): ?float {
  $weightedTotal = 0.0;
  $weightTotal = 0.0;
  foreach ($samples as $sample) {
    if (!isset($sample['quality']) || $sample['quality'] === null) continue;
    $weight = max(0.0, (float)($sample['weight'] ?? 1.0));
    if ($weight <= 0) continue;
    $weightedTotal += player_progress_clamp((float)$sample['quality']) * $weight;
    $weightTotal += $weight;
  }
  return $weightTotal > 0 ? round($weightedTotal / $weightTotal, 2) : null;
}

function player_progress_combined_score(?float $exerciseComponent, ?float $gameComponent): int {
  if ($exerciseComponent === null && $gameComponent === null) return 0;
  if ($exerciseComponent === null) return (int)round(player_progress_clamp((float)$gameComponent) * 10);
  if ($gameComponent === null) return (int)round(player_progress_clamp($exerciseComponent) * 10);
  return (int)round((player_progress_clamp($exerciseComponent) * 0.60 + player_progress_clamp($gameComponent) * 0.40) * 10);
}

function player_progress_autonomy_sample(string $status, int $attemptsCount, int $highestHintLevel): float {
  if ($status !== 'solved') return 0.0;
  $attemptsCount = max(1, training_progress_attempts_count($attemptsCount));
  $highestHintLevel = training_progress_hint_level($highestHintLevel);
  $attemptScores = [1 => 100.0, 2 => 85.0, 3 => 70.0, 4 => 50.0, 5 => 30.0];
  $hintScores = [0 => 100.0, 1 => 70.0, 2 => 35.0, 3 => 10.0];
  return round($attemptScores[$attemptsCount] * 0.30 + $hintScores[$highestHintLevel] * 0.70, 2);
}

function player_progress_game_rows(int $userId, int $limit = PLAYER_PROGRESS_GAME_WINDOW): array {
  $limit = max(1, min(30, $limit));
  $sql = 'SELECT g.id AS game_id,g.white_player,g.black_player,g.user_result,g.played_at,g.imported_at,
                 a.id AS analysis_id,a.completed_at,u.username
          FROM games g
          JOIN users u ON u.id=g.user_id
          JOIN game_analysis a ON a.id=(
            SELECT id FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC LIMIT 1
          )
          WHERE g.user_id=?
          ORDER BY COALESCE(g.played_at,DATE(g.imported_at)) DESC,g.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return $st->fetchAll();
}

function player_progress_game_side(array $game): ?string {
  $username = strtolower(trim((string)($game['username'] ?? '')));
  if ($username !== '' && $username === strtolower(trim((string)($game['white_player'] ?? '')))) return 'w';
  if ($username !== '' && $username === strtolower(trim((string)($game['black_player'] ?? '')))) return 'b';
  return null;
}

function player_progress_sync_recent_games(int $userId, int $limit = PLAYER_PROGRESS_GAME_WINDOW): array {
  $games = player_progress_game_rows($userId, $limit);
  if (!$games) return ['synced' => 0, 'skipped' => 0];

  $analysisIds = array_map('intval', array_column($games, 'analysis_id'));
  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $movesSt = db()->prepare("SELECT analysis_id,ply,centipawn_loss,classification
                            FROM game_move_analysis
                            WHERE analysis_id IN ($placeholders)
                            ORDER BY analysis_id,ply");
  $movesSt->execute($analysisIds);
  $movesByAnalysis = [];
  foreach ($movesSt->fetchAll() as $move) {
    $movesByAnalysis[(int)$move['analysis_id']][] = $move;
  }

  $synced = 0;
  $skipped = 0;
  foreach ($games as $game) {
    $side = player_progress_game_side($game);
    if ($side === null) {
      $skipped++;
      continue;
    }
    $losses = [];
    $counts = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];
    foreach ($movesByAnalysis[(int)$game['analysis_id']] ?? [] as $move) {
      $moveSide = ((int)$move['ply'] % 2 === 1) ? 'w' : 'b';
      if ($moveSide !== $side) continue;
      $losses[] = min(1000, max(0, (int)$move['centipawn_loss']));
      $classification = (string)$move['classification'];
      if (isset($counts[$classification])) $counts[$classification]++;
    }
    if (!$losses) {
      $skipped++;
      continue;
    }

    $acpl = round(array_sum($losses) / count($losses), 2);
    $accuracy = player_progress_accuracy_from_acpl($acpl);
    $discipline = player_progress_discipline_score(
      $counts['blunder'],
      $counts['mistake'],
      $counts['inaccuracy'],
      count($losses)
    );
    $result = (string)($game['user_result'] ?? 'unknown');
    $quality = player_progress_game_quality($accuracy, $discipline, $result);
    $occurredAt = !empty($game['played_at'])
      ? (string)$game['played_at'] . ' 12:00:00'
      : ((string)($game['completed_at'] ?? $game['imported_at'] ?? ''));
    player_progress_record_event(
      $userId,
      'game_performance:' . (int)$game['game_id'],
      'game_performance',
      'game_analysis',
      (int)$game['analysis_id'],
      $quality,
      1.0,
      [
        'game_id' => (int)$game['game_id'],
        'accuracy' => $accuracy,
        'acpl' => $acpl,
        'discipline' => $discipline,
        'result' => $result,
        'result_score' => player_progress_result_score($result),
        'own_moves' => count($losses),
        'blunders' => $counts['blunder'],
        'mistakes' => $counts['mistake'],
        'inaccuracies' => $counts['inaccuracy'],
      ],
      null,
      PLAYER_PROGRESS_CALCULATION_VERSION,
      $occurredAt
    );
    $synced++;
  }
  return ['synced' => $synced, 'skipped' => $skipped];
}

function player_progress_event_samples(int $userId, string $eventType, int $limit): array {
  $limit = max(1, min(100, $limit));
  $st = db()->prepare('SELECT quality_score,evidence_weight
                       FROM player_progress_events
                       WHERE user_id=? AND event_type=? AND quality_score IS NOT NULL
                       ORDER BY occurred_at DESC,id DESC
                       LIMIT ' . (int)$limit);
  $st->execute([$userId, $eventType]);
  return array_map(static fn(array $row): array => [
    'quality' => (float)$row['quality_score'],
    'weight' => (float)$row['evidence_weight'],
  ], $st->fetchAll());
}

function player_progress_autonomy(int $userId): array {
  $st = db()->prepare('SELECT status,attempts_count,highest_hint_level
                       FROM training_solve_runs
                       WHERE user_id=? AND status IN ("solved","failed")
                       ORDER BY completed_at DESC,id DESC
                       LIMIT ' . PLAYER_PROGRESS_EXERCISE_WINDOW);
  $st->execute([$userId]);
  $rows = $st->fetchAll();
  $samples = array_map(static fn(array $row): array => [
    'quality' => player_progress_autonomy_sample(
      (string)$row['status'],
      (int)$row['attempts_count'],
      (int)$row['highest_hint_level']
    ),
    'weight' => 1.0,
  ], $rows);
  return [
    'score' => player_progress_weighted_average($samples),
    'samples' => count($rows),
    'minimum_samples' => PLAYER_PROGRESS_AUTONOMY_MIN_SAMPLES,
    'calibrated' => count($rows) >= PLAYER_PROGRESS_AUTONOMY_MIN_SAMPLES,
  ];
}

function player_progress_recalculate(int $userId, string $reason = 'manual', bool $syncGames = true): array {
  $sync = $syncGames
    ? player_progress_sync_recent_games($userId, PLAYER_PROGRESS_GAME_WINDOW)
    : ['synced' => 0, 'skipped' => 0];
  $exerciseSamples = player_progress_event_samples($userId, 'exercise_resolution', PLAYER_PROGRESS_EXERCISE_WINDOW);
  $gameSamples = player_progress_event_samples($userId, 'game_performance', PLAYER_PROGRESS_GAME_WINDOW);
  $exerciseComponent = player_progress_weighted_average($exerciseSamples);
  $gameComponent = player_progress_weighted_average($gameSamples);
  $autonomy = player_progress_autonomy($userId);
  $score = player_progress_combined_score($exerciseComponent, $gameComponent);

  $st = db()->prepare('INSERT INTO player_progress_snapshots
      (user_id,progress_score,autonomy_score,exercise_component,game_component,exercise_samples,game_samples,
       calculation_version,reason,created_at)
      VALUES (?,?,?,?,?,?,?,?,?,NOW())');
  $st->execute([
    $userId,
    $score,
    $autonomy['score'],
    $exerciseComponent,
    $gameComponent,
    count($exerciseSamples),
    count($gameSamples),
    PLAYER_PROGRESS_CALCULATION_VERSION,
    substr(trim($reason), 0, 80) ?: 'manual',
  ]);
  $snapshotId = (int)db()->lastInsertId();

  $previousSt = db()->prepare('SELECT progress_score FROM player_progress_snapshots
                               WHERE user_id=? AND id<? ORDER BY id DESC LIMIT 1');
  $previousSt->execute([$userId, $snapshotId]);
  $previous = $previousSt->fetchColumn();
  return [
    'available' => true,
    'score' => $score,
    'previous_score' => $previous === false ? null : (int)$previous,
    'delta' => $previous === false ? null : $score - (int)$previous,
    'exercise_component' => $exerciseComponent,
    'game_component' => $gameComponent,
    'exercise_samples' => count($exerciseSamples),
    'game_samples' => count($gameSamples),
    'autonomy' => $autonomy,
    'weights' => ['exercises' => 60, 'games' => 40],
    'windows' => ['exercises' => PLAYER_PROGRESS_EXERCISE_WINDOW, 'games' => PLAYER_PROGRESS_GAME_WINDOW],
    'game_sync' => $sync,
    'calculation_version' => PLAYER_PROGRESS_CALCULATION_VERSION,
  ];
}

function player_progress_latest(int $userId): array {
  $st = db()->prepare('SELECT progress_score,autonomy_score,exercise_component,game_component,
                              exercise_samples,game_samples,calculation_version,reason,created_at
                       FROM player_progress_snapshots
                       WHERE user_id=? ORDER BY id DESC LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  if (!$row) {
    return [
      'available' => false,
      'score' => null,
      'previous_score' => null,
      'delta' => null,
      'exercise_component' => null,
      'game_component' => null,
      'exercise_samples' => 0,
      'game_samples' => 0,
      'autonomy' => player_progress_autonomy($userId),
      'weights' => ['exercises' => 60, 'games' => 40],
      'windows' => ['exercises' => PLAYER_PROGRESS_EXERCISE_WINDOW, 'games' => PLAYER_PROGRESS_GAME_WINDOW],
      'calculation_version' => PLAYER_PROGRESS_CALCULATION_VERSION,
      'reason' => null,
      'calculated_at' => null,
    ];
  }

  return [
    'available' => true,
    'score' => (int)$row['progress_score'],
    'previous_score' => null,
    'delta' => null,
    'exercise_component' => $row['exercise_component'] === null ? null : (float)$row['exercise_component'],
    'game_component' => $row['game_component'] === null ? null : (float)$row['game_component'],
    'exercise_samples' => (int)$row['exercise_samples'],
    'game_samples' => (int)$row['game_samples'],
    'autonomy' => player_progress_autonomy($userId),
    'weights' => ['exercises' => 60, 'games' => 40],
    'windows' => ['exercises' => PLAYER_PROGRESS_EXERCISE_WINDOW, 'games' => PLAYER_PROGRESS_GAME_WINDOW],
    'calculation_version' => (int)$row['calculation_version'],
    'reason' => $row['reason'],
    'calculated_at' => $row['created_at'],
  ];
}
