<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pgn.php';
require_once __DIR__ . '/chess_server.php';

function openings_profile_plies(): int {
  return 16;
}

function openings_lab_min_games(): int {
  return 3;
}

function openings_accuracy_from_acpl(float $acpl): float {
  if ($acpl <= 0) return 100.0;
  return round(max(0, min(100, 100 * exp(-$acpl / 220))), 1);
}

function openings_move_side(int $ply): string {
  return $ply % 2 === 1 ? 'white' : 'black';
}

function openings_fen_side_to_move(?string $fen): string {
  $parts = preg_split('/\s+/', trim((string)$fen));
  return ($parts[1] ?? 'w') === 'b' ? 'black' : 'white';
}

function openings_score_after_for_user(array $move, string $userColor): ?int {
  if (($move['score_after_type'] ?? 'cp') !== 'cp') return null;
  if ($userColor !== 'white' && $userColor !== 'black') return null;
  if ($move['score_after'] === null || $move['score_after'] === '') return null;

  $score = (int)$move['score_after'];
  $sideToMove = openings_fen_side_to_move($move['fen_after'] ?? '');
  $scoreForWhite = $sideToMove === 'white' ? $score : -$score;
  return $userColor === 'white' ? $scoreForWhite : -$scoreForWhite;
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

function openings_lab_game_rows(int $userId, ?string $openingKey = null): array {
  $where = ['op.user_id=?'];
  $params = [$userId];
  if ($openingKey !== null && $openingKey !== '') {
    $where[] = 'op.opening_key=?';
    $params[] = $openingKey;
  }

  $sql = 'SELECT op.id AS profile_id, op.game_id, op.analysis_id, op.user_color, op.opening_key,
                 op.display_name, op.eco_code, op.opening_name, op.eco_url, op.opening_source,
                 op.opening_signature, op.first_moves_san, op.first_moves_uci, op.plies_count,
                 g.white_player, g.black_player, g.user_result, g.played_at, g.imported_at,
                 g.event_name, g.site, a.status AS analysis_status
          FROM game_opening_profiles op
          JOIN games g ON g.id=op.game_id AND g.user_id=op.user_id
          LEFT JOIN game_analysis a ON a.id=op.analysis_id AND a.user_id=op.user_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC';
  $st = db()->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

function openings_lab_moves_by_analysis(array $analysisIds): array {
  $analysisIds = array_values(array_unique(array_filter(array_map('intval', $analysisIds))));
  if (!$analysisIds) return [];

  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $sql = 'SELECT analysis_id, ply, san, uci, fen_after, score_after, score_after_type,
                 centipawn_loss, classification
          FROM game_move_analysis
          WHERE analysis_id IN (' . $placeholders . ')
            AND ply <= 20
          ORDER BY analysis_id ASC, ply ASC';
  $st = db()->prepare($sql);
  $st->execute($analysisIds);

  $grouped = [];
  foreach ($st->fetchAll() as $move) {
    $analysisId = (int)$move['analysis_id'];
    $grouped[$analysisId][] = $move;
  }
  return $grouped;
}

function openings_lab_decode_moves(?string $json): array {
  if (!is_string($json) || trim($json) === '') return [];
  $decoded = json_decode($json, true);
  if (!is_array($decoded)) return [];
  return array_values(array_filter(array_map('strval', $decoded), fn($move) => trim($move) !== ''));
}

function openings_lab_game_metrics(array $row, array $moves): array {
  $userColor = (string)($row['user_color'] ?? 'unknown');
  $openingLimit = openings_profile_plies();
  $ownOpeningLosses = [];
  $earlyErrors = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];
  $evalAfterMove10 = null;
  $firstErrorPly = null;
  $firstErrorLabel = null;

  foreach ($moves as $move) {
    $ply = (int)($move['ply'] ?? 0);
    $isOwnMove = $userColor !== 'unknown' && openings_move_side($ply) === $userColor;

    if ($isOwnMove && $ply <= $openingLimit) {
      $ownOpeningLosses[] = min(max(0, (int)($move['centipawn_loss'] ?? 0)), 1000);
    }

    if ($isOwnMove && $ply <= 20) {
      $class = (string)($move['classification'] ?? 'ok');
      if (isset($earlyErrors[$class])) {
        $earlyErrors[$class]++;
        if ($firstErrorPly === null) {
          $firstErrorPly = $ply;
          $firstErrorLabel = $class;
        }
      }
    }

    if ($ply === 20) {
      $evalAfterMove10 = openings_score_after_for_user($move, $userColor);
    }
  }

  $acpl = $ownOpeningLosses ? round(array_sum($ownOpeningLosses) / count($ownOpeningLosses), 1) : null;
  $accuracy = $acpl === null ? null : openings_accuracy_from_acpl($acpl);

  return [
    'opening_acpl' => $acpl,
    'opening_accuracy' => $accuracy,
    'eval_after_move_10' => $evalAfterMove10,
    'opening_blunders' => $earlyErrors['blunder'],
    'opening_mistakes' => $earlyErrors['mistake'],
    'opening_inaccuracies' => $earlyErrors['inaccuracy'],
    'opening_error_count' => array_sum($earlyErrors),
    'first_error_ply' => $firstErrorPly,
    'first_error_label' => $firstErrorLabel,
  ];
}

function openings_lab_public_game(array $row, array $metrics): array {
  $white = trim((string)($row['white_player'] ?? ''));
  $black = trim((string)($row['black_player'] ?? ''));
  return [
    'game_id' => (int)$row['game_id'],
    'analysis_id' => $row['analysis_id'] === null ? null : (int)$row['analysis_id'],
    'title' => trim($white . ' vs ' . $black),
    'white_player' => $white,
    'black_player' => $black,
    'user_color' => (string)($row['user_color'] ?? 'unknown'),
    'result' => (string)($row['user_result'] ?? 'unknown'),
    'played_at' => $row['played_at'] ?: ($row['imported_at'] ? substr((string)$row['imported_at'], 0, 10) : null),
    'opening_accuracy' => $metrics['opening_accuracy'],
    'opening_acpl' => $metrics['opening_acpl'],
    'eval_after_move_10' => $metrics['eval_after_move_10'],
    'opening_errors' => [
      'blunders' => $metrics['opening_blunders'],
      'mistakes' => $metrics['opening_mistakes'],
      'inaccuracies' => $metrics['opening_inaccuracies'],
      'total' => $metrics['opening_error_count'],
    ],
    'first_error_ply' => $metrics['first_error_ply'],
    'first_error_label' => $metrics['first_error_label'],
    'review_url' => 'review.php?id=' . (int)$row['game_id'],
    'review_focus_url' => $metrics['first_error_ply'] === null
      ? 'review.php?id=' . (int)$row['game_id']
      : 'review.php?id=' . (int)$row['game_id'] . '&ply=' . (int)$metrics['first_error_ply'],
  ];
}

function openings_lab_recommended_games(array $games, int $limit = 5): array {
  $recommended = array_values(array_filter($games, fn($game) => (int)($game['opening_errors']['total'] ?? 0) > 0));
  usort($recommended, fn($a, $b) =>
    ((int)($b['opening_errors']['total'] ?? 0) <=> (int)($a['opening_errors']['total'] ?? 0))
    ?: ((float)($a['opening_accuracy'] ?? 101) <=> (float)($b['opening_accuracy'] ?? 101))
    ?: strcmp((string)($b['played_at'] ?? ''), (string)($a['played_at'] ?? ''))
  );
  return array_slice($recommended, 0, max(1, $limit));
}

function openings_lab_recommendation(array $opening): string {
  $games = (int)($opening['games'] ?? 0);
  if ($games < openings_lab_min_games()) return 'Aún faltan partidas para sacar una conclusión fiable.';
  if (($opening['opening_blunders'] ?? 0) > 0) return 'Revisa las primeras jugadas críticas y busca el patrón común antes de memorizar variantes.';
  if (($opening['opening_mistakes'] ?? 0) > 0) return 'Trabaja los planes típicos y comprueba dónde empieza a caer la evaluación.';
  if (($opening['score_rate'] ?? 0) >= 65) return 'Esta apertura está funcionando: conserva el repertorio y revisa partidas modelo propias.';
  if (($opening['avg_opening_accuracy'] ?? 100) < 75) return 'Prioriza principios básicos: desarrollo, rey seguro y control del centro.';
  return 'Mantener en observación: los resultados aún no muestran un problema claro.';
}

function openings_lab_common_issue(array $opening): ?array {
  $issues = [
    'blunder' => ['label' => 'Omisiones graves en apertura', 'count' => (int)($opening['opening_blunders'] ?? 0)],
    'mistake' => ['label' => 'Errores importantes en apertura', 'count' => (int)($opening['opening_mistakes'] ?? 0)],
    'inaccuracy' => ['label' => 'Imprecisiones repetidas en apertura', 'count' => (int)($opening['opening_inaccuracies'] ?? 0)],
  ];
  usort($issues, fn($a, $b) => $b['count'] <=> $a['count']);
  return $issues[0]['count'] > 0 ? $issues[0] : null;
}

function openings_lab_principle(array $opening): array {
  $games = (int)($opening['games'] ?? 0);
  if ($games < openings_lab_min_games()) {
    return [
      'code' => 'sample_size',
      'title' => 'Reune mas muestra',
      'summary' => 'Todavia hay pocas partidas para diagnosticar esta apertura con confianza.',
      'checklist' => ['Analiza mas partidas de esta linea', 'Compara al menos 3 partidas antes de cambiar repertorio'],
    ];
  }
  if ((int)($opening['opening_blunders'] ?? 0) > 0) {
    return [
      'code' => 'avoid_tactical_collapse',
      'title' => 'Evita el colapso tactico temprano',
      'summary' => 'Antes de memorizar variantes, localiza que amenaza o pieza queda sin defender en las primeras jugadas.',
      'checklist' => ['Revisa capturas y amenazas del rival', 'Comprueba piezas indefensas antes de mover', 'No aceleres el plan si el rey sigue expuesto'],
    ];
  }
  if ((int)($opening['opening_mistakes'] ?? 0) > 0) {
    return [
      'code' => 'typical_plan',
      'title' => 'Entiende el plan tipico',
      'summary' => 'La apertura no pide mas memoria, pide reconocer donde empieza a cambiar la evaluacion.',
      'checklist' => ['Desarrolla piezas antes de buscar ataques', 'Identifica el mejor plan para el centro', 'Revisa la primera jugada donde cae la precision'],
    ];
  }
  if ((float)($opening['avg_opening_accuracy'] ?? 100) < 75) {
    return [
      'code' => 'opening_principles',
      'title' => 'Refuerza principios basicos',
      'summary' => 'Tu precision baja sin un unico error claro: vuelve a desarrollo, rey seguro y centro.',
      'checklist' => ['Saca piezas menores pronto', 'Evita mover la misma pieza varias veces', 'Enroca o asegura el rey cuando la posicion lo pida'],
    ];
  }
  if ((int)($opening['score_rate'] ?? 0) >= 65) {
    return [
      'code' => 'keep_and_model',
      'title' => 'Conserva esta estructura',
      'summary' => 'Esta apertura esta funcionando. Usa tus mejores partidas como modelo practico.',
      'checklist' => ['Revisa la mejor partida de ejemplo', 'Detecta que plan se repite cuando ganas', 'Mantén la linea mientras siga dando posiciones jugables'],
    ];
  }
  return [
    'code' => 'observe',
    'title' => 'Observa el patron',
    'summary' => 'No hay una senal dominante. Sigue comparando partidas y revisa los momentos tempranos recomendados.',
    'checklist' => ['Busca errores repetidos antes de cambiar la linea', 'Compara partidas ganadas y perdidas', 'Prioriza posiciones que entiendas'],
  ];
}

function openings_lab_build_openings(array $rows, array $movesByAnalysis): array {
  $openings = [];

  foreach ($rows as $row) {
    $analysisId = (int)($row['analysis_id'] ?? 0);
    $metrics = openings_lab_game_metrics($row, $analysisId > 0 ? ($movesByAnalysis[$analysisId] ?? []) : []);
    $key = (string)$row['opening_key'];

    if (!isset($openings[$key])) {
      $openings[$key] = [
        'opening_key' => $key,
        'display_name' => (string)$row['display_name'],
        'eco_code' => $row['eco_code'],
        'opening_name' => $row['opening_name'],
        'eco_url' => $row['eco_url'],
        'opening_source' => (string)$row['opening_source'],
        'opening_signature' => $row['opening_signature'],
        'first_moves_san' => openings_lab_decode_moves($row['first_moves_san'] ?? null),
        'first_moves_uci' => openings_lab_decode_moves($row['first_moves_uci'] ?? null),
        'plies_count' => (int)($row['plies_count'] ?? 0),
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'unknown_results' => 0,
        'white_games' => 0,
        'black_games' => 0,
        'opening_blunders' => 0,
        'opening_mistakes' => 0,
        'opening_inaccuracies' => 0,
        'opening_error_count' => 0,
        'accuracy_sum' => 0.0,
        'accuracy_games' => 0,
        'acpl_sum' => 0.0,
        'eval_sum' => 0,
        'eval_games' => 0,
        'recent_games' => [],
        'best_game' => null,
        'worst_game' => null,
      ];
    }

    $game = openings_lab_public_game($row, $metrics);
    $result = (string)($row['user_result'] ?? 'unknown');
    $openings[$key]['games']++;
    if ($result === 'win') $openings[$key]['wins']++;
    elseif ($result === 'draw') $openings[$key]['draws']++;
    elseif ($result === 'loss') $openings[$key]['losses']++;
    else $openings[$key]['unknown_results']++;

    if (($row['user_color'] ?? '') === 'white') $openings[$key]['white_games']++;
    if (($row['user_color'] ?? '') === 'black') $openings[$key]['black_games']++;

    $openings[$key]['opening_blunders'] += $metrics['opening_blunders'];
    $openings[$key]['opening_mistakes'] += $metrics['opening_mistakes'];
    $openings[$key]['opening_inaccuracies'] += $metrics['opening_inaccuracies'];
    $openings[$key]['opening_error_count'] += $metrics['opening_error_count'];

    if ($metrics['opening_accuracy'] !== null) {
      $openings[$key]['accuracy_sum'] += (float)$metrics['opening_accuracy'];
      $openings[$key]['accuracy_games']++;
    }
    if ($metrics['opening_acpl'] !== null) {
      $openings[$key]['acpl_sum'] += (float)$metrics['opening_acpl'];
    }
    if ($metrics['eval_after_move_10'] !== null) {
      $openings[$key]['eval_sum'] += (int)$metrics['eval_after_move_10'];
      $openings[$key]['eval_games']++;
    }

    if (count($openings[$key]['recent_games']) < 5) $openings[$key]['recent_games'][] = $game;
    if ($metrics['opening_accuracy'] !== null) {
      if ($openings[$key]['best_game'] === null || $metrics['opening_accuracy'] > $openings[$key]['best_game']['opening_accuracy']) {
        $openings[$key]['best_game'] = $game;
      }
      if ($openings[$key]['worst_game'] === null || $metrics['opening_accuracy'] < $openings[$key]['worst_game']['opening_accuracy']) {
        $openings[$key]['worst_game'] = $game;
      }
    }
  }

  foreach ($openings as &$opening) {
    $games = max(1, (int)$opening['games']);
    $opening['score_rate'] = round(((int)$opening['wins'] + ((int)$opening['draws'] * 0.5)) / $games * 100);
    $opening['avg_opening_accuracy'] = $opening['accuracy_games'] ? round($opening['accuracy_sum'] / $opening['accuracy_games'], 1) : null;
    $opening['avg_opening_acpl'] = $opening['accuracy_games'] ? round($opening['acpl_sum'] / $opening['accuracy_games'], 1) : null;
    $opening['avg_eval_after_move_10'] = $opening['eval_games'] ? round($opening['eval_sum'] / $opening['eval_games']) : null;
    $opening['common_issue'] = openings_lab_common_issue($opening);
    $opening['recommendation'] = openings_lab_recommendation($opening);
    $opening['recommended_principle'] = openings_lab_principle($opening);
    unset($opening['accuracy_sum'], $opening['accuracy_games'], $opening['acpl_sum'], $opening['eval_sum'], $opening['eval_games']);
  }
  unset($opening);

  $items = array_values($openings);
  usort($items, fn($a, $b) =>
    ((int)$b['games'] <=> (int)$a['games'])
    ?: ((int)$b['opening_error_count'] <=> (int)$a['opening_error_count'])
    ?: strcmp((string)$a['display_name'], (string)$b['display_name'])
  );
  return $items;
}

function openings_lab_summary(array $openings, int $pendingProfiles): array {
  $totalGames = array_sum(array_map(fn($opening) => (int)$opening['games'], $openings));
  $readyOpenings = array_values(array_filter($openings, fn($opening) => (int)$opening['games'] >= openings_lab_min_games()));
  $best = $readyOpenings;
  usort($best, fn($a, $b) =>
    ((int)$b['score_rate'] <=> (int)$a['score_rate'])
    ?: ((float)($b['avg_opening_accuracy'] ?? -1) <=> (float)($a['avg_opening_accuracy'] ?? -1))
  );
  $worst = $readyOpenings;
  usort($worst, fn($a, $b) =>
    ((int)$b['opening_error_count'] <=> (int)$a['opening_error_count'])
    ?: ((int)$a['score_rate'] <=> (int)$b['score_rate'])
  );

  return [
    'total_profiled_games' => $totalGames,
    'total_openings' => count($openings),
    'openings_with_minimum_games' => count($readyOpenings),
    'minimum_games' => openings_lab_min_games(),
    'profile_plies' => openings_profile_plies(),
    'pending_profiles' => $pendingProfiles,
    'best_opening' => $best[0] ?? null,
    'main_issue_opening' => $worst[0] ?? null,
  ];
}

function openings_lab_frequent_tags(int $userId, array $gameIds, array $analysisIds, int $limit = 8): array {
  $gameIds = array_values(array_unique(array_filter(array_map('intval', $gameIds))));
  $analysisIds = array_values(array_unique(array_filter(array_map('intval', $analysisIds))));
  if (!$gameIds || !$analysisIds) return [];

  $gamePlaceholders = implode(',', array_fill(0, count($gameIds), '?'));
  $analysisPlaceholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $params = array_merge([$userId], $gameIds, $analysisIds, [$userId], $gameIds, $analysisIds);
  $sql = "SELECT tag_code, label, category, severity, SUM(total) AS total
          FROM (
            SELECT gt.tag_code, d.label, d.category, d.severity, COUNT(*) AS total
            FROM game_tags gt
            JOIN smart_tag_definitions d ON d.code=gt.tag_code
            WHERE gt.user_id=?
              AND gt.game_id IN ($gamePlaceholders)
              AND gt.analysis_id IN ($analysisPlaceholders)
            GROUP BY gt.tag_code, d.label, d.category, d.severity
            UNION ALL
            SELECT mt.tag_code, d.label, d.category, d.severity, COUNT(*) AS total
            FROM move_tags mt
            JOIN smart_tag_definitions d ON d.code=mt.tag_code
            WHERE mt.user_id=?
              AND mt.game_id IN ($gamePlaceholders)
              AND mt.analysis_id IN ($analysisPlaceholders)
              AND mt.ply <= 20
            GROUP BY mt.tag_code, d.label, d.category, d.severity
          ) tag_counts
          GROUP BY tag_code, label, category, severity
          ORDER BY total DESC, severity ASC, label ASC
          LIMIT " . (int)max(1, min(20, $limit));
  $st = db()->prepare($sql);
  $st->execute($params);
  return array_map(fn($tag) => [
    'tag_code' => (string)$tag['tag_code'],
    'label' => (string)$tag['label'],
    'category' => (string)$tag['category'],
    'severity' => (string)$tag['severity'],
    'total' => (int)$tag['total'],
  ], $st->fetchAll());
}

function openings_lab_early_error_patterns(array $rows, array $movesByAnalysis, int $limit = 5): array {
  $patterns = [];
  foreach ($rows as $row) {
    $analysisId = (int)($row['analysis_id'] ?? 0);
    $userColor = (string)($row['user_color'] ?? 'unknown');
    if ($analysisId <= 0 || ($userColor !== 'white' && $userColor !== 'black')) continue;
    foreach ($movesByAnalysis[$analysisId] ?? [] as $move) {
      $ply = (int)($move['ply'] ?? 0);
      $class = (string)($move['classification'] ?? 'ok');
      if ($ply <= 0 || $ply > 20 || !in_array($class, ['blunder', 'mistake', 'inaccuracy'], true)) continue;
      if (openings_move_side($ply) !== $userColor) continue;
      $key = $ply . '|' . $class;
      if (!isset($patterns[$key])) {
        $patterns[$key] = [
          'ply' => $ply,
          'classification' => $class,
          'count' => 0,
          'sample_san' => (string)($move['san'] ?? ''),
          'sample_uci' => (string)($move['uci'] ?? ''),
          'review_url' => 'review.php?id=' . (int)$row['game_id'] . '&ply=' . $ply,
        ];
      }
      $patterns[$key]['count']++;
    }
  }

  $items = array_values($patterns);
  usort($items, fn($a, $b) =>
    ((int)$b['count'] <=> (int)$a['count'])
    ?: ((int)$a['ply'] <=> (int)$b['ply'])
    ?: strcmp((string)$a['classification'], (string)$b['classification'])
  );
  return array_slice($items, 0, max(1, $limit));
}

function openings_lab_training_type_label(string $type): string {
  return [
    'find_best_move' => 'Encontrar mejor jugada',
    'avoid_blunder' => 'Evitar omision',
    'find_mate' => 'Encontrar mate',
    'spot_threat' => 'Detectar amenaza',
    'find_tactic' => 'Encontrar tactica',
    'defend_position' => 'Defender posicion',
    'convert_advantage' => 'Convertir ventaja',
    'other' => 'Otros',
  ][$type] ?? $type;
}

function openings_lab_related_exercises(int $userId, ?string $ecoCode, int $limit = 6): array {
  $ecoCode = trim((string)$ecoCode);
  if ($ecoCode === '') return [];

  $sql = 'SELECT te.id, te.game_id, te.ply, te.exercise_type, te.difficulty, te.priority_score,
                 te.resolved_at, te.last_attempt_at, g.white_player, g.black_player, g.played_at
          FROM training_exercises te
          JOIN game_opening_profiles op ON op.game_id=te.game_id AND op.user_id=te.user_id
          JOIN games g ON g.id=te.game_id AND g.user_id=te.user_id
          WHERE te.user_id=?
            AND te.status="active"
            AND te.ply <= 16
            AND op.eco_code=?
          ORDER BY te.resolved_at IS NOT NULL ASC, te.priority_score DESC, te.created_at DESC, te.id DESC
          LIMIT ' . (int)max(1, min(20, $limit));
  $st = db()->prepare($sql);
  $st->execute([$userId, $ecoCode]);
  return array_map(fn($row) => [
    'id' => (int)$row['id'],
    'game_id' => (int)$row['game_id'],
    'ply' => (int)$row['ply'],
    'exercise_type' => (string)$row['exercise_type'],
    'type_label' => openings_lab_training_type_label((string)$row['exercise_type']),
    'difficulty' => (string)($row['difficulty'] ?? ''),
    'priority_score' => (int)($row['priority_score'] ?? 0),
    'resolved' => !empty($row['resolved_at']),
    'title' => trim((string)($row['white_player'] ?? '') . ' vs ' . (string)($row['black_player'] ?? '')),
    'played_at' => $row['played_at'] ?: null,
    'training_url' => 'training.php?exercise_id=' . (int)$row['id'],
    'review_url' => 'review.php?id=' . (int)$row['game_id'] . '&ply=' . (int)$row['ply'],
  ], $st->fetchAll());
}

function openings_lab_payload(int $userId, int $limit = 50, int $minGames = 1): array {
  $limit = max(1, min(100, $limit));
  $minGames = max(1, min(50, $minGames));
  $rows = openings_lab_game_rows($userId);
  $movesByAnalysis = openings_lab_moves_by_analysis(array_column($rows, 'analysis_id'));
  $openings = openings_lab_build_openings($rows, $movesByAnalysis);
  $summary = openings_lab_summary($openings, openings_profile_pending_count($userId));
  $filtered = array_values(array_filter($openings, fn($opening) => (int)$opening['games'] >= $minGames));

  return [
    'ok' => true,
    'summary' => $summary,
    'openings' => array_slice($filtered, 0, $limit),
    'filters' => [
      'minimum_games' => $minGames,
      'recommended_minimum_games' => openings_lab_min_games(),
      'limit' => $limit,
    ],
  ];
}

function openings_lab_detail_payload(int $userId, string $openingKey): array {
  $openingKey = trim($openingKey);
  if ($openingKey === '') return ['ok' => false, 'error' => 'Apertura no indicada.'];

  $rows = openings_lab_game_rows($userId, $openingKey);
  if (!$rows) return ['ok' => false, 'error' => 'Apertura no encontrada.'];

  $movesByAnalysis = openings_lab_moves_by_analysis(array_column($rows, 'analysis_id'));
  $openings = openings_lab_build_openings($rows, $movesByAnalysis);
  $opening = $openings[0] ?? null;
  if (!$opening) return ['ok' => false, 'error' => 'Apertura no encontrada.'];

  $games = [];
  foreach ($rows as $row) {
    $analysisId = (int)($row['analysis_id'] ?? 0);
    $metrics = openings_lab_game_metrics($row, $analysisId > 0 ? ($movesByAnalysis[$analysisId] ?? []) : []);
    $games[] = openings_lab_public_game($row, $metrics);
  }
  $gameIds = array_column($rows, 'game_id');
  $analysisIds = array_column($rows, 'analysis_id');

  return [
    'ok' => true,
    'opening' => $opening,
    'games' => $games,
    'recommended_games' => openings_lab_recommended_games($games),
    'frequent_tags' => openings_lab_frequent_tags($userId, $gameIds, $analysisIds, 5),
    'early_error_patterns' => openings_lab_early_error_patterns($rows, $movesByAnalysis),
    'related_exercises' => openings_lab_related_exercises($userId, $opening['eco_code'] ?? null),
    'games_url' => 'games.php?opening_key=' . rawurlencode($openingKey),
    'minimum_games' => openings_lab_min_games(),
  ];
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
