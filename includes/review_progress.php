<?php
require_once __DIR__ . '/db.php';

const REVIEW_PROGRESS_REQUIRED_PLIES = 17;

function review_progress_game(int $userId, int $gameId): ?array {
  $st = db()->prepare('SELECT g.id AS game_id,a.id AS analysis_id,
                              COALESCE(NULLIF(a.total_ply,0),(SELECT COUNT(*) FROM game_move_analysis WHERE analysis_id=a.id)) AS total_plies
                       FROM games g
                       JOIN game_analysis a ON a.id=(
                         SELECT id FROM game_analysis
                         WHERE game_id=g.id AND user_id=? AND status="done"
                         ORDER BY id DESC LIMIT 1
                       )
                       WHERE g.id=? AND g.user_id=? LIMIT 1');
  $st->execute([$userId, $gameId, $userId]);
  $row = $st->fetch();
  return $row ?: null;
}

function review_progress_sanitize_plies(array $plies, int $totalPlies): array {
  $clean = [];
  foreach ($plies as $ply) {
    $value = (int)$ply;
    if ($value >= 1 && $value <= $totalPlies) $clean[$value] = $value;
  }
  ksort($clean, SORT_NUMERIC);
  return array_values($clean);
}

function review_progress_required_count(int $totalPlies): int {
  return min(REVIEW_PROGRESS_REQUIRED_PLIES, max(0, $totalPlies));
}

function review_progress_public(array $row): array {
  $visited = json_decode((string)($row['visited_plies_json'] ?? '[]'), true);
  if (!is_array($visited)) $visited = [];
  $total = max(0, (int)($row['total_plies'] ?? 0));
  $required = review_progress_required_count($total);
  return [
    'game_id' => (int)$row['game_id'],
    'visited_plies' => review_progress_sanitize_plies($visited, $total),
    'visited_plies_count' => (int)($row['visited_plies_count'] ?? 0),
    'total_plies' => $total,
    'required_plies' => $required,
    'completed' => !empty($row['completed_at']),
    'completed_at' => $row['completed_at'] ?? null,
  ];
}

function review_progress_for_game(int $userId, int $gameId): ?array {
  $game = review_progress_game($userId, $gameId);
  if (!$game) return null;
  $st = db()->prepare('SELECT * FROM game_review_progress WHERE user_id=? AND game_id=? LIMIT 1');
  $st->execute([$userId, $gameId]);
  $row = $st->fetch();
  if (!$row) {
    $row = [
      'game_id' => $gameId,
      'visited_plies_json' => '[]',
      'visited_plies_count' => 0,
      'total_plies' => (int)$game['total_plies'],
      'completed_at' => null,
    ];
  }
  return review_progress_public($row);
}

function review_progress_record(int $userId, int $gameId, array $plies): array {
  $game = review_progress_game($userId, $gameId);
  if (!$game) throw new RuntimeException('Partida analizada no encontrada.');
  $totalPlies = max(0, (int)$game['total_plies']);
  $newPlies = review_progress_sanitize_plies($plies, $totalPlies);
  if (!$newPlies) {
    $current = review_progress_for_game($userId, $gameId);
    if (!$current) throw new RuntimeException('No se pudo cargar el progreso de revisión.');
    return $current;
  }

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare('SELECT * FROM game_review_progress WHERE user_id=? AND game_id=? LIMIT 1 FOR UPDATE');
    $st->execute([$userId, $gameId]);
    $row = $st->fetch();
    $visited = $row ? json_decode((string)$row['visited_plies_json'], true) : [];
    if (!is_array($visited)) $visited = [];
    $visited = review_progress_sanitize_plies(array_merge($visited, $newPlies), $totalPlies);
    $count = count($visited);
    $required = review_progress_required_count($totalPlies);
    $completed = $required > 0 && $count >= $required;
    $visitedJson = json_encode($visited, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $up = $pdo->prepare('INSERT INTO game_review_progress
        (user_id,game_id,visited_plies_json,visited_plies_count,total_plies,completed_at,created_at)
        VALUES (?,?,?,?,?,IF(?,NOW(),NULL),NOW())
        ON DUPLICATE KEY UPDATE
          visited_plies_json=VALUES(visited_plies_json),
          visited_plies_count=VALUES(visited_plies_count),
          total_plies=VALUES(total_plies),
          completed_at=IF(completed_at IS NOT NULL,completed_at,IF(VALUES(completed_at) IS NOT NULL,NOW(),NULL)),
          updated_at=NOW()');
    $up->execute([$userId, $gameId, $visitedJson, $count, $totalPlies, $completed ? 1 : 0]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  $progress = review_progress_for_game($userId, $gameId);
  if (!$progress) throw new RuntimeException('No se pudo recuperar el progreso de revisión.');
  return $progress;
}
