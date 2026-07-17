<?php
require_once __DIR__ . '/training.php';
require_once __DIR__ . '/review_progress.php';

const TRAINING_PLAN_GENERATION_VERSION = 1;

function training_plan_period(string $periodType, ?DateTimeImmutable $now = null): array {
  $today = ($now ?: new DateTimeImmutable('now'))->setTime(0, 0);
  if ($periodType === 'weekly') {
    $start = $today->modify('monday this week');
    return ['start' => $start->format('Y-m-d'), 'end' => $start->modify('+6 days')->format('Y-m-d')];
  }
  return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
}

function training_plan_goal_key(string $periodType, string $periodStart, string $goalType, ?string $contextKey = null): string {
  $context = $contextKey === null || $contextKey === '' ? 'general' : substr($contextKey, 0, 80);
  return implode(':', [$periodType, $periodStart, $goalType, $context]);
}

function training_plan_review_candidate(int $userId): ?array {
  $st = db()->prepare('SELECT g.id,g.white_player,g.black_player,g.played_at
                       FROM games g
                       JOIN game_analysis a ON a.id=(
                         SELECT id FROM game_analysis
                         WHERE game_id=g.id AND user_id=? AND status="done"
                         ORDER BY id DESC LIMIT 1
                       )
                       LEFT JOIN game_review_progress rp ON rp.user_id=g.user_id AND rp.game_id=g.id
                       WHERE g.user_id=? AND rp.completed_at IS NULL
                       ORDER BY COALESCE(g.played_at,DATE(g.imported_at)) DESC,g.id DESC LIMIT 1');
  $st->execute([$userId, $userId]);
  $row = $st->fetch();
  if (!$row) return null;
  return [
    'game_id' => (int)$row['id'],
    'title' => trim((string)$row['white_player'] . ' vs ' . (string)$row['black_player']),
    'played_at' => $row['played_at'],
  ];
}

function training_plan_focus_candidate(int $userId): ?array {
  $st = db()->prepare('SELECT tet.tag_code,d.label,COUNT(DISTINCT te.id) AS exercise_count
                       FROM training_exercise_tags tet
                       JOIN training_exercises te ON te.id=tet.exercise_id AND te.user_id=? AND te.status="active"
                       JOIN smart_tag_definitions d ON d.code=tet.tag_code AND d.is_active=1
                       WHERE (te.resolved_at IS NULL OR te.last_training_result="failed")
                         AND d.category<>"positive"
                       GROUP BY tet.tag_code,d.label
                       ORDER BY exercise_count DESC,FIELD(d.severity,"critical","high","medium","low","info"),d.label
                       LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  return $row ? [
    'tag_code' => (string)$row['tag_code'],
    'label' => (string)$row['label'],
    'exercise_count' => (int)$row['exercise_count'],
  ] : null;
}

function training_plan_opening_candidate(int $userId): ?array {
  $st = db()->prepare('SELECT op.opening_key,MAX(op.display_name) AS display_name,COUNT(DISTINCT op.game_id) AS games,
                              COUNT(DISTINCT CASE WHEN te.resolved_at IS NULL OR te.last_training_result="failed" THEN te.id END) AS pending_exercises
                       FROM game_opening_profiles op
                       LEFT JOIN training_exercises te ON te.user_id=op.user_id AND te.game_id=op.game_id AND te.ply<=16 AND te.status="active"
                       WHERE op.user_id=? AND op.opening_source<>"unknown"
                       GROUP BY op.opening_key
                       HAVING games>=3 AND pending_exercises>0
                       ORDER BY pending_exercises DESC,games DESC,display_name ASC LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  return $row ? [
    'opening_key' => (string)$row['opening_key'],
    'display_name' => (string)$row['display_name'],
    'games' => (int)$row['games'],
  ] : null;
}

function training_plan_definitions(int $userId): array {
  $settings = training_goal_settings_for_user($userId);
  $daily = training_plan_period('daily');
  $weekly = training_plan_period('weekly');
  $dailyProgress = training_plan_period_progress($userId, $daily['start'], $daily['end']);
  $weeklyProgress = training_plan_period_progress($userId, $weekly['start'], $weekly['end']);
  $goals = [];
  $add = static function (
    string $periodType,
    array $period,
    string $goalType,
    string $title,
    string $rationale,
    int $target,
    ?string $contextType = null,
    ?string $contextKey = null
  ) use (&$goals): void {
    $goals[] = [
      'goal_key' => training_plan_goal_key($periodType, $period['start'], $goalType, $contextKey),
      'period_type' => $periodType,
      'period_start' => $period['start'],
      'period_end' => $period['end'],
      'goal_type' => $goalType,
      'context_type' => $contextType,
      'context_key' => $contextKey,
      'title' => $title,
      'rationale' => $rationale,
      'target_value' => max(1, $target),
    ];
  };

  $mode = (string)$settings['daily_goal_mode'];
  if ($mode === 'exercises' || $mode === 'both') {
    $target = (int)$settings['daily_exercise_goal'];
    $add('daily', $daily, 'training_exercises', "Completa {$target} ejercicios", 'Tu objetivo diario configurado.', $target);
  }
  if ($mode === 'minutes' || $mode === 'both') {
    $target = (int)$settings['daily_minutes_goal'];
    $add('daily', $daily, 'training_minutes', "Entrena {$target} minutos", 'Tu objetivo diario configurado.', $target);
  }

  $review = training_plan_review_candidate($userId);
  if ($review && $dailyProgress['reviews'] < 1) {
    $add('daily', $daily, 'review_game', 'Revisa una partida reciente', $review['title'], 1, 'game', (string)$review['game_id']);
  }
  $focus = training_plan_focus_candidate($userId);
  if ($focus) {
    $add('daily', $daily, 'focus_exercises', 'Trabaja ' . $focus['label'], 'Tu patrón pendiente más frecuente.', 2, 'smart_tag', $focus['tag_code']);
  }

  $add('weekly', $weekly, 'training_days', 'Entrena con regularidad', 'Días con al menos un ejercicio completado.', (int)$settings['weekly_training_days_goal']);
  $add('weekly', $weekly, 'training_exercises', 'Completa el trabajo semanal', 'Volumen semanal configurado.', (int)$settings['weekly_exercise_goal']);
  if ($review && $weeklyProgress['reviews'] < 2) {
    $add('weekly', $weekly, 'review_games', 'Revisa tus partidas', 'Convierte análisis recientes en aprendizaje.', 2);
  }

  $opening = training_plan_opening_candidate($userId);
  if ($opening) {
    $add('weekly', $weekly, 'opening_review', 'Refuerza ' . $opening['display_name'], 'Apertura recurrente con ejercicios pendientes.', 1, 'opening', $opening['opening_key']);
  }
  return $goals;
}

function training_plan_upsert_goal(int $userId, array $goal): void {
  $st = db()->prepare('INSERT INTO training_plan_goals
      (user_id,goal_key,period_type,period_start,period_end,goal_type,context_type,context_key,title,rationale,target_value,status,source,generation_version,created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,"pending","rules",?,NOW())
      ON DUPLICATE KEY UPDATE
        period_end=VALUES(period_end),title=VALUES(title),rationale=VALUES(rationale),target_value=VALUES(target_value),
        context_type=VALUES(context_type),context_key=VALUES(context_key),generation_version=VALUES(generation_version),
        status=IF(status="dismissed","pending",status),updated_at=NOW()');
  $st->execute([
    $userId, $goal['goal_key'], $goal['period_type'], $goal['period_start'], $goal['period_end'], $goal['goal_type'],
    $goal['context_type'], $goal['context_key'], $goal['title'], $goal['rationale'], $goal['target_value'], TRAINING_PLAN_GENERATION_VERSION,
  ]);
}

function training_plan_period_progress(int $userId, string $start, string $end): array {
  $st = db()->prepare('SELECT COUNT(DISTINCT sr.id) AS exercises,
                              COUNT(DISTINCT DATE(sr.completed_at)) AS training_days,
                              COALESCE(SUM(run_duration.duration_ms),0) AS duration_ms
                       FROM training_solve_runs sr
                       LEFT JOIN (
                         SELECT solve_run_id,MAX(duration_ms) AS duration_ms
                         FROM training_attempts WHERE user_id=? AND solve_run_id IS NOT NULL GROUP BY solve_run_id
                       ) run_duration ON run_duration.solve_run_id=sr.id
                       WHERE sr.user_id=? AND sr.status IN ("solved","failed")
                         AND DATE(sr.completed_at) BETWEEN ? AND ?');
  $st->execute([$userId, $userId, $start, $end]);
  $row = $st->fetch() ?: [];
  $reviews = db()->prepare('SELECT COUNT(*) FROM game_review_progress WHERE user_id=? AND DATE(completed_at) BETWEEN ? AND ?');
  $reviews->execute([$userId, $start, $end]);
  return [
    'exercises' => (int)($row['exercises'] ?? 0),
    'training_days' => (int)($row['training_days'] ?? 0),
    'minutes' => (int)floor((int)($row['duration_ms'] ?? 0) / 60000),
    'reviews' => (int)$reviews->fetchColumn(),
  ];
}

function training_plan_goal_progress(int $userId, array $goal, array $periodProgress): int {
  $type = (string)$goal['goal_type'];
  if ($type === 'training_exercises') return $periodProgress['exercises'];
  if ($type === 'training_minutes') return $periodProgress['minutes'];
  if ($type === 'training_days') return $periodProgress['training_days'];
  if ($type === 'review_games') return $periodProgress['reviews'];
  if ($type === 'review_game') {
    $st = db()->prepare('SELECT COUNT(*) FROM game_review_progress
                         WHERE user_id=? AND game_id=? AND DATE(completed_at) BETWEEN ? AND ?');
    $st->execute([$userId, (int)$goal['context_key'], $goal['period_start'], $goal['period_end']]);
    return min(1, (int)$st->fetchColumn());
  }
  if ($type === 'focus_exercises') {
    $st = db()->prepare('SELECT COUNT(DISTINCT sr.id)
                         FROM training_solve_runs sr
                         JOIN training_exercise_tags tet ON tet.exercise_id=sr.exercise_id AND tet.tag_code=?
                         WHERE sr.user_id=? AND sr.status IN ("solved","failed")
                           AND DATE(sr.completed_at) BETWEEN ? AND ?');
    $st->execute([$goal['context_key'], $userId, $goal['period_start'], $goal['period_end']]);
    return (int)$st->fetchColumn();
  }
  if ($type === 'opening_review') {
    $st = db()->prepare('SELECT COUNT(DISTINCT rp.game_id)
                         FROM game_review_progress rp
                         JOIN game_opening_profiles op ON op.user_id=rp.user_id AND op.game_id=rp.game_id AND op.opening_key=?
                         WHERE rp.user_id=? AND DATE(rp.completed_at) BETWEEN ? AND ?');
    $st->execute([$goal['context_key'], $userId, $goal['period_start'], $goal['period_end']]);
    return (int)$st->fetchColumn();
  }
  return 0;
}

function training_plan_goal_url(array $goal): ?string {
  return match ((string)$goal['goal_type']) {
    'review_game' => 'review.php?id=' . (int)$goal['context_key'],
    'review_games' => 'games.php',
    'opening_review' => 'openings-lab.php',
    default => 'training.php',
  };
}

function training_plan_refresh(int $userId): array {
  db()->prepare('UPDATE training_plan_goals SET status="expired",updated_at=NOW()
                 WHERE user_id=? AND status="pending" AND period_end<CURDATE()')->execute([$userId]);
  $definitions = training_plan_definitions($userId);
  foreach ($definitions as $goal) training_plan_upsert_goal($userId, $goal);
  $activeKeys = array_column($definitions, 'goal_key');
  if ($activeKeys) {
    $placeholders = implode(',', array_fill(0, count($activeKeys), '?'));
    $dismiss = db()->prepare('UPDATE training_plan_goals SET status="dismissed",updated_at=NOW()
                              WHERE user_id=? AND source="rules" AND period_start<=CURDATE() AND period_end>=CURDATE()
                                AND status="pending" AND goal_key NOT IN (' . $placeholders . ')');
    $dismiss->execute(array_merge([$userId], $activeKeys));
  }

  $st = db()->prepare('SELECT * FROM training_plan_goals
                       WHERE user_id=? AND period_start<=CURDATE() AND period_end>=CURDATE()
                         AND status IN ("pending","completed")
                       ORDER BY FIELD(period_type,"daily","weekly"),id');
  $st->execute([$userId]);
  $goals = $st->fetchAll();
  $periodCache = [];
  foreach ($goals as &$goal) {
    $cacheKey = $goal['period_start'] . ':' . $goal['period_end'];
    if (!isset($periodCache[$cacheKey])) {
      $periodCache[$cacheKey] = training_plan_period_progress($userId, $goal['period_start'], $goal['period_end']);
    }
    $current = training_plan_goal_progress($userId, $goal, $periodCache[$cacheKey]);
    $completed = $current >= (int)$goal['target_value'];
    if ($completed && $goal['status'] !== 'completed') {
      db()->prepare('UPDATE training_plan_goals SET status="completed",completed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=?')
        ->execute([(int)$goal['id'], $userId]);
      $goal['status'] = 'completed';
      $goal['completed_at'] = date('Y-m-d H:i:s');
    } elseif (!$completed && $goal['status'] === 'completed') {
      db()->prepare('UPDATE training_plan_goals SET status="pending",completed_at=NULL,updated_at=NOW() WHERE id=? AND user_id=?')
        ->execute([(int)$goal['id'], $userId]);
      $goal['status'] = 'pending';
      $goal['completed_at'] = null;
    }
    $goal['id'] = (int)$goal['id'];
    $goal['target_value'] = (int)$goal['target_value'];
    $goal['current_value'] = $current;
    $goal['progress_percent'] = min(100, (int)round($current / max(1, (int)$goal['target_value']) * 100));
    $goal['action_url'] = training_plan_goal_url($goal);
  }
  unset($goal);
  return [
    'generation_version' => TRAINING_PLAN_GENERATION_VERSION,
    'daily' => array_values(array_filter($goals, fn(array $goal): bool => $goal['period_type'] === 'daily')),
    'weekly' => array_values(array_filter($goals, fn(array $goal): bool => $goal['period_type'] === 'weekly')),
  ];
}
