<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pgn.php';
require_once __DIR__ . '/chess_server.php';

function openings_profile_plies(): int {
  return 16;
}

function openings_user_color(array $game, string $username): string {
  $user = strtolower(trim($username));
  if ($user !== '' && $user === strtolower(trim((string)($game['white_player'] ?? '')))) return 'white';
  if ($user !== '' && $user === strtolower(trim((string)($game['black_player'] ?? '')))) return 'black';
  return 'unknown';
}

function openings_normalize_key_part(string $value): string {
  $value = strtolower(trim($value));
  $value = preg_replace('/\s+/', ' ', $value) ?? $value;
  return substr($value, 0, 255);
}

function openings_json(array $value): ?string {
  if (!$value) return null;
  return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function openings_first_moves_from_analysis(int $analysisId, int $limit): array {
  $st = db()->prepare('SELECT san, uci FROM game_move_analysis WHERE analysis_id=? ORDER BY ply ASC LIMIT ' . (int)$limit);
  $st->execute([$analysisId]);
  return array_map(fn($move) => [
    'san' => (string)($move['san'] ?? ''),
    'uci' => strtolower((string)($move['uci'] ?? '')),
  ], $st->fetchAll());
}

function openings_first_moves_from_pgn(string $pgn, int $limit): array {
  if (trim($pgn) === '') return [];
  try {
    $positions = pgn_to_uci_positions($pgn);
  } catch (Throwable $e) {
    return [];
  }
  $positions = array_slice($positions, 0, $limit);
  return array_map(fn($move) => [
    'san' => (string)($move['san'] ?? ''),
    'uci' => strtolower((string)($move['uci'] ?? '')),
  ], $positions);
}

function openings_signature(array $moves): ?string {
  if (!$moves) return null;
  $uci = array_values(array_filter(array_map(fn($move) => trim((string)($move['uci'] ?? '')), $moves)));
  if ($uci) return substr(implode(' ', $uci), 0, 255);
  $san = array_values(array_filter(array_map(fn($move) => openings_normalize_key_part((string)($move['san'] ?? '')), $moves)));
  return $san ? substr(implode(' ', $san), 0, 255) : null;
}

function openings_display_name(?string $ecoCode, ?string $openingName, ?string $signature, string $userColor): string {
  $ecoCode = trim((string)$ecoCode);
  $openingName = trim((string)$openingName);
  if ($openingName !== '' && $ecoCode !== '') return $openingName . ' (' . $ecoCode . ')';
  if ($openingName !== '') return $openingName;
  if ($ecoCode !== '') return $ecoCode;
  if ($signature !== null && $signature !== '') return 'Linea por secuencia';
  return $userColor === 'black' ? 'Defensa no identificada' : 'Apertura no identificada';
}

function openings_source(?string $ecoCode, ?string $openingName, ?string $signature): string {
  if (trim((string)$openingName) !== '') return 'pgn';
  if (trim((string)$ecoCode) !== '') return 'eco';
  if ($signature !== null && $signature !== '') return 'signature';
  return 'unknown';
}

function openings_key(?string $ecoCode, ?string $openingName, ?string $signature): string {
  $eco = openings_normalize_key_part((string)$ecoCode);
  $opening = openings_normalize_key_part((string)$openingName);
  if ($eco !== '' || $opening !== '') return 'eco_name|' . $eco . '|' . $opening;
  if ($signature !== null && $signature !== '') return 'signature|' . openings_normalize_key_part($signature);
  return 'unknown';
}

function openings_game_context(int $gameId, int $userId): ?array {
  $sql = 'SELECT g.*, u.username, a.id AS latest_analysis_id
          FROM games g
          JOIN users u ON u.id=g.user_id
          LEFT JOIN game_analysis a ON a.id=(
            SELECT id
            FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC
            LIMIT 1
          )
          WHERE g.id=? AND g.user_id=?
          LIMIT 1';
  $st = db()->prepare($sql);
  $st->execute([$userId, $gameId, $userId]);
  $row = $st->fetch();
  return $row ?: null;
}

function openings_analysis_context(int $analysisId, int $userId): ?array {
  $sql = 'SELECT g.*, u.username, a.id AS latest_analysis_id
          FROM game_analysis a
          JOIN games g ON g.id=a.game_id
          JOIN users u ON u.id=a.user_id
          WHERE a.id=? AND a.user_id=?
          LIMIT 1';
  $st = db()->prepare($sql);
  $st->execute([$analysisId, $userId]);
  $row = $st->fetch();
  return $row ?: null;
}

function openings_profile_payload(array $game): array {
  $limit = openings_profile_plies();
  $analysisId = !empty($game['latest_analysis_id']) ? (int)$game['latest_analysis_id'] : null;
  $moves = $analysisId
    ? openings_first_moves_from_analysis($analysisId, $limit)
    : openings_first_moves_from_pgn((string)($game['pgn'] ?? ''), $limit);
  $signature = openings_signature($moves);
  $ecoCode = pgn_eco_code((string)($game['pgn'] ?? '')) ?: ($game['eco_code'] ?? null);
  $openingName = pgn_opening_name((string)($game['pgn'] ?? '')) ?: ($game['opening_name'] ?? null);
  $ecoUrl = pgn_eco_url((string)($game['pgn'] ?? '')) ?: ($game['eco_url'] ?? null);
  $userColor = openings_user_color($game, (string)($game['username'] ?? ''));

  return [
    'user_id' => (int)$game['user_id'],
    'game_id' => (int)$game['id'],
    'analysis_id' => $analysisId,
    'user_color' => $userColor,
    'opening_key' => openings_key($ecoCode, $openingName, $signature),
    'display_name' => openings_display_name($ecoCode, $openingName, $signature, $userColor),
    'eco_code' => $ecoCode ? substr((string)$ecoCode, 0, 10) : null,
    'opening_name' => $openingName ? substr((string)$openingName, 0, 255) : null,
    'eco_url' => $ecoUrl ? substr((string)$ecoUrl, 0, 500) : null,
    'opening_source' => openings_source($ecoCode, $openingName, $signature),
    'opening_signature' => $signature,
    'first_moves_san' => openings_json(array_map(fn($move) => (string)($move['san'] ?? ''), $moves)),
    'first_moves_uci' => openings_json(array_map(fn($move) => (string)($move['uci'] ?? ''), $moves)),
    'plies_count' => count($moves),
  ];
}

function openings_upsert_profile(array $profile): void {
  $sql = 'INSERT INTO game_opening_profiles
            (user_id,game_id,analysis_id,user_color,opening_key,display_name,eco_code,opening_name,eco_url,
             opening_source,opening_signature,first_moves_san,first_moves_uci,plies_count,created_at,updated_at)
          VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
          ON DUPLICATE KEY UPDATE
            analysis_id=VALUES(analysis_id),
            user_color=VALUES(user_color),
            opening_key=VALUES(opening_key),
            display_name=VALUES(display_name),
            eco_code=VALUES(eco_code),
            opening_name=VALUES(opening_name),
            eco_url=VALUES(eco_url),
            opening_source=VALUES(opening_source),
            opening_signature=VALUES(opening_signature),
            first_moves_san=VALUES(first_moves_san),
            first_moves_uci=VALUES(first_moves_uci),
            plies_count=VALUES(plies_count),
            updated_at=NOW()';
  $st = db()->prepare($sql);
  $st->execute([
    $profile['user_id'],
    $profile['game_id'],
    $profile['analysis_id'],
    $profile['user_color'],
    $profile['opening_key'],
    $profile['display_name'],
    $profile['eco_code'],
    $profile['opening_name'],
    $profile['eco_url'],
    $profile['opening_source'],
    $profile['opening_signature'],
    $profile['first_moves_san'],
    $profile['first_moves_uci'],
    $profile['plies_count'],
  ]);
}

function openings_refresh_game_profile(int $gameId, int $userId): array {
  $game = openings_game_context($gameId, $userId);
  if (!$game) return ['ok' => false, 'error' => 'Partida no encontrada.'];
  $profile = openings_profile_payload($game);
  openings_upsert_profile($profile);

  return ['ok' => true, 'profile' => $profile];
}

function openings_refresh_analysis_profile(int $analysisId, int $userId): array {
  $game = openings_analysis_context($analysisId, $userId);
  if (!$game) return ['ok' => false, 'error' => 'Analisis no encontrado.'];
  $profile = openings_profile_payload($game);
  openings_upsert_profile($profile);
  return ['ok' => true, 'profile' => $profile];
}

function openings_profile_pending_count(int $userId): int {
  $sql = 'SELECT COUNT(*)
          FROM games g
          LEFT JOIN game_opening_profiles op ON op.game_id=g.id AND op.user_id=g.user_id
          LEFT JOIN game_analysis a ON a.id=(
            SELECT id
            FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC
            LIMIT 1
          )
          WHERE g.user_id=?
            AND (
              op.id IS NULL
              OR (a.id IS NOT NULL AND (op.analysis_id IS NULL OR op.analysis_id<>a.id))
            )';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return (int)$st->fetchColumn();
}

function openings_backfill_batch(int $userId, int $limit = 25, string $trigger = 'profile-page'): array {
  $limit = max(1, min(100, $limit));
  $started = microtime(true);
  $pendingBefore = openings_profile_pending_count($userId);
  $runId = null;

  try {
    $run = db()->prepare('INSERT INTO opening_profile_runs (user_id,trigger_source,status,started_at) VALUES (?,?,"running",NOW())');
    $run->execute([$userId, $trigger]);
    $runId = (int)db()->lastInsertId();
  } catch (Throwable $e) {
    $runId = null;
  }

  $sql = 'SELECT g.id
          FROM games g
          LEFT JOIN game_opening_profiles op ON op.game_id=g.id AND op.user_id=g.user_id
          LEFT JOIN game_analysis a ON a.id=(
            SELECT id
            FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC
            LIMIT 1
          )
          WHERE g.user_id=?
            AND (
              op.id IS NULL
              OR (a.id IS NOT NULL AND (op.analysis_id IS NULL OR op.analysis_id<>a.id))
            )
          ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  $gameIds = array_map('intval', array_column($st->fetchAll(), 'id'));

  $processed = 0;
  $updated = 0;
  $errors = [];
  foreach ($gameIds as $gameId) {
    try {
      $result = openings_refresh_game_profile($gameId, $userId);
      $processed++;
      if (!empty($result['ok'])) $updated++;
      elseif (!empty($result['error'])) $errors[] = $result['error'];
    } catch (Throwable $e) {
      $processed++;
      $errors[] = public_error_message($e);
    }
  }

  $pendingAfter = openings_profile_pending_count($userId);
  $durationMs = (int)round((microtime(true) - $started) * 1000);
  $message = $processed === 0
    ? 'No hay perfiles de apertura pendientes.'
    : 'Backfill de perfiles de apertura ejecutado correctamente.';
  if ($errors) $message = 'Backfill de perfiles de apertura completado con errores parciales.';

  if ($runId) {
    try {
      $up = db()->prepare('UPDATE opening_profile_runs
                           SET status=?, processed_games=?, updated_profiles=?, error_count=?,
                               duration_ms=?, message=?, error_message=?, completed_at=NOW()
                           WHERE id=?');
      $up->execute([
        $errors ? 'error' : 'done',
        $processed,
        $updated,
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
    'processed_games' => $processed,
    'updated_profiles' => $updated,
    'error_count' => count($errors),
    'pending_before' => $pendingBefore,
    'pending_after' => $pendingAfter,
    'duration_ms' => $durationMs,
    'message' => $message,
    'errors' => $errors,
  ];
}
