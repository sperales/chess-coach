<?php
require_once __DIR__ . '/training.php';
require_once __DIR__ . '/training_progress.php';
require_once __DIR__ . '/coach.php';

const TRAINING_HINT_GENERATOR_VERSION = 2;

function training_hint_level_is_available(int $currentLevel, int $requestedLevel): bool {
  $currentLevel = training_progress_hint_level($currentLevel);
  return $requestedLevel >= 1
    && $requestedLevel <= TRAINING_PROGRESS_MAX_HINT_LEVEL
    && $requestedLevel <= $currentLevel + 1;
}

function training_hint_piece_name(string $piece): string {
  return match (strtolower($piece)) {
    'p' => 'peón',
    'n' => 'caballo',
    'b' => 'alfil',
    'r' => 'torre',
    'q' => 'dama',
    'k' => 'rey',
    default => 'pieza',
  };
}

function training_hint_board_region(string $square): string {
  $file = strtolower($square[0] ?? '');
  if (strpos('abc', $file) !== false) return 'el flanco de dama';
  if (strpos('fgh', $file) !== false) return 'el flanco de rey';
  return 'el centro';
}

function training_hint_level_one_text(string $exerciseType, string $region): string {
  return match ($exerciseType) {
    'find_mate' => "Centra tu atención en {$region} y en las casillas de escape del rey rival.",
    'spot_threat', 'defend_position' => "Revisa qué pieza o casilla de {$region} sostiene la amenaza rival.",
    'convert_advantage' => "Mira {$region}: allí está el elemento que puede reducir el contrajuego rival.",
    'avoid_blunder' => "Comprueba primero qué queda vulnerable en {$region} después de cada candidata.",
    'find_tactic' => "Hay una característica concreta de {$region} que permite un recurso forzante.",
    'other' => "La decisión de la posición se concentra en {$region}.",
    default => "Compara primero la actividad y las amenazas en {$region}.",
  };
}

function training_hint_level_two_text(string $exerciseType, string $pieceName): string {
  return match ($exerciseType) {
    'find_mate' => "Tu {$pieceName} participa en la red que limita al rey rival.",
    'spot_threat', 'defend_position' => "Tu {$pieceName} puede intervenir sobre el recurso principal del rival.",
    'convert_advantage' => "Tu {$pieceName} es la pieza que puede mejorar el control de la posición.",
    'find_tactic' => "Tu {$pieceName} puede crear el recurso forzante que buscas.",
    default => "La pieza clave para ejecutar la idea es tu {$pieceName}.",
  };
}

function training_hint_solution_context(array $exercise): ?array {
  $fen = (string)($exercise['fen'] ?? '');
  $solution = strtolower(trim((string)($exercise['solution_uci'] ?? '')));
  if (!training_valid_solution($solution)) return null;

  $state = chess_state_from_fen($fen);
  if (!$state) return null;
  [$fromRank, $fromFile] = sq_to_rf(substr($solution, 0, 2));
  [$toRank, $toFile] = sq_to_rf(substr($solution, 2, 2));
  if (!in_bounds($fromRank, $fromFile) || !in_bounds($toRank, $toFile)) return null;

  $piece = (string)($state['b'][$fromRank][$fromFile] ?? '');
  if ($piece === '') return null;
  $san = chess_uci_to_san($fen, $solution);
  $region = training_hint_board_region(substr($solution, 2, 2));

  if ($san === 'O-O' || $san === 'O-O-O') {
    $action = "La idea concreta es enrocar hacia {$region}.";
  } elseif (strlen($solution) === 5) {
    $action = "La idea concreta es promocionar un peón en {$region}.";
  } elseif ($san !== null && str_ends_with($san, '#')) {
    $action = "La idea concreta es dar mate en {$region}.";
  } elseif ($san !== null && str_ends_with($san, '+')) {
    $action = "La idea concreta es dar jaque en {$region}.";
  } elseif ($san !== null && strpos($san, 'x') !== false) {
    $action = "La idea concreta es realizar una captura en {$region}.";
  } else {
    $action = "La idea concreta es llevar la pieza hacia {$region}.";
  }

  return [
    'from' => substr($solution, 0, 2),
    'piece_name' => training_hint_piece_name($piece),
    'region' => $region,
    'action_text' => $action,
  ];
}

function training_hint_generate(array $exercise, int $level): ?array {
  if ($level < 1 || $level > TRAINING_PROGRESS_MAX_HINT_LEVEL) return null;
  $context = training_hint_solution_context($exercise);
  if (!$context) return null;

  if ($level === 1) {
    return [
      'level' => 1,
      'type' => 'attention_area',
      'text' => training_hint_level_one_text((string)($exercise['exercise_type'] ?? 'find_best_move'), $context['region']),
      'highlight_squares' => [],
    ];
  }

  if ($level === 2) {
    return [
      'level' => 2,
      'type' => 'piece',
      'text' => training_hint_level_two_text((string)($exercise['exercise_type'] ?? 'find_best_move'), $context['piece_name']),
      'highlight_squares' => [$context['from']],
    ];
  }

  return [
    'level' => 3,
    'type' => 'action_region',
    'text' => $context['action_text'],
    'highlight_squares' => [$context['from']],
  ];
}

function training_hint_public_run(array $run, array $exercise): array {
  $st = db()->prepare('SELECT hint_level,hint_type,hint_text,created_at
                       FROM training_solve_hints
                       WHERE solve_run_id=? AND user_id=?
                       ORDER BY hint_level ASC');
  $st->execute([(int)$run['id'], (int)$run['user_id']]);
  $hints = [];
  foreach ($st->fetchAll() as $stored) {
    $generated = training_hint_generate($exercise, (int)$stored['hint_level']);
    $hints[] = [
      'level' => (int)$stored['hint_level'],
      'type' => (string)$stored['hint_type'],
      'text' => (string)$stored['hint_text'],
      'highlight_squares' => $generated['highlight_squares'] ?? [],
      'created_at' => $stored['created_at'],
    ];
  }

  return [
    'id' => (int)$run['id'],
    'exercise_id' => (int)$run['exercise_id'],
    'status' => (string)$run['status'],
    'highest_hint_level' => (int)$run['highest_hint_level'],
    'max_hint_level' => TRAINING_PROGRESS_MAX_HINT_LEVEL,
    'hints' => $hints,
  ];
}

function training_hint_start_run(int $userId, int $exerciseId, ?int $sessionId = null): array {
  $exercise = training_exercise_for_user($exerciseId, $userId);
  if (!$exercise) throw new RuntimeException('Ejercicio no encontrado.');
  if (empty($exercise['is_trainable'])) throw new RuntimeException('Este ejercicio todavía no está disponible para repetirlo.');

  $run = training_progress_start_solve_run($userId, $exerciseId, $sessionId);
  $feed = coach_messages_for_run($userId, (int)$run['id']);
  if (!$feed && !empty($run['session_id'])) {
    $objective = preg_replace('/\s*Juegan\s+(blancas|negras)\.?/iu', '', (string)($exercise['prompt'] ?? 'Encuentra la mejor jugada.'));
    coach_record_run_message($userId, (int)$run['id'], coach_message_payload(
      'selection', 'neutral', trim((string)$objective), ['exercise_type' => (string)$exercise['exercise_type']], (int)$exercise['ply']
    ));
    $feed = coach_messages_for_run($userId, (int)$run['id']);
  }
  return [
    'ok' => true,
    'solve_run' => training_hint_public_run($run, $exercise),
    'coach_feed' => $feed,
  ];
}

function training_hint_request(
  int $userId,
  int $exerciseId,
  int $runId,
  int $level,
  ?int $sessionId = null
): array {
  if ($level < 1 || $level > TRAINING_PROGRESS_MAX_HINT_LEVEL) {
    throw new InvalidArgumentException('Nivel de pista no válido.');
  }

  $exercise = training_exercise_for_user($exerciseId, $userId);
  if (!$exercise) throw new RuntimeException('Ejercicio no encontrado.');
  if (empty($exercise['is_trainable'])) throw new RuntimeException('Este ejercicio todavía no está disponible para repetirlo.');

  $run = $runId > 0 ? training_progress_run_for_user($runId, $userId) : null;
  if (!$run) $run = training_progress_start_solve_run($userId, $exerciseId, $sessionId);
  if ((int)$run['exercise_id'] !== $exerciseId || $run['status'] !== 'active') {
    throw new RuntimeException('La resolución no corresponde a este ejercicio.');
  }

  $currentLevel = (int)$run['highest_hint_level'];
  if (!training_hint_level_is_available($currentLevel, $level)) {
    throw new RuntimeException('Las pistas deben solicitarse en orden.');
  }
  $generated = training_hint_generate($exercise, $level);
  if (!$generated) throw new RuntimeException('No se ha podido generar una pista segura para este ejercicio.');

  $stored = training_progress_record_hint(
    $userId,
    (int)$run['id'],
    $level,
    (string)$generated['type'],
    (string)$generated['text'],
    TRAINING_HINT_GENERATOR_VERSION
  );
  $run = training_progress_run_for_user((int)$run['id'], $userId);
  if (!$run) throw new RuntimeException('No se ha podido recuperar la resolución.');

  if ($level > $currentLevel) {
    coach_record_run_message($userId, (int)$run['id'], coach_message_payload(
      'hint', 'thinking', (string)$stored['hint_text'], ['hint_level' => $level, 'hint_type' => (string)$stored['hint_type']], (int)$exercise['ply']
    ));
  }

  $hint = [
    'level' => (int)$stored['hint_level'],
    'type' => (string)$stored['hint_type'],
    'text' => (string)$stored['hint_text'],
    'highlight_squares' => $generated['highlight_squares'],
    'created_at' => $stored['created_at'],
  ];
  return [
    'ok' => true,
    'hint' => $hint,
    'solve_run' => training_hint_public_run($run, $exercise),
    'coach_feed' => coach_messages_for_run($userId, (int)$run['id']),
  ];
}

function training_coach_explanation(int $userId, int $exerciseId, int $runId, ?int $sessionId = null): array {
  $exercise = training_exercise_for_user($exerciseId, $userId);
  if (!$exercise) throw new RuntimeException('Ejercicio no encontrado.');
  $run = $runId > 0 ? training_progress_run_for_user($runId, $userId) : null;
  if (!$run) $run = training_progress_start_solve_run($userId, $exerciseId, $sessionId);
  if ((int)$run['exercise_id'] !== $exerciseId) throw new RuntimeException('La resolución no corresponde a este ejercicio.');
  $attemptSt = db()->prepare('SELECT * FROM training_attempts WHERE user_id=? AND solve_run_id=? ORDER BY id DESC LIMIT 1');
  $attemptSt->execute([$userId, (int)$run['id']]);
  $attempt = $attemptSt->fetch() ?: null;
  $text = coach_flash_explanation($exercise, $attempt);
  coach_record_run_message($userId, (int)$run['id'], coach_message_payload(
    'explanation', 'explaining', $text, ['does_not_consume_hint' => true], (int)$exercise['ply']
  ));
  return [
    'ok' => true,
    'explanation' => $text,
    'solve_run' => training_hint_public_run($run, $exercise),
    'coach_feed' => coach_messages_for_run($userId, (int)$run['id']),
  ];
}
