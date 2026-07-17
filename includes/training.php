<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/stockfish.php';
require_once __DIR__ . '/chess_notation.php';

const TRAINING_EXERCISE_CONTENT_VERSION = 2;

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
      'label' => 'Encuentra el recurso',
      'description' => 'Busca una alternativa concreta que mejora la jugada de la partida.',
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

function training_tag_details(array $tag): array {
  $details = $tag['details_json'] ?? null;
  if (is_array($details)) return $details;
  if (!is_string($details) || trim($details) === '') return [];
  $decoded = json_decode($details, true);
  return is_array($decoded) ? $decoded : [];
}

function training_relevant_game_tags(array $gameTags, int $ply): array {
  return array_values(array_filter($gameTags, function (array $tag) use ($ply): bool {
    $details = training_tag_details($tag);
    $plies = array_map('intval', is_array($details['plies'] ?? null) ? $details['plies'] : []);
    if ($plies) return in_array($ply, $plies, true);
    return (int)($tag['primary_ply'] ?? 0) === $ply;
  }));
}

function training_exercise_type_for_candidate(array $move, array $moveTags, array $gameTags, string $sourceSide): string {
  if ($sourceSide === 'opponent') return 'other';

  $moveCodes = training_tag_codes($moveTags);
  $gameCodes = training_tag_codes($gameTags);
  $classification = (string)($move['classification'] ?? 'ok');
  $loss = (int)($move['centipawn_loss'] ?? 0);
  $beforeType = (string)($move['score_before_type'] ?? 'cp');
  $beforeScore = (int)($move['score_before'] ?? 0);

  if (in_array('missed_mate', $moveCodes, true)) return 'find_mate';
  if (in_array('allowed_mate', $moveCodes, true)) return 'spot_threat';
  if ($beforeType === 'mate' && $beforeScore > 0) return 'find_mate';
  if ($beforeType === 'mate' && $beforeScore < 0) return 'spot_threat';
  if (in_array('lost_winning_position', $gameCodes, true)) return 'convert_advantage';
  if (in_array('endgame_mistake', $gameCodes, true)) return 'defend_position';
  if ($classification === 'blunder' || $loss >= 300) return 'avoid_blunder';
  if ($classification === 'mistake' || in_array('mistake_own', $moveCodes, true)) return 'find_tactic';

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
    'avoid_blunder' => "La jugada de la partida perdió mucha evaluación. Encuentra una alternativa más segura. Juegan {$side}.",
    'find_mate' => "Hay una secuencia forzada de mate. Encuentra el primer movimiento. Juegan {$side}.",
    'spot_threat' => "Evita permitir una amenaza decisiva y encuentra la defensa más precisa. Juegan {$side}.",
    'find_tactic' => "Hay una alternativa concreta que mejora la jugada de la partida. Encuéntrala. Juegan {$side}.",
    'defend_position' => "Encuentra el recurso más preciso para sostener este tramo final. Juegan {$side}.",
    'convert_advantage' => "La posición ofrece una ventaja clara. Encuentra cómo conservarla. Juegan {$side}.",
    'other' => "Aprende también de las decisiones del rival: encuentra la mejor jugada disponible. Juegan {$side}.",
    default => "Compara tu decisión con la mejor alternativa de Stockfish. Juegan {$side}.",
  };
}

function training_title_for_type(string $type): string {
  return match ($type) {
    'avoid_blunder' => 'Evita el error decisivo',
    'find_mate' => 'Remata la posición',
    'spot_threat' => 'Neutraliza la amenaza',
    'find_tactic' => 'Encuentra el recurso',
    'defend_position' => 'Defiende el final',
    'convert_advantage' => 'Conserva la ventaja',
    'other' => 'Aprende de la jugada rival',
    default => 'Encuentra la mejor jugada',
  };
}

function training_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
}

function training_feedback_success(string $type): string {
  return match ($type) {
    'avoid_blunder' => 'Correcto. Has encontrado una alternativa que evita la caída de evaluación.',
    'find_mate' => 'Correcto. Has encontrado el primer movimiento de la secuencia de mate.',
    'spot_threat' => 'Correcto. Has encontrado el recurso que evita la amenaza decisiva.',
    'find_tactic' => 'Correcto. Has encontrado la alternativa más precisa señalada por el análisis.',
    'defend_position' => 'Correcto. Has encontrado el recurso que sostiene el final.',
    'convert_advantage' => 'Correcto. Esa jugada conserva la ventaja disponible.',
    'other' => 'Correcto. Has encontrado la mejor decisión disponible para el rival.',
    default => 'Correcto. Coincide con la mejor jugada almacenada por Stockfish.',
  };
}

function training_feedback_failure(string $type): string {
  return match ($type) {
    'avoid_blunder' => 'Todavía no. Busca una alternativa que mantenga estable la evaluación.',
    'find_mate' => 'Todavía no. Busca el primer movimiento de una secuencia forzada.',
    'spot_threat' => 'Todavía no. Comprueba qué respuestas evitan una amenaza decisiva.',
    'find_tactic' => 'Todavía no. Compara las alternativas antes de repetir la decisión de la partida.',
    'defend_position' => 'Todavía no. El final necesita una defensa precisa.',
    'convert_advantage' => 'Todavía no. Busca una continuación que conserve la ventaja.',
    'other' => 'Todavía no. Busca la decisión más precisa disponible en la posición.',
    default => 'Todavía no. Intenta encontrar la mejor jugada de la posición.',
  };
}

function training_attempt_feedback(array $exercise, bool $isSolved, bool $isExhausted, int $attemptsCount, bool $usedHint): string {
  $type = (string)($exercise['exercise_type'] ?? 'find_best_move');
  if ($isSolved) {
    $base = $exercise['feedback_success'] ?? training_feedback_success($type);
    $interval = training_repetition_interval_days('solved', $attemptsCount, $usedHint);
    if ($interval >= 30) {
      return $base . ' Cuenta para tu objetivo de hoy. Si hace falta, lo repetiremos más adelante.';
    }
    return $base . ' Cuenta para tu objetivo de hoy. Lo programo para repasarlo dentro de unas semanas.';
  }

  if ($isExhausted) {
    return 'Has agotado los intentos. Este ejercicio queda marcado para repetirlo pronto y reforzar la idea.';
  }

  return $exercise['feedback_failure'] ?? training_feedback_failure($type);
}

function training_stats_for_user(int $userId): array {
  $st = db()->prepare('SELECT
      COUNT(*) AS total,
      SUM(status="active" AND (resolved_at IS NULL OR (next_due_at IS NOT NULL AND next_due_at <= NOW()))) AS pending,
      SUM(resolved_at IS NOT NULL) AS resolved,
      SUM(status="active" AND resolved_at IS NOT NULL AND next_due_at IS NOT NULL AND next_due_at <= NOW()) AS due_repeats,
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
    'due_repeats' => (int)($row['due_repeats'] ?? 0),
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

function training_goal_defaults(): array {
  return [
    'daily_goal_mode' => 'exercises',
    'daily_exercise_goal' => 5,
    'daily_minutes_goal' => 10,
    'weekly_training_days_goal' => 4,
    'weekly_exercise_goal' => 25,
    'show_legal_moves' => 1,
    'auto_submit_move' => 0,
  ];
}

function training_goal_settings_for_user(int $userId): array {
  $st = db()->prepare('SELECT daily_goal_mode,daily_exercise_goal,daily_minutes_goal,weekly_training_days_goal,weekly_exercise_goal,
                              show_legal_moves,auto_submit_move
                       FROM training_goal_settings
                       WHERE user_id=?
                       LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  $settings = array_merge(training_goal_defaults(), $row ?: []);
  $settings['daily_goal_mode'] = in_array((string)$settings['daily_goal_mode'], ['exercises', 'minutes', 'both'], true)
    ? (string)$settings['daily_goal_mode']
    : 'exercises';
  $settings['daily_exercise_goal'] = max(1, min(100, (int)$settings['daily_exercise_goal']));
  $settings['daily_minutes_goal'] = max(1, min(240, (int)$settings['daily_minutes_goal']));
  $settings['weekly_training_days_goal'] = max(1, min(7, (int)$settings['weekly_training_days_goal']));
  $settings['weekly_exercise_goal'] = max(1, min(500, (int)$settings['weekly_exercise_goal']));
  $settings['show_legal_moves'] = !empty($settings['show_legal_moves']) ? 1 : 0;
  $settings['auto_submit_move'] = !empty($settings['auto_submit_move']) ? 1 : 0;
  $settings['is_configured'] = (bool)$row;
  return $settings;
}

function training_save_goal_settings(int $userId, array $input): array {
  $defaults = training_goal_defaults();
  $mode = (string)($input['daily_goal_mode'] ?? $defaults['daily_goal_mode']);
  if (!in_array($mode, ['exercises', 'minutes', 'both'], true)) $mode = $defaults['daily_goal_mode'];

  $settings = [
    'daily_goal_mode' => $mode,
    'daily_exercise_goal' => max(1, min(100, (int)($input['daily_exercise_goal'] ?? $defaults['daily_exercise_goal']))),
    'daily_minutes_goal' => max(1, min(240, (int)($input['daily_minutes_goal'] ?? $defaults['daily_minutes_goal']))),
    'weekly_training_days_goal' => max(1, min(7, (int)($input['weekly_training_days_goal'] ?? $defaults['weekly_training_days_goal']))),
    'weekly_exercise_goal' => max(1, min(500, (int)($input['weekly_exercise_goal'] ?? $defaults['weekly_exercise_goal']))),
  ];

  $st = db()->prepare('INSERT INTO training_goal_settings
      (user_id,daily_goal_mode,daily_exercise_goal,daily_minutes_goal,weekly_training_days_goal,weekly_exercise_goal,created_at)
      VALUES (?,?,?,?,?,?,NOW())
      ON DUPLICATE KEY UPDATE
        daily_goal_mode=VALUES(daily_goal_mode),
        daily_exercise_goal=VALUES(daily_exercise_goal),
        daily_minutes_goal=VALUES(daily_minutes_goal),
        weekly_training_days_goal=VALUES(weekly_training_days_goal),
        weekly_exercise_goal=VALUES(weekly_exercise_goal),
        updated_at=NOW()');
  $st->execute([
    $userId,
    $settings['daily_goal_mode'],
    $settings['daily_exercise_goal'],
    $settings['daily_minutes_goal'],
    $settings['weekly_training_days_goal'],
    $settings['weekly_exercise_goal'],
  ]);

  return training_goal_settings_for_user($userId);
}

function training_save_preferences(int $userId, array $input): array {
  $showLegalMoves = !empty($input['show_legal_moves']) ? 1 : 0;
  $autoSubmitMove = !empty($input['auto_submit_move']) ? 1 : 0;
  $st = db()->prepare('INSERT INTO training_goal_settings
      (user_id,show_legal_moves,auto_submit_move,created_at)
      VALUES (?,?,?,NOW())
      ON DUPLICATE KEY UPDATE
        show_legal_moves=VALUES(show_legal_moves),
        auto_submit_move=VALUES(auto_submit_move),
        updated_at=NOW()');
  $st->execute([$userId, $showLegalMoves, $autoSubmitMove]);
  return training_goal_settings_for_user($userId);
}

function training_goal_completed(array $settings, int $exerciseCount, int $durationMs): bool {
  $minutes = (int)floor($durationMs / 60000);
  $exerciseDone = $exerciseCount >= (int)$settings['daily_exercise_goal'];
  $minutesDone = $minutes >= (int)$settings['daily_minutes_goal'];
  return match ((string)$settings['daily_goal_mode']) {
    'minutes' => $minutesDone,
    'both' => $exerciseDone && $minutesDone,
    default => $exerciseDone,
  };
}

function training_activity_rows(int $userId, int $days = 60): array {
  $days = max(1, min(365, $days));
  $sql = 'SELECT DATE(created_at) AS activity_date,
                 COUNT(DISTINCT exercise_id) AS exercises,
                 SUM(result="solved") AS solved,
                 SUM(result="failed") AS failed,
                 SUM(duration_ms) AS duration_ms
          FROM training_attempts
          WHERE user_id=?
            AND result<>"skipped"
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . (int)($days - 1) . ' DAY)
          GROUP BY DATE(created_at)
          ORDER BY activity_date DESC';
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  $rows = [];
  foreach ($st->fetchAll() as $row) {
    $date = (string)$row['activity_date'];
    $rows[$date] = [
      'date' => $date,
      'exercises' => (int)($row['exercises'] ?? 0),
      'solved' => (int)($row['solved'] ?? 0),
      'failed' => (int)($row['failed'] ?? 0),
      'duration_ms' => (int)($row['duration_ms'] ?? 0),
    ];
  }
  return $rows;
}

function training_today_progress(int $userId, array $settings): array {
  $st = db()->prepare('SELECT COUNT(DISTINCT exercise_id) AS exercises,
                              SUM(result="solved") AS solved,
                              SUM(result="failed") AS failed,
                              SUM(duration_ms) AS duration_ms
                       FROM training_attempts
                       WHERE user_id=? AND result<>"skipped" AND DATE(created_at)=CURDATE()');
  $st->execute([$userId]);
  $row = $st->fetch() ?: [];
  $exercises = (int)($row['exercises'] ?? 0);
  $durationMs = (int)($row['duration_ms'] ?? 0);
  return [
    'date' => date('Y-m-d'),
    'trained' => $exercises > 0,
    'goal_met' => training_goal_completed($settings, $exercises, $durationMs),
    'exercises' => $exercises,
    'solved' => (int)($row['solved'] ?? 0),
    'failed' => (int)($row['failed'] ?? 0),
    'duration_ms' => $durationMs,
    'duration_minutes' => (int)floor($durationMs / 60000),
  ];
}

function training_week_progress(int $userId, array $settings): array {
  $st = db()->prepare('SELECT COUNT(DISTINCT DATE(created_at)) AS training_days,
                              COUNT(DISTINCT exercise_id) AS exercises,
                              SUM(result="solved") AS solved,
                              SUM(result="failed") AS failed,
                              SUM(duration_ms) AS duration_ms
                       FROM training_attempts
                       WHERE user_id=?
                         AND result<>"skipped"
                         AND YEARWEEK(created_at, 1)=YEARWEEK(CURDATE(), 1)');
  $st->execute([$userId]);
  $row = $st->fetch() ?: [];
  $trainingDays = (int)($row['training_days'] ?? 0);
  $exercises = (int)($row['exercises'] ?? 0);
  return [
    'training_days' => $trainingDays,
    'training_days_goal' => (int)$settings['weekly_training_days_goal'],
    'training_days_goal_met' => $trainingDays >= (int)$settings['weekly_training_days_goal'],
    'exercises' => $exercises,
    'exercise_goal' => (int)$settings['weekly_exercise_goal'],
    'exercise_goal_met' => $exercises >= (int)$settings['weekly_exercise_goal'],
    'solved' => (int)($row['solved'] ?? 0),
    'failed' => (int)($row['failed'] ?? 0),
    'duration_ms' => (int)($row['duration_ms'] ?? 0),
  ];
}

function training_goal_streak(int $userId, array $settings): array {
  $rows = training_activity_rows($userId, 120);
  $today = new DateTimeImmutable('today');
  $todayKey = $today->format('Y-m-d');
  $todayMet = isset($rows[$todayKey]) && training_goal_completed($settings, $rows[$todayKey]['exercises'], $rows[$todayKey]['duration_ms']);
  $cursor = $todayMet ? $today : $today->modify('-1 day');
  $days = 0;

  while (true) {
    $key = $cursor->format('Y-m-d');
    if (!isset($rows[$key]) || !training_goal_completed($settings, $rows[$key]['exercises'], $rows[$key]['duration_ms'])) {
      break;
    }
    $days++;
    $cursor = $cursor->modify('-1 day');
  }

  return [
    'days' => $days,
    'today_goal_met' => $todayMet,
    'continues_if_completed_today' => !$todayMet && $days > 0,
  ];
}

function training_repetition_interval_days(string $result, int $attemptsCount = 0, bool $usedHint = false): int {
  if ($result === 'failed') return 1;
  if ($result === 'skipped') return 3;
  if ($usedHint || $attemptsCount > 1) return 14;
  return 45;
}

function training_repetition_due_label(?string $lastResult, ?string $lastCompletedAt): ?string {
  if (!$lastResult || !$lastCompletedAt) return null;
  $date = substr((string)$lastCompletedAt, 0, 10);
  return match ($lastResult) {
    'failed' => "Fallado el {$date}",
    'skipped' => "Saltado el {$date}",
    'solved' => "Resuelto el {$date}",
    default => null,
  };
}

function training_due_repetition_count(int $userId): int {
  $st = db()->prepare('SELECT COUNT(*)
                       FROM training_exercises
                       WHERE user_id=? AND status="active" AND resolved_at IS NOT NULL
                         AND next_due_at IS NOT NULL AND next_due_at <= NOW()');
  $st->execute([$userId]);
  return (int)$st->fetchColumn();
}

function training_due_repetition_sample(int $userId, int $limit = 2): array {
  $limit = max(1, min(10, $limit));
  $sql = 'SELECT id, exercise_type, title, difficulty, priority_score, next_due_at, repeat_count, last_training_result, last_completed_at
          FROM training_exercises
          WHERE user_id=? AND status="active" AND resolved_at IS NOT NULL
            AND next_due_at IS NOT NULL AND next_due_at <= NOW()
          ORDER BY FIELD(last_training_result,"failed","skipped","solved"), next_due_at ASC, priority_score DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  $items = [];
  foreach ($st->fetchAll() as $row) {
    $items[] = [
      'id' => (int)$row['id'],
      'exercise_type' => (string)$row['exercise_type'],
      'type_label' => trim((string)($row['title'] ?? '')) ?: training_exercise_type_label((string)$row['exercise_type']),
      'difficulty' => (string)$row['difficulty'],
      'priority_score' => (int)$row['priority_score'],
      'next_due_at' => $row['next_due_at'],
      'repeat_count' => (int)$row['repeat_count'],
      'last_training_result' => $row['last_training_result'],
      'reason_label' => training_repetition_due_label($row['last_training_result'] ?? null, $row['last_completed_at'] ?? null),
    ];
  }
  return $items;
}

function training_experience_milestones(int $userId, array $today, array $week, array $streak, int $dueCount): array {
  $activityRows = training_activity_rows($userId, 365);
  $activityDays = count($activityRows);
  $streakDays = (int)($streak['days'] ?? 0);
  $items = [
    [
      'code' => 'first_training_day',
      'label' => 'Primer día entrenado',
      'description' => 'Ya has empezado a construir el hábito.',
      'achieved' => $activityDays > 0,
    ],
    [
      'code' => 'today_goal',
      'label' => 'Objetivo diario',
      'description' => 'Completa tu objetivo de hoy para mantener la racha.',
      'achieved' => !empty($today['goal_met']),
    ],
    [
      'code' => 'weekly_goal',
      'label' => 'Semana en marcha',
      'description' => 'Cumple días y ejercicios semanales sin obsesionarte con el marcador.',
      'achieved' => !empty($week['training_days_goal_met']) && !empty($week['exercise_goal_met']),
    ],
    [
      'code' => 'three_day_streak',
      'label' => 'Tres días de constancia',
      'description' => 'Tres objetivos diarios seguidos.',
      'achieved' => $streakDays >= 3,
    ],
    [
      'code' => 'repetitions_clear',
      'label' => 'Repasos al día',
      'description' => 'No tienes ejercicios vencidos para repetir ahora mismo.',
      'achieved' => $activityDays > 0 && $dueCount === 0,
    ],
  ];

  $achieved = array_values(array_filter($items, fn($item) => !empty($item['achieved'])));
  $next = null;
  foreach ($items as $item) {
    if (empty($item['achieved'])) {
      $next = $item;
      break;
    }
  }

  return [
    'total' => count($items),
    'achieved_count' => count($achieved),
    'items' => $items,
    'next' => $next,
  ];
}

function training_experience_summary(int $userId): array {
  $settings = training_goal_settings_for_user($userId);
  $today = training_today_progress($userId, $settings);
  $week = training_week_progress($userId, $settings);
  $streak = training_goal_streak($userId, $settings);
  $dueCount = training_due_repetition_count($userId);
  return [
    'settings' => $settings,
    'today' => $today,
    'week' => $week,
    'streak' => $streak,
    'repeat_queue' => [
      'due_count' => $dueCount,
      'sample' => training_due_repetition_sample($userId, 2),
    ],
    'milestones' => training_experience_milestones($userId, $today, $week, $streak, $dueCount),
  ];
}

function training_update_exercise_repetition(int $userId, int $exerciseId, string $result, int $attemptsCount = 0, bool $usedHint = false): void {
  if (!in_array($result, ['solved', 'failed', 'skipped'], true)) return;
  $interval = training_repetition_interval_days($result, $attemptsCount, $usedHint);
  db()->prepare('UPDATE training_exercises
                 SET last_training_result=?,
                     last_completed_at=NOW(),
                     next_due_at=DATE_ADD(NOW(), INTERVAL ' . (int)$interval . ' DAY),
                     repeat_count=repeat_count+1,
                     updated_at=NOW()
                 WHERE id=? AND user_id=?')
    ->execute([$result, $exerciseId, $userId]);
}

function training_session_metrics(int $sessionId, int $userId): array {
  $st = db()->prepare('SELECT
      COUNT(*) AS exercise_count,
      SUM(exercise_solved) AS solved_count,
      SUM(exercise_failed) AS failed_count,
      SUM(exercise_skipped) AS skipped_count,
      SUM(max_attempts) AS total_attempts,
      AVG(NULLIF(max_duration_ms,0)) AS avg_time_ms
    FROM (
      SELECT exercise_id,
             MAX(is_solved) AS exercise_solved,
             MAX(result="skipped") AS exercise_skipped,
             MAX(CASE WHEN result="failed" AND attempts_count>=5 THEN 1 ELSE 0 END) AS exercise_failed,
             MAX(attempts_count) AS max_attempts,
             MAX(duration_ms) AS max_duration_ms
      FROM training_attempts
      WHERE session_id=? AND user_id=?
      GROUP BY exercise_id
    ) session_exercises');
  $st->execute([$sessionId, $userId]);
  $row = $st->fetch() ?: [];
  return [
    'exercise_count' => (int)($row['exercise_count'] ?? 0),
    'solved_count' => (int)($row['solved_count'] ?? 0),
    'failed_count' => (int)($row['failed_count'] ?? 0),
    'skipped_count' => (int)($row['skipped_count'] ?? 0),
    'total_attempts' => (int)($row['total_attempts'] ?? 0),
    'avg_time_ms' => $row['avg_time_ms'] === null ? null : (int)round((float)$row['avg_time_ms']),
  ];
}

function training_update_session_metrics(int $sessionId, int $userId): ?array {
  $session = training_session_for_user($sessionId, $userId);
  if (!$session) return null;
  $metrics = training_session_metrics($sessionId, $userId);
  $up = db()->prepare('UPDATE training_sessions
                       SET exercise_count=?, solved_count=?, failed_count=?, skipped_count=?,
                           total_attempts=?, avg_time_ms=?, updated_at=NOW()
                       WHERE id=? AND user_id=?');
  $up->execute([
    $metrics['exercise_count'],
    $metrics['solved_count'],
    $metrics['failed_count'],
    $metrics['skipped_count'],
    $metrics['total_attempts'],
    $metrics['avg_time_ms'],
    $sessionId,
    $userId,
  ]);
  return training_session_for_user($sessionId, $userId);
}

function training_session_for_user(int $sessionId, int $userId): ?array {
  $st = db()->prepare('SELECT * FROM training_sessions WHERE id=? AND user_id=? LIMIT 1');
  $st->execute([$sessionId, $userId]);
  $session = $st->fetch();
  if (!$session) return null;
  return training_public_session($session);
}

function training_expire_stale_sessions(int $userId): void {
  db()->prepare('UPDATE training_sessions
                 SET status="abandoned", completed_at=NOW(),
                     duration_ms=GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW()) * 1000),
                     updated_at=NOW()
                 WHERE user_id=? AND status="active" AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')
    ->execute([$userId]);
}

function training_active_session(int $userId, bool $expireStale = true): ?array {
  if ($expireStale) training_expire_stale_sessions($userId);
  $st = db()->prepare('SELECT * FROM training_sessions WHERE user_id=? AND status="active" ORDER BY started_at DESC, id DESC LIMIT 1');
  $st->execute([$userId]);
  $session = $st->fetch();
  if (!$session) return null;
  return training_public_session($session);
}

function training_public_session(array $session): array {
  $session['id'] = (int)$session['id'];
  $session['user_id'] = (int)$session['user_id'];
  $session['duration_ms'] = (int)($session['duration_ms'] ?? 0);
  $session['exercise_count'] = (int)($session['exercise_count'] ?? 0);
  $session['solved_count'] = (int)($session['solved_count'] ?? 0);
  $session['failed_count'] = (int)($session['failed_count'] ?? 0);
  $session['skipped_count'] = (int)($session['skipped_count'] ?? 0);
  $session['total_attempts'] = (int)($session['total_attempts'] ?? 0);
  $session['avg_time_ms'] = $session['avg_time_ms'] === null ? null : (int)$session['avg_time_ms'];
  return $session;
}

function training_start_session(int $userId, string $selectedType = 'recommended', string $source = 'manual'): array {
  $types = training_exercise_types();
  if (!isset($types[$selectedType])) $selectedType = 'recommended';
  if (!in_array($source, ['manual', 'dashboard', 'recommended'], true)) $source = 'manual';

  $active = training_active_session($userId);
  if ($active) return ['ok' => true, 'session' => $active, 'already_active' => true];

  $st = db()->prepare('INSERT INTO training_sessions (user_id,selected_type,source,status,started_at,created_at)
                       VALUES (?, ?, ?, "active", NOW(), NOW())');
  $st->execute([$userId, $selectedType, $source]);
  $sessionId = (int)db()->lastInsertId();
  return ['ok' => true, 'session' => training_session_for_user($sessionId, $userId)];
}

function training_ensure_active_session(int $userId, string $selectedType = 'recommended', string $source = 'manual'): array {
  $active = training_active_session($userId);
  if ($active) return ['ok' => true, 'session' => $active, 'already_active' => true];
  return training_start_session($userId, $selectedType, $source);
}

function training_new_session(int $userId, string $selectedType = 'recommended', string $source = 'manual'): array {
  $active = training_active_session($userId);
  if ($active) training_end_session($userId, (int)$active['id'], 'completed');
  return training_start_session($userId, $selectedType, $source);
}

function training_end_session(int $userId, int $sessionId, string $status = 'completed'): array {
  if (!in_array($status, ['completed', 'abandoned'], true)) $status = 'completed';
  $session = training_session_for_user($sessionId, $userId);
  if (!$session || $session['status'] !== 'active') {
    return ['ok' => false, 'error' => 'Sesión de entrenamiento no encontrada.'];
  }
  $session = training_update_session_metrics($sessionId, $userId) ?: $session;
  $durationMs = max(0, (int)round((time() - strtotime((string)$session['started_at'])) * 1000));
  $up = db()->prepare('UPDATE training_sessions
                       SET status=?, completed_at=NOW(), duration_ms=?, updated_at=NOW()
                       WHERE id=? AND user_id=? AND status="active"');
  $up->execute([$status, $durationMs, $sessionId, $userId]);
  return ['ok' => true, 'session' => training_session_for_user($sessionId, $userId)];
}

function training_type_counts_for_user(int $userId): array {
  $st = db()->prepare('SELECT exercise_type, COUNT(*) AS total,
                              SUM(status="active" AND (resolved_at IS NULL OR (next_due_at IS NOT NULL AND next_due_at <= NOW()))) AS pending,
                              SUM(resolved_at IS NOT NULL) AS resolved
                       FROM training_exercises
                       WHERE user_id=?
                       GROUP BY exercise_type
                       ORDER BY exercise_type');
  $st->execute([$userId]);
  $counts = [];
  foreach ($st->fetchAll() as $row) {
    $type = (string)$row['exercise_type'];
    $counts[$type] = [
      'total' => (int)($row['total'] ?? 0),
      'pending' => (int)($row['pending'] ?? 0),
      'resolved' => (int)($row['resolved'] ?? 0),
    ];
  }
  return $counts;
}

function training_exercise_tags_for_ids(array $exerciseIds, int $userId): array {
  $exerciseIds = array_values(array_unique(array_map('intval', $exerciseIds)));
  if (!$exerciseIds) return [];
  $placeholders = implode(',', array_fill(0, count($exerciseIds), '?'));
  $sql = "SELECT tet.exercise_id, tet.tag_code, tet.source, d.label, d.category, d.severity
          FROM training_exercise_tags tet
          JOIN training_exercises te ON te.id=tet.exercise_id
          JOIN smart_tag_definitions d ON d.code=tet.tag_code
          WHERE te.user_id=? AND tet.exercise_id IN ($placeholders)
          ORDER BY FIELD(d.severity,'critical','high','medium','low','info'), d.label ASC";
  $st = db()->prepare($sql);
  $st->execute(array_merge([$userId], $exerciseIds));
  $byExercise = [];
  foreach ($st->fetchAll() as $tag) {
    $byExercise[(int)$tag['exercise_id']][] = $tag;
  }
  return $byExercise;
}

function training_exercise_is_repeat_due(array $item): bool {
  return !empty($item['resolved_at'])
    && !empty($item['next_due_at'])
    && strtotime((string)$item['next_due_at']) <= time();
}

function training_mark_exercise_training_state(array $item): array {
  $isRepeatDue = training_exercise_is_repeat_due($item);
  $item['repeat_count'] = (int)($item['repeat_count'] ?? 0);
  $item['is_repeat_due'] = $isRepeatDue;
  $item['is_trainable'] = empty($item['resolved_at']) || $isRepeatDue;
  $item['repetition_reason'] = training_repetition_due_label($item['last_training_result'] ?? null, $item['last_completed_at'] ?? null);
  return $item;
}

function training_public_exercise(array $item, bool $includeSolution = false): array {
  $solution = strtolower(trim((string)($item['solution_uci'] ?? '')));
  $item = training_mark_exercise_training_state($item);
  if ($includeSolution && $solution !== '') {
    $solutionSan = chess_uci_to_san((string)($item['fen'] ?? ''), $solution);
    $item['solution_san'] = $solutionSan;
    $item['solution_display'] = $solutionSan ?? chess_uci_fallback($solution);
  } else {
    unset(
      $item['solution_uci'],
      $item['solution_san'],
      $item['solution_display'],
      $item['engine_bestmove_uci'],
      $item['engine_pv_uci'],
      $item['engine_score'],
      $item['engine_score_type'],
      $item['engine_original_score'],
      $item['engine_original_score_type'],
      $item['engine_original_depth'],
      $item['accepted_alternative_uci']
    );
  }
  return $item;
}

function training_list_exercises(int $userId, string $type = 'recommended', string $status = 'pending', int $page = 1, int $perPage = 20): array {
  $types = training_exercise_types();
  if (!isset($types[$type])) $type = 'recommended';
  if (!in_array($status, ['pending', 'failed', 'resolved', 'all'], true)) $status = 'pending';

  $perPage = max(1, min(100, $perPage));
  $where = ['te.user_id=?', 'te.status="active"'];
  $params = [$userId];

  if ($type !== 'recommended') {
    $where[] = 'te.exercise_type=?';
    $params[] = $type;
  }
  if ($status === 'pending') {
    $where[] = '(te.resolved_at IS NULL OR (te.next_due_at IS NOT NULL AND te.next_due_at <= NOW()))';
  } elseif ($status === 'failed') {
    $where[] = 'te.last_training_result="failed"';
  } elseif ($status === 'resolved') {
    $where[] = 'te.resolved_at IS NOT NULL AND (te.next_due_at IS NULL OR te.next_due_at > NOW())';
  }

  $whereSql = implode(' AND ', $where);
  $countSt = db()->prepare("SELECT COUNT(*) FROM training_exercises te WHERE $whereSql");
  $countSt->execute($params);
  $total = (int)$countSt->fetchColumn();
  $pages = max(1, (int)ceil($total / $perPage));
  $page = max(1, min($pages, $page));
  $offset = ($page - 1) * $perPage;

  $order = $type === 'recommended'
    ? 'CASE WHEN te.resolved_at IS NOT NULL AND te.next_due_at IS NOT NULL AND te.next_due_at <= NOW() THEN 0 WHEN te.resolved_at IS NULL THEN 1 ELSE 2 END ASC, te.priority_score DESC, te.last_attempt_at IS NULL DESC, te.created_at DESC, te.id DESC'
    : 'CASE WHEN te.resolved_at IS NOT NULL AND te.next_due_at IS NOT NULL AND te.next_due_at <= NOW() THEN 0 WHEN te.resolved_at IS NULL THEN 1 ELSE 2 END ASC, te.created_at DESC, te.priority_score DESC, te.id DESC';

  $sql = "SELECT te.*, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at,
                 g.event_name, g.site, g.eco_code, g.opening_name,
                 ga.completed_at,
                 COALESCE(attempts.attempt_count,0) AS attempt_count,
                 attempts.last_result
          FROM training_exercises te
          JOIN games g ON g.id=te.game_id
          JOIN game_analysis ga ON ga.id=te.analysis_id
          LEFT JOIN (
            SELECT exercise_id, COUNT(*) AS attempt_count,
                   SUBSTRING_INDEX(GROUP_CONCAT(result ORDER BY created_at DESC, id DESC), ',', 1) AS last_result
            FROM training_attempts
            WHERE user_id=?
            GROUP BY exercise_id
          ) attempts ON attempts.exercise_id=te.id
          WHERE $whereSql
          ORDER BY $order
          LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
  $st = db()->prepare($sql);
  $st->execute(array_merge([$userId], $params));
  $items = $st->fetchAll();

  $tagsByExercise = training_exercise_tags_for_ids(array_column($items, 'id'), $userId);
  foreach ($items as &$item) {
    $item['id'] = (int)$item['id'];
    $item['game_id'] = (int)$item['game_id'];
    $item['analysis_id'] = (int)$item['analysis_id'];
    $item['move_analysis_id'] = (int)$item['move_analysis_id'];
    $item['ply'] = (int)$item['ply'];
    $item['centipawn_loss'] = (int)$item['centipawn_loss'];
    $item['priority_score'] = (int)$item['priority_score'];
    $item['attempt_count'] = (int)$item['attempt_count'];
    $item['type_label'] = trim((string)($item['title'] ?? '')) ?: training_exercise_type_label((string)$item['exercise_type']);
    $item['review_url'] = 'review.php?id=' . (int)$item['game_id'];
    $item['smart_tags'] = $tagsByExercise[(int)$item['id']] ?? [];
    $item = training_mark_exercise_training_state($item);
    $includeSolution = !empty($item['resolved_at']) && empty($item['is_repeat_due']);
    $item = training_public_exercise($item, $includeSolution);
  }
  unset($item);

  return [
    'items' => $items,
    'pagination' => [
      'page' => $page,
      'per_page' => $perPage,
      'total' => $total,
      'pages' => $pages,
    ],
    'filters' => [
      'type' => $type,
      'status' => $status,
    ],
  ];
}

function training_exercise_for_user(int $exerciseId, int $userId): ?array {
  $st = db()->prepare('SELECT te.*, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at,
                              g.event_name, g.site, g.eco_code, g.opening_name,
                              prev.ply AS previous_ply, prev.uci AS previous_uci, prev.san AS previous_san,
                              COALESCE(attempts.attempt_count,0) AS attempt_count,
                              attempts.last_result
                       FROM training_exercises te
                       JOIN games g ON g.id=te.game_id
                       LEFT JOIN game_move_analysis prev ON prev.analysis_id=te.analysis_id AND prev.ply=te.ply-1
                       LEFT JOIN (
                         SELECT exercise_id, COUNT(*) AS attempt_count,
                                SUBSTRING_INDEX(GROUP_CONCAT(result ORDER BY created_at DESC, id DESC), \',\', 1) AS last_result
                         FROM training_attempts
                         WHERE user_id=?
                         GROUP BY exercise_id
                       ) attempts ON attempts.exercise_id=te.id
                       WHERE te.id=? AND te.user_id=? AND te.status="active"
                       LIMIT 1');
  $st->execute([$userId, $exerciseId, $userId]);
  $item = $st->fetch();
  if (!$item) return null;

  $tagsByExercise = training_exercise_tags_for_ids([(int)$item['id']], $userId);
  $item['id'] = (int)$item['id'];
  $item['game_id'] = (int)$item['game_id'];
  $item['analysis_id'] = (int)$item['analysis_id'];
  $item['move_analysis_id'] = (int)$item['move_analysis_id'];
  $item['ply'] = (int)$item['ply'];
  $item['previous_ply'] = $item['previous_ply'] === null ? null : (int)$item['previous_ply'];
  $item['centipawn_loss'] = (int)$item['centipawn_loss'];
  $item['priority_score'] = (int)$item['priority_score'];
  $item['attempt_count'] = (int)$item['attempt_count'];
  $item['type_label'] = trim((string)($item['title'] ?? '')) ?: training_exercise_type_label((string)$item['exercise_type']);
  $item['review_url'] = 'review.php?id=' . (int)$item['game_id'];
  $item['smart_tags'] = $tagsByExercise[(int)$item['id']] ?? [];
  return training_mark_exercise_training_state($item);
}

function training_sanitize_attempted_moves(array $moves): array {
  $clean = [];
  foreach ($moves as $move) {
    $uci = strtolower(trim((string)$move));
    if (preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $uci)) $clean[] = $uci;
    if (count($clean) >= 5) break;
  }
  return $clean;
}

function training_record_attempt(int $userId, int $exerciseId, array $attemptedMoves, int $durationMs = 0, bool $usedHint = false, ?int $sessionId = null): array {
  $exercise = training_exercise_for_user($exerciseId, $userId);
  if (!$exercise) return ['ok' => false, 'error' => 'Ejercicio no encontrado.'];
  if (!empty($exercise['resolved_at']) && empty($exercise['is_repeat_due'])) {
    return [
      'ok' => true,
      'already_resolved' => true,
      'exercise' => $exercise,
      'feedback' => 'Este ejercicio ya está completado y todavía no toca repetirlo.',
    ];
  }

  $moves = training_sanitize_attempted_moves($attemptedMoves);
  if (!$moves) return ['ok' => false, 'error' => 'No se ha enviado ninguna jugada válida.'];

  $solution = strtolower(trim((string)($exercise['solution_uci'] ?? '')));
  $alternative = strtolower(trim((string)($exercise['accepted_alternative_uci'] ?? '')));
  $acceptedSolutions = array_values(array_filter(array_unique([$solution, $alternative]), 'training_valid_solution'));
  $finalMove = end($moves);
  $matchedSolution = in_array($finalMove, $acceptedSolutions, true) ? $finalMove : null;
  $isSolved = $matchedSolution !== null;
  $isExhausted = count($moves) >= 5;
  $result = $isSolved ? 'solved' : 'failed';
  $durationMs = max(0, min(86400000, $durationMs));
  $sessionId = $sessionId && training_session_is_active($sessionId, $userId) ? $sessionId : null;
  if (!$sessionId) {
    $sessionResult = training_ensure_active_session($userId, 'recommended', 'manual');
    $sessionId = !empty($sessionResult['session']['id']) ? (int)$sessionResult['session']['id'] : null;
  }

  $ins = db()->prepare('INSERT INTO training_attempts
      (session_id,exercise_id,user_id,started_at,completed_at,duration_ms,attempts_count,first_move_uci,final_move_uci,
       attempted_moves_json,is_solved,result,used_hint,difficulty_after_attempt,created_at)
      VALUES (?,?,?,NOW(),NOW(),?,?,?,?,?,?,?,?,?,NOW())');
  $ins->execute([
    $sessionId,
    $exerciseId,
    $userId,
    $durationMs,
    count($moves),
    $moves[0],
    $finalMove,
    json_encode($moves, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $isSolved ? 1 : 0,
    $result,
    $usedHint ? 1 : 0,
    $exercise['difficulty'] ?? null,
  ]);

  if ($isSolved || $isExhausted) {
    db()->prepare('UPDATE training_exercises SET resolved_at=COALESCE(resolved_at,NOW()), last_attempt_at=NOW(), updated_at=NOW() WHERE id=? AND user_id=?')->execute([$exerciseId, $userId]);
    training_update_exercise_repetition($userId, $exerciseId, $isSolved ? 'solved' : 'failed', count($moves), $usedHint);
  } else {
    db()->prepare('UPDATE training_exercises SET last_attempt_at=NOW(), last_training_result="failed", updated_at=NOW() WHERE id=? AND user_id=?')->execute([$exerciseId, $userId]);
  }

  $updated = training_exercise_for_user($exerciseId, $userId);
  $session = $sessionId ? training_update_session_metrics($sessionId, $userId) : null;
  return [
    'ok' => true,
    'solved' => $isSolved,
    'exhausted' => $isExhausted && !$isSolved,
    'attempts_count' => count($moves),
    'remaining_attempts' => max(0, 5 - count($moves)),
    'solution_uci' => $isSolved ? $matchedSolution : ($isExhausted ? $solution : null),
    'attempted_moves' => $moves,
    'feedback' => training_attempt_feedback($exercise, $isSolved, $isExhausted, count($moves), $usedHint),
    'exercise' => $updated,
    'stats' => training_stats_for_user($userId),
    'experience' => training_experience_summary($userId),
    'session' => $session,
  ];
}

function training_session_is_active(int $sessionId, int $userId): bool {
  training_expire_stale_sessions($userId);
  $st = db()->prepare('SELECT COUNT(*) FROM training_sessions WHERE id=? AND user_id=? AND status="active"');
  $st->execute([$sessionId, $userId]);
  return (int)$st->fetchColumn() > 0;
}

function training_record_skip(int $userId, int $exerciseId, ?int $sessionId = null): array {
  $exercise = training_exercise_for_user($exerciseId, $userId);
  if (!$exercise) return ['ok' => false, 'error' => 'Ejercicio no encontrado.'];
  $sessionId = $sessionId && training_session_is_active($sessionId, $userId) ? $sessionId : null;
  if (!$sessionId) {
    $sessionResult = training_ensure_active_session($userId, 'recommended', 'manual');
    $sessionId = !empty($sessionResult['session']['id']) ? (int)$sessionResult['session']['id'] : null;
  }
  if (!$sessionId) return ['ok' => false, 'error' => 'No se pudo registrar la sesión de entrenamiento.'];

  $ins = db()->prepare('INSERT INTO training_attempts
      (session_id,exercise_id,user_id,started_at,completed_at,duration_ms,attempts_count,
       attempted_moves_json,is_solved,result,used_hint,difficulty_after_attempt,created_at)
      VALUES (?,?,?,NOW(),NOW(),0,0,?,0,"skipped",0,?,NOW())');
  $ins->execute([
    $sessionId,
    $exerciseId,
    $userId,
    json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $exercise['difficulty'] ?? null,
  ]);
  db()->prepare('UPDATE training_exercises SET resolved_at=COALESCE(resolved_at,NOW()), last_attempt_at=NOW(), updated_at=NOW() WHERE id=? AND user_id=?')->execute([$exerciseId, $userId]);
  training_update_exercise_repetition($userId, $exerciseId, 'skipped', 0, false);
  $updated = training_exercise_for_user($exerciseId, $userId);
  $session = training_update_session_metrics($sessionId, $userId);
  return [
    'ok' => true,
    'skipped' => true,
    'feedback' => 'Ejercicio saltado. No cuenta para el objetivo de hoy y volverá más adelante con prioridad baja.',
    'exercise' => $updated ?: $exercise,
    'stats' => training_stats_for_user($userId),
    'experience' => training_experience_summary($userId),
    'session' => $session,
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
  $gameSt = db()->prepare('SELECT gt.tag_code, gt.primary_ply, gt.details_json, d.label, d.category, d.severity
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
  $relevantGameTags = training_relevant_game_tags($gameTags, (int)($move['ply'] ?? 0));
  if (!training_candidate_is_useful($move, $moveTags, $relevantGameTags, $sourceSide)) {
    return ['ok' => true, 'created' => false, 'skipped' => true, 'reason' => 'not-useful'];
  }

  $type = training_exercise_type_for_candidate($move, $moveTags, $relevantGameTags, $sourceSide);
  $difficulty = training_difficulty_for_candidate($move, $moveTags);
  $priority = training_priority_score($move, $moveTags, $relevantGameTags, $sourceSide, $focusCode);
  $fen = (string)($move['fen_before'] ?? '');
  $sideToMove = training_fen_side_to_move($fen);
  $solution = strtolower(trim((string)($move['bestmove'] ?? '')));

  $exists = db()->prepare('SELECT id FROM training_exercises WHERE user_id=? AND move_analysis_id=? AND exercise_type=? LIMIT 1');
  $exists->execute([$userId, (int)$move['id'], $type]);
  $existingId = (int)($exists->fetchColumn() ?: 0);

  $sql = 'INSERT INTO training_exercises
            (user_id,game_id,analysis_id,move_analysis_id,ply,source_side,exercise_type,fen,solution_uci,solution_san,
             played_uci,played_san,centipawn_loss,classification,difficulty,priority_score,source_focus_code,title,prompt,
             feedback_success,feedback_failure,content_version,status,created_at)
          VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, \'active\', NOW())
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
            title=VALUES(title),
            prompt=VALUES(prompt),
            feedback_success=VALUES(feedback_success),
            feedback_failure=VALUES(feedback_failure),
            content_version=VALUES(content_version)';
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
    training_title_for_type($type),
    training_prompt_for_type($type, $sideToMove),
    training_feedback_success($type),
    training_feedback_failure($type),
    TRAINING_EXERCISE_CONTENT_VERSION,
  ]);

  $exerciseId = (int)db()->lastInsertId();
  training_insert_exercise_tags($exerciseId, $moveTags, 'move');
  training_insert_exercise_tags($exerciseId, $relevantGameTags, 'game');

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
      $errors[] = public_error_message($e);
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
      $errors[] = public_error_message($e);
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

function training_content_backfill_pending_count(int $userId): int {
  $st = db()->prepare('SELECT COUNT(*) FROM training_exercises WHERE user_id=? AND content_version<?');
  $st->execute([$userId, TRAINING_EXERCISE_CONTENT_VERSION]);
  return (int)$st->fetchColumn();
}

function training_content_backfill_batch(int $userId, int $limit = 200): array {
  $limit = max(1, min(500, $limit));
  $pendingBefore = training_content_backfill_pending_count($userId);
  $sql = 'SELECT te.id, te.analysis_id, te.move_analysis_id, te.source_side, te.exercise_type,
                 te.source_focus_code, ma.ply, ma.fen_before, ma.bestmove, ma.score_before,
                 ma.score_before_type, ma.score_after, ma.score_after_type, ma.centipawn_loss,
                 ma.classification, ma.san, ma.uci
          FROM training_exercises te
          JOIN game_move_analysis ma ON ma.id=te.move_analysis_id AND ma.analysis_id=te.analysis_id
          WHERE te.user_id=? AND te.content_version<?
          ORDER BY te.id
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, TRAINING_EXERCISE_CONTENT_VERSION]);
  $rows = $st->fetchAll();

  $tagsByAnalysis = [];
  $updated = 0;
  $retyped = 0;
  $typeConflicts = 0;
  $errors = [];

  foreach ($rows as $row) {
    $analysisId = (int)$row['analysis_id'];
    if (!isset($tagsByAnalysis[$analysisId])) {
      $tagsByAnalysis[$analysisId] = training_tags_for_analysis($analysisId, $userId);
    }
    $analysisTags = $tagsByAnalysis[$analysisId];
    $moveTags = $analysisTags['move_tags'][(int)$row['move_analysis_id']] ?? [];
    $gameTags = training_relevant_game_tags($analysisTags['game_tags'] ?? [], (int)$row['ply']);
    $desiredType = training_exercise_type_for_candidate($row, $moveTags, $gameTags, (string)$row['source_side']);
    $storedType = (string)$row['exercise_type'];
    $typeToStore = $desiredType;

    if ($desiredType !== $storedType) {
      $conflictSt = db()->prepare('SELECT id FROM training_exercises
                                   WHERE user_id=? AND move_analysis_id=? AND exercise_type=? AND id<>?
                                   LIMIT 1');
      $conflictSt->execute([$userId, (int)$row['move_analysis_id'], $desiredType, (int)$row['id']]);
      if ($conflictSt->fetchColumn()) {
        $typeToStore = $storedType;
        $typeConflicts++;
      }
    }

    $sideToMove = training_fen_side_to_move((string)$row['fen_before']);
    $priority = training_priority_score(
      $row,
      $moveTags,
      $gameTags,
      (string)$row['source_side'],
      $row['source_focus_code'] === null ? null : (string)$row['source_focus_code']
    );

    try {
      db()->beginTransaction();
      $up = db()->prepare('UPDATE training_exercises
                           SET exercise_type=?, title=?, prompt=?, feedback_success=?, feedback_failure=?,
                               priority_score=?, content_version=?
                           WHERE id=? AND user_id=?');
      $up->execute([
        $typeToStore,
        training_title_for_type($desiredType),
        training_prompt_for_type($desiredType, $sideToMove),
        training_feedback_success($desiredType),
        training_feedback_failure($desiredType),
        $priority,
        TRAINING_EXERCISE_CONTENT_VERSION,
        (int)$row['id'],
        $userId,
      ]);
      db()->prepare('DELETE FROM training_exercise_tags WHERE exercise_id=? AND source IN ("move","game")')
        ->execute([(int)$row['id']]);
      training_insert_exercise_tags((int)$row['id'], $moveTags, 'move');
      training_insert_exercise_tags((int)$row['id'], $gameTags, 'game');
      db()->commit();
      $updated++;
      if ($typeToStore !== $storedType) $retyped++;
    } catch (Throwable $e) {
      if (db()->inTransaction()) db()->rollBack();
      $errors[] = public_error_message($e);
    }
  }

  $pendingAfter = training_content_backfill_pending_count($userId);
  return [
    'ok' => !$errors,
    'processed' => count($rows),
    'updated' => $updated,
    'retyped' => $retyped,
    'type_conflicts' => $typeConflicts,
    'error_count' => count($errors),
    'pending_before' => $pendingBefore,
    'pending_after' => $pendingAfter,
    'message' => $rows
      ? ($errors ? 'Actualización de contenido completada con errores parciales.' : 'Contenido de ejercicios actualizado correctamente.')
      : 'Todos los ejercicios ya tienen el contenido actualizado.',
    'errors' => array_slice($errors, 0, 5),
  ];
}

function training_engine_content(array $exercise, array $evaluation): array {
  $type = (string)($exercise['exercise_type'] ?? 'find_best_move');
  $side = training_fen_side_to_move((string)($exercise['fen'] ?? '')) === 'b' ? 'negras' : 'blancas';
  $title = trim((string)($exercise['title'] ?? '')) ?: training_title_for_type($type);
  $prompt = trim((string)($exercise['prompt'] ?? '')) ?: training_prompt_for_type($type, $side === 'negras' ? 'b' : 'w');
  $scoreType = (string)($evaluation['score_type'] ?? 'cp');
  $score = (int)($evaluation['score'] ?? 0);

  if ($scoreType === 'mate' && $score > 0) {
    $distance = abs($score);
    $title = $distance > 0 ? "Encuentra el mate en {$distance}" : 'Remata la posición';
    $prompt = "Stockfish confirma una secuencia forzada de mate. Encuentra el primer movimiento. Juegan {$side}.";
  } elseif ($scoreType === 'mate' && $score < 0) {
    $title = 'Encuentra la mejor defensa';
    $prompt = "El rival dispone de una secuencia forzada. Encuentra la defensa que ofrece más resistencia. Juegan {$side}.";
  } elseif ($type === 'convert_advantage' && $score >= 250) {
    $prompt = "Stockfish confirma una ventaja clara en la posición. Encuentra la continuación que la conserva. Juegan {$side}.";
  } elseif ($type === 'defend_position' && $score < 0) {
    $prompt = "La posición exige precisión. Encuentra la continuación que limita la presión rival. Juegan {$side}.";
  }

  return ['title' => $title, 'prompt' => $prompt];
}

function training_engine_score_value(array $evaluation): ?int {
  $type = (string)($evaluation['score_type'] ?? '');
  $score = (int)($evaluation['score'] ?? 0);
  if ($type === 'cp') return $score;
  if ($type !== 'mate' || $score === 0) return null;
  return $score > 0
    ? 100000 - (abs($score) * 1000)
    : -100000 + (abs($score) * 1000);
}

function training_engine_solutions_equivalent(array $best, array $candidate): bool {
  $bestType = (string)($best['score_type'] ?? '');
  $candidateType = (string)($candidate['score_type'] ?? '');
  if ($bestType !== $candidateType) return false;

  $bestValue = training_engine_score_value($best);
  $candidateValue = training_engine_score_value($candidate);
  if ($bestValue === null || $candidateValue === null) return false;

  if ($bestType === 'cp') return ($bestValue - $candidateValue) <= 30;

  $bestScore = (int)($best['score'] ?? 0);
  $candidateScore = (int)($candidate['score'] ?? 0);
  if (($bestScore > 0) !== ($candidateScore > 0)) return false;
  return ($bestValue - $candidateValue) <= 2000;
}

function training_engine_backfill_pending_count(int $userId): int {
  $st = db()->prepare('SELECT COUNT(*)
                       FROM training_exercises
                       WHERE user_id=? AND status="active" AND resolved_at IS NULL
                         AND content_version>=?
                         AND (content_version<? OR
                              (content_version=3 AND engine_solution_mismatch=1 AND engine_original_depth IS NULL))');
  $st->execute([$userId, TRAINING_EXERCISE_CONTENT_VERSION, 3]);
  return (int)$st->fetchColumn();
}

function training_engine_backfill_batch(int $userId, int $limit = 10): array {
  $limit = max(1, min(10, $limit));
  $pendingBefore = training_engine_backfill_pending_count($userId);
  $sql = 'SELECT id, exercise_type, fen, solution_uci, title, prompt, feedback_success
          FROM training_exercises
          WHERE user_id=? AND status="active" AND resolved_at IS NULL
            AND content_version>=?
            AND (content_version<? OR
                 (content_version=3 AND engine_solution_mismatch=1 AND engine_original_depth IS NULL))
          ORDER BY CASE WHEN content_version=3 THEN 0 ELSE 1 END, priority_score DESC, id
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, TRAINING_EXERCISE_CONTENT_VERSION, 3]);
  $rows = $st->fetchAll();

  if (!$rows) {
    return [
      'ok' => true,
      'processed' => 0,
      'updated' => 0,
      'mismatches' => 0,
      'alternatives_accepted' => 0,
      'alternatives_rejected' => 0,
      'error_count' => 0,
      'pending_before' => $pendingBefore,
      'pending_after' => $pendingBefore,
      'message' => 'No hay ejercicios pendientes de enriquecer con Stockfish.',
      'errors' => [],
    ];
  }

  $updated = 0;
  $mismatches = 0;
  $alternativesAccepted = 0;
  $alternativesRejected = 0;
  $errors = [];
  $runner = null;

  try {
    $runner = stockfish_runner();
    foreach ($rows as $row) {
      try {
        $evaluation = $runner->evalFen((string)$row['fen']);
        $bestmove = strtolower(trim((string)($evaluation['bestmove'] ?? '')));
        $pv = is_array($evaluation['pv'] ?? null) ? $evaluation['pv'] : [];
        if (!training_valid_solution($bestmove) || !$pv || strtolower((string)$pv[0]) !== $bestmove) {
          throw new RuntimeException('Stockfish no devolvió una variante principal válida.');
        }

        $storedSolution = strtolower(trim((string)$row['solution_uci']));
        $mismatch = $storedSolution !== $bestmove;
        $originalEvaluation = $evaluation;
        $acceptedAlternative = null;
        if ($mismatch) {
          $originalEvaluation = $runner->evalFenWithSearchMoves((string)$row['fen'], [$storedSolution]);
          $originalBestmove = strtolower(trim((string)($originalEvaluation['bestmove'] ?? '')));
          if ($originalBestmove !== $storedSolution) {
            throw new RuntimeException('Stockfish no pudo evaluar la solución original de forma controlada.');
          }
          if (training_engine_solutions_equivalent($evaluation, $originalEvaluation)) {
            $acceptedAlternative = $bestmove;
          }
        }
        $content = $mismatch
          ? ['title' => (string)$row['title'], 'prompt' => (string)$row['prompt']]
          : training_engine_content($row, $evaluation);
        $feedback = (string)($row['feedback_success'] ?? '');
        if (!$mismatch && $feedback !== '') {
          $feedback .= ' La verificación reciente de Stockfish confirma esta primera jugada.';
        }

        $up = db()->prepare('UPDATE training_exercises
                             SET title=?, prompt=?, feedback_success=?, content_version=3,
                                 engine_bestmove_uci=?, engine_pv_uci=?, engine_score=?, engine_score_type=?,
                                 engine_depth=?, engine_original_score=?, engine_original_score_type=?,
                                 engine_original_depth=?, accepted_alternative_uci=?,
                                 engine_solution_mismatch=?, engine_refreshed_at=NOW()
                             WHERE id=? AND user_id=? AND resolved_at IS NULL');
        $up->execute([
          $content['title'],
          $content['prompt'],
          $feedback,
          $bestmove,
          implode(' ', $pv),
          (int)($evaluation['score'] ?? 0),
          in_array(($evaluation['score_type'] ?? 'cp'), ['cp', 'mate'], true) ? $evaluation['score_type'] : 'cp',
          max(0, (int)($evaluation['depth'] ?? 0)),
          (int)($originalEvaluation['score'] ?? 0),
          in_array(($originalEvaluation['score_type'] ?? 'cp'), ['cp', 'mate'], true) ? $originalEvaluation['score_type'] : 'cp',
          max(0, (int)($originalEvaluation['depth'] ?? 0)),
          $acceptedAlternative,
          $mismatch ? 1 : 0,
          (int)$row['id'],
          $userId,
        ]);
        if ($up->rowCount() > 0) {
          $updated++;
          if ($mismatch) {
            $mismatches++;
            if ($acceptedAlternative !== null) $alternativesAccepted++;
            else $alternativesRejected++;
          }
        }
      } catch (Throwable $e) {
        $errors[] = 'Ejercicio ' . (int)$row['id'] . ': ' . public_error_message($e);
      }
    }
  } catch (Throwable $e) {
    $errors[] = public_error_message($e);
  } finally {
    if ($runner instanceof StockfishRunner) $runner->close();
  }

  $pendingAfter = training_engine_backfill_pending_count($userId);
  return [
    'ok' => !$errors,
    'processed' => count($rows),
    'updated' => $updated,
    'mismatches' => $mismatches,
    'alternatives_accepted' => $alternativesAccepted,
    'alternatives_rejected' => $alternativesRejected,
    'error_count' => count($errors),
    'pending_before' => $pendingBefore,
    'pending_after' => $pendingAfter,
    'message' => $errors
      ? 'Enriquecimiento Stockfish completado con errores parciales.'
      : 'Ejercicios enriquecidos con Stockfish correctamente.',
    'errors' => array_slice($errors, 0, 5),
  ];
}
