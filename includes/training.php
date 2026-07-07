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
