<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/analysis_queue.php';

function dashboard_accuracy_from_acpl(float $acpl): float {
  if ($acpl <= 0) return 100.0;
  return round(max(0, min(100, 100 * exp(-$acpl / 220))), 1);
}

function dashboard_user_side(array $game, string $username): ?string {
  $user = strtolower(trim($username));
  if ($user === '') return null;
  if ($user === strtolower(trim((string)($game['white_player'] ?? '')))) return 'w';
  if ($user === strtolower(trim((string)($game['black_player'] ?? '')))) return 'b';
  return null;
}

function dashboard_move_side(int $ply): string {
  return $ply % 2 === 1 ? 'w' : 'b';
}

function dashboard_latest_analyzed_games(int $userId, int $limit = 10): array {
  $limit = max(1, min(30, $limit));
  $sql = 'SELECT g.id AS game_id, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at,
                 g.imported_at, g.event_name, g.site, g.source,
                 a.id AS analysis_id, a.completed_at, a.created_at AS analysis_created_at
          FROM games g
          JOIN game_analysis a ON a.id=(
            SELECT id
            FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC
            LIMIT 1
          )
          WHERE g.user_id=?
          ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return $st->fetchAll();
}

function dashboard_analyzed_game_ids(int $userId, int $limit = 20): array {
  $limit = max(1, min(60, $limit));
  $sql = 'SELECT a.id AS analysis_id
          FROM games g
          JOIN game_analysis a ON a.id=(
            SELECT id
            FROM game_analysis
            WHERE game_id=g.id AND user_id=? AND status="done"
            ORDER BY id DESC
            LIMIT 1
          )
          WHERE g.user_id=?
          ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return array_map('intval', array_column($st->fetchAll(), 'analysis_id'));
}

function dashboard_metrics_for_games(array $games, string $username): array {
  if (!$games) {
    return [
      'games' => [],
      'summary' => dashboard_empty_period_summary(),
    ];
  }

  $analysisIds = array_map('intval', array_column($games, 'analysis_id'));
  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $movesSql = "SELECT analysis_id, ply, centipawn_loss, classification
               FROM game_move_analysis
               WHERE analysis_id IN ($placeholders)
               ORDER BY analysis_id, ply";
  $st = db()->prepare($movesSql);
  $st->execute($analysisIds);

  $movesByAnalysis = [];
  foreach ($st->fetchAll() as $move) {
    $movesByAnalysis[(int)$move['analysis_id']][] = $move;
  }

  $items = [];
  $totals = [
    'wins' => 0,
    'losses' => 0,
    'draws' => 0,
    'accuracy_sum' => 0.0,
    'acpl_sum' => 0.0,
    'own_blunders' => 0,
    'own_mistakes' => 0,
    'own_inaccuracies' => 0,
    'white_games' => 0,
    'black_games' => 0,
    'white_score' => 0.0,
    'black_score' => 0.0,
    'metric_games' => 0,
  ];

  foreach ($games as $game) {
    $analysisId = (int)$game['analysis_id'];
    $moves = $movesByAnalysis[$analysisId] ?? [];
    $side = dashboard_user_side($game, $username);
    $ownLosses = [];
    $ownCounts = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];

    foreach ($moves as $move) {
      $ply = (int)($move['ply'] ?? 0);
      if ($side !== null && dashboard_move_side($ply) !== $side) continue;
      $loss = min(max(0, (int)($move['centipawn_loss'] ?? 0)), 1000);
      $ownLosses[] = $loss;
      $classification = (string)($move['classification'] ?? 'ok');
      if (isset($ownCounts[$classification])) $ownCounts[$classification]++;
    }

    if (!$ownLosses) {
      foreach ($moves as $move) {
        $ownLosses[] = min(max(0, (int)($move['centipawn_loss'] ?? 0)), 1000);
      }
    }

    $acpl = $ownLosses ? round(array_sum($ownLosses) / count($ownLosses), 1) : null;
    $accuracy = $acpl === null ? null : dashboard_accuracy_from_acpl($acpl);
    $result = (string)($game['user_result'] ?? 'unknown');
    if ($result === 'win') $totals['wins']++;
    elseif ($result === 'loss') $totals['losses']++;
    elseif ($result === 'draw') $totals['draws']++;

    $score = $result === 'win' ? 1.0 : ($result === 'draw' ? 0.5 : 0.0);
    if ($side === 'w') {
      $totals['white_games']++;
      $totals['white_score'] += $score;
    } elseif ($side === 'b') {
      $totals['black_games']++;
      $totals['black_score'] += $score;
    }

    if ($accuracy !== null && $acpl !== null) {
      $totals['accuracy_sum'] += $accuracy;
      $totals['acpl_sum'] += $acpl;
      $totals['metric_games']++;
    }
    $totals['own_blunders'] += $ownCounts['blunder'];
    $totals['own_mistakes'] += $ownCounts['mistake'];
    $totals['own_inaccuracies'] += $ownCounts['inaccuracy'];

    $items[] = [
      'game_id' => (int)$game['game_id'],
      'analysis_id' => $analysisId,
      'white_player' => $game['white_player'],
      'black_player' => $game['black_player'],
      'result_raw' => $game['result_raw'],
      'user_result' => $result,
      'played_at' => $game['played_at'],
      'imported_at' => $game['imported_at'],
      'event_name' => $game['event_name'],
      'site' => $game['site'],
      'user_side' => $side,
      'accuracy' => $accuracy,
      'acpl' => $acpl,
      'own_blunders' => $ownCounts['blunder'],
      'own_mistakes' => $ownCounts['mistake'],
      'own_inaccuracies' => $ownCounts['inaccuracy'],
      'review_url' => 'review.php?id=' . (int)$game['game_id'],
    ];
  }

  $count = count($items);
  $summary = [
    'games' => $count,
    'wins' => $totals['wins'],
    'losses' => $totals['losses'],
    'draws' => $totals['draws'],
    'win_rate' => $count ? round(($totals['wins'] / $count) * 100) : 0,
    'score_rate' => $count ? round((($totals['wins'] + $totals['draws'] * 0.5) / $count) * 100) : 0,
    'avg_accuracy' => $totals['metric_games'] ? round($totals['accuracy_sum'] / $totals['metric_games'], 1) : null,
    'avg_acpl' => $totals['metric_games'] ? round($totals['acpl_sum'] / $totals['metric_games'], 1) : null,
    'own_blunders' => $totals['own_blunders'],
    'own_mistakes' => $totals['own_mistakes'],
    'own_inaccuracies' => $totals['own_inaccuracies'],
    'own_blunders_per_game' => $count ? round($totals['own_blunders'] / $count, 2) : 0,
    'own_mistakes_per_game' => $count ? round($totals['own_mistakes'] / $count, 2) : 0,
    'own_inaccuracies_per_game' => $count ? round($totals['own_inaccuracies'] / $count, 2) : 0,
    'white' => [
      'games' => $totals['white_games'],
      'score_rate' => $totals['white_games'] ? round(($totals['white_score'] / $totals['white_games']) * 100) : null,
    ],
    'black' => [
      'games' => $totals['black_games'],
      'score_rate' => $totals['black_games'] ? round(($totals['black_score'] / $totals['black_games']) * 100) : null,
    ],
  ];

  return ['games' => $items, 'summary' => $summary];
}

function dashboard_empty_period_summary(): array {
  return [
    'games' => 0,
    'wins' => 0,
    'losses' => 0,
    'draws' => 0,
    'win_rate' => 0,
    'score_rate' => 0,
    'avg_accuracy' => null,
    'avg_acpl' => null,
    'own_blunders' => 0,
    'own_mistakes' => 0,
    'own_inaccuracies' => 0,
    'own_blunders_per_game' => 0,
    'own_mistakes_per_game' => 0,
    'own_inaccuracies_per_game' => 0,
    'white' => ['games' => 0, 'score_rate' => null],
    'black' => ['games' => 0, 'score_rate' => null],
  ];
}

function dashboard_recent_tag_summary(int $userId, array $analysisIds): array {
  if (!$analysisIds) return ['game_tags' => [], 'move_tags' => []];
  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));

  $gameSql = "SELECT gt.tag_code, d.label, d.category, d.severity, COUNT(*) AS total,
                     SUM(gt.evidence_count) AS evidence_count
              FROM game_tags gt
              JOIN smart_tag_definitions d ON d.code=gt.tag_code
              WHERE gt.user_id=? AND gt.analysis_id IN ($placeholders)
              GROUP BY gt.tag_code, d.label, d.category, d.severity
              ORDER BY total DESC, FIELD(d.severity,'critical','high','medium','low','info'), d.label ASC";
  $gameSt = db()->prepare($gameSql);
  $gameSt->execute(array_merge([$userId], $analysisIds));

  $moveSql = "SELECT mt.tag_code, d.label, d.category, mt.severity, COUNT(*) AS total
              FROM move_tags mt
              JOIN smart_tag_definitions d ON d.code=mt.tag_code
              WHERE mt.user_id=? AND mt.analysis_id IN ($placeholders)
              GROUP BY mt.tag_code, d.label, d.category, mt.severity
              ORDER BY total DESC, FIELD(mt.severity,'critical','high','medium','low','info'), d.label ASC";
  $moveSt = db()->prepare($moveSql);
  $moveSt->execute(array_merge([$userId], $analysisIds));

  return [
    'game_tags' => $gameSt->fetchAll(),
    'move_tags' => $moveSt->fetchAll(),
  ];
}

function dashboard_focus_definitions(): array {
  return [
    'results' => [
      'title' => 'Resultados recientes',
      'description' => 'Tus resultados recientes necesitan atencion antes de hilar mas fino.',
      'action' => 'Revisa las partidas perdidas o tablas con errores propios claros.',
      'tags' => [],
    ],
    'tactics' => [
      'title' => 'Vision tactica',
      'description' => 'Estas dejando pasar golpes tacticos o permitiendo recursos fuertes al rival.',
      'action' => 'Empieza revisando omisiones graves, mates omitidos y mates permitidos.',
      'tags' => ['blunder_own', 'mistake_own', 'missed_mate', 'allowed_mate'],
    ],
    'accuracy' => [
      'title' => 'Precision y consistencia',
      'description' => 'Tu precision reciente esta por debajo de tu bloque anterior.',
      'action' => 'Busca jugadas con perdidas moderadas repetidas antes de mirar solo la omision mas grande.',
      'tags' => ['inaccuracy_own'],
    ],
    'opening' => [
      'title' => 'Apertura',
      'description' => 'Los problemas aparecen demasiado pronto en la partida.',
      'action' => 'Revisa partidas con errores en las primeras jugadas y busca principios repetidos.',
      'tags' => ['opening_issue'],
    ],
    'endgame' => [
      'title' => 'Finales',
      'description' => 'Estas perdiendo precision en el tramo final.',
      'action' => 'Revisa finales recientes y posiciones donde la ventaja cambio tarde.',
      'tags' => ['endgame_mistake'],
    ],
    'conversion' => [
      'title' => 'Convertir ventajas',
      'description' => 'Llegas a posiciones favorables, pero no siempre las conviertes.',
      'action' => 'Revisa ventajas desperdiciadas y compara con partidas donde si convertiste.',
      'tags' => ['lost_winning_position'],
    ],
  ];
}

function dashboard_severity_weight(string $severity): int {
  return [
    'critical' => 5,
    'high' => 3,
    'medium' => 2,
    'low' => 1,
    'info' => 1,
  ][$severity] ?? 1;
}

function dashboard_training_focus(array $recentSummary, array $previousSummary, array $tags, int $minimumGames): array {
  $definitions = dashboard_focus_definitions();
  $scores = [];
  foreach ($definitions as $key => $definition) {
    $scores[$key] = [
      'code' => $key,
      'title' => $definition['title'],
      'description' => $definition['description'],
      'recommended_action' => $definition['action'],
      'score' => 0,
      'evidence' => [],
      'tag_codes' => $definition['tags'],
      'games_url' => null,
    ];
  }

  $recentGames = (int)($recentSummary['games'] ?? 0);
  $losses = (int)($recentSummary['losses'] ?? 0);
  $scoreRate = (int)($recentSummary['score_rate'] ?? 0);
  if ($recentGames >= $minimumGames && ($losses >= 4 || $scoreRate < 45)) {
    $scores['results']['score'] += 10;
    $scores['results']['evidence'][] = "{$losses} derrotas en las ultimas {$recentGames} analizadas";
  }

  $accuracy = $recentSummary['avg_accuracy'];
  $previousAccuracy = $previousSummary['avg_accuracy'];
  if ($recentGames >= $minimumGames && $accuracy !== null && $previousAccuracy !== null && ($accuracy + 3) < $previousAccuracy) {
    $scores['accuracy']['score'] += 4;
    $scores['accuracy']['evidence'][] = 'Accuracy bajando frente al bloque anterior';
  }
  if ($recentGames >= $minimumGames && $accuracy !== null && $accuracy < 65) {
    $scores['accuracy']['score'] += 2;
    $scores['accuracy']['evidence'][] = 'Accuracy reciente por debajo de 65%';
  }

  $combinedTags = array_merge($tags['game_tags'] ?? [], $tags['move_tags'] ?? []);
  foreach ($combinedTags as $tag) {
    $code = (string)($tag['tag_code'] ?? '');
    $total = (int)($tag['total'] ?? 0);
    $weight = dashboard_severity_weight((string)($tag['severity'] ?? 'info'));
    foreach ($definitions as $focusCode => $definition) {
      if (!in_array($code, $definition['tags'], true)) continue;
      $scores[$focusCode]['score'] += $total * $weight;
      $scores[$focusCode]['evidence'][] = ($tag['label'] ?? $code) . ': ' . $total;
      if ($scores[$focusCode]['games_url'] === null) {
        $scores[$focusCode]['games_url'] = 'games.php?tag=' . rawurlencode($code);
      }
    }
  }

  if (($recentSummary['own_blunders_per_game'] ?? 0) >= 1) {
    $scores['tactics']['score'] += 4;
    $scores['tactics']['evidence'][] = 'Al menos una omision grave propia por partida';
  }

  usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
  $top = array_values(array_filter($scores, fn($focus) => (int)$focus['score'] > 0));
  if (!$top) {
    $top[] = [
      'code' => 'maintenance',
      'title' => 'Mantener consistencia',
      'description' => 'No hay un patron negativo dominante con los datos actuales.',
      'recommended_action' => 'Revisa una partida precisa reciente y una partida con errores para mantener contraste.',
      'score' => 1,
      'evidence' => ['Sin foco critico dominante'],
      'tag_codes' => [],
      'games_url' => null,
    ];
  }
  return array_slice($top, 0, 3);
}

function dashboard_strengths(array $recentSummary, array $tags): array {
  $strengths = [];
  foreach ($tags['game_tags'] ?? [] as $tag) {
    if (($tag['category'] ?? '') !== 'positive') continue;
    $strengths[] = [
      'title' => $tag['label'] ?? $tag['tag_code'],
      'evidence' => (int)($tag['total'] ?? 0) . ' partida(s)',
      'tag_code' => $tag['tag_code'],
      'games_url' => 'games.php?tag=' . rawurlencode((string)$tag['tag_code']),
    ];
  }
  if (($recentSummary['score_rate'] ?? 0) >= 65 && ($recentSummary['games'] ?? 0) >= 6) {
    $strengths[] = [
      'title' => 'Resultados solidos',
      'evidence' => 'Score reciente de ' . (int)$recentSummary['score_rate'] . '%',
      'tag_code' => null,
      'games_url' => 'games.php?result=win',
    ];
  }
  if (($recentSummary['avg_accuracy'] ?? null) !== null && (float)$recentSummary['avg_accuracy'] >= 75) {
    $strengths[] = [
      'title' => 'Buena precision reciente',
      'evidence' => 'Accuracy media de ' . number_format((float)$recentSummary['avg_accuracy'], 1) . '%',
      'tag_code' => null,
      'games_url' => null,
    ];
  }
  return array_slice($strengths, 0, 3);
}

function dashboard_recommended_reviews(array $games, array $tags): array {
  $ranked = [];
  foreach ($games as $game) {
    $score = 0;
    $reasons = [];
    $loss = (int)($game['own_blunders'] ?? 0);
    $mistakes = (int)($game['own_mistakes'] ?? 0);
    $inaccuracies = (int)($game['own_inaccuracies'] ?? 0);
    if ($loss > 0) {
      $score += $loss * 8;
      $reasons[] = 'omision grave';
    }
    if ($mistakes > 0) {
      $score += $mistakes * 5;
      $reasons[] = 'error importante';
    }
    if ($inaccuracies > 1) {
      $score += $inaccuracies * 2;
      $reasons[] = 'imprecisiones repetidas';
    }
    if (($game['user_result'] ?? '') === 'loss') {
      $score += 4;
      $reasons[] = 'derrota reciente';
    }
    if (($game['accuracy'] ?? null) !== null && (float)$game['accuracy'] < 60) {
      $score += 3;
      $reasons[] = 'accuracy baja';
    }
    if ($score <= 0) continue;
    $ranked[] = [
      'game_id' => $game['game_id'],
      'title' => dashboard_game_title($game),
      'played_at' => $game['played_at'] ?: substr((string)$game['imported_at'], 0, 10),
      'score' => $score,
      'reason' => implode(', ', array_unique($reasons)),
      'accuracy' => $game['accuracy'],
      'review_url' => $game['review_url'],
    ];
  }

  usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
  $recommended = array_slice($ranked, 0, 3);

  $best = null;
  foreach ($games as $game) {
    if (($game['accuracy'] ?? null) === null) continue;
    if ($best === null || (float)$game['accuracy'] > (float)$best['accuracy']) $best = $game;
  }
  if ($best) {
    array_unshift($recommended, [
      'game_id' => $best['game_id'],
      'title' => dashboard_game_title($best),
      'played_at' => $best['played_at'] ?: substr((string)$best['imported_at'], 0, 10),
      'score' => 0,
      'reason' => 'mejor accuracy reciente',
      'accuracy' => $best['accuracy'],
      'review_url' => $best['review_url'],
    ]);
  }

  $unique = [];
  foreach ($recommended as $item) {
    $unique[(int)$item['game_id']] = $item;
  }
  return array_slice(array_values($unique), 0, 4);
}

function dashboard_game_title(array $game): string {
  $white = trim((string)($game['white_player'] ?? 'Blancas'));
  $black = trim((string)($game['black_player'] ?? 'Negras'));
  return ($white ?: 'Blancas') . ' vs ' . ($black ?: 'Negras');
}

function dashboard_form_state(array $recentSummary, array $previousSummary, int $minimumGames): array {
  $games = (int)($recentSummary['games'] ?? 0);
  if ($games < $minimumGames) {
    return [
      'state' => 'insufficient',
      'label' => 'Sin datos suficientes',
      'message' => 'Necesito al menos ' . $minimumGames . ' partidas analizadas para detectar tendencia.',
    ];
  }

  $scoreRate = (int)($recentSummary['score_rate'] ?? 0);
  $accuracy = $recentSummary['avg_accuracy'];
  $previousAccuracy = $previousSummary['avg_accuracy'];
  $delta = ($accuracy !== null && $previousAccuracy !== null) ? round((float)$accuracy - (float)$previousAccuracy, 1) : null;

  if ($scoreRate >= 65 && ($delta === null || $delta >= -2)) {
    return ['state' => 'good', 'label' => 'Buen momento', 'message' => 'Los resultados recientes acompanan. Ahora toca consolidar patrones.'];
  }
  if ($delta !== null && $delta >= 3) {
    return ['state' => 'improving', 'label' => 'Mejorando', 'message' => 'Tu accuracy sube frente al bloque anterior. Buena senal.'];
  }
  if ($scoreRate < 45 || ($delta !== null && $delta <= -3)) {
    return ['state' => 'declining', 'label' => 'Atencion', 'message' => 'Hay senales de bajada. Revisa primero las partidas recomendadas.'];
  }
  return ['state' => 'stable', 'label' => 'Estable', 'message' => 'No hay caida clara, pero si margen para atacar los focos detectados.'];
}

function dashboard_recent_summary_text(array $recentSummary, array $focus): string {
  $games = (int)($recentSummary['games'] ?? 0);
  if ($games === 0) return 'Aun no hay partidas analizadas para construir un resumen de entrenamiento.';
  $accuracy = $recentSummary['avg_accuracy'] === null ? 'sin accuracy disponible' : 'accuracy media ' . number_format((float)$recentSummary['avg_accuracy'], 1) . '%';
  $focusTitle = $focus[0]['title'] ?? 'mantener consistencia';
  return "En tus ultimas {$games} partidas analizadas tienes {$recentSummary['wins']} victorias, {$recentSummary['losses']} derrotas y {$recentSummary['draws']} tablas, con {$accuracy}. Tu primer foco ahora mismo: {$focusTitle}.";
}

function dashboard_payload(int $userId, string $username): array {
  $periodSize = 10;
  $minimumGames = 6;
  $recentGamesRaw = dashboard_latest_analyzed_games($userId, $periodSize);
  $analysisIds = dashboard_analyzed_game_ids($userId, $periodSize * 2);
  $recentAnalysisIds = array_slice($analysisIds, 0, $periodSize);
  $previousAnalysisIds = array_slice($analysisIds, $periodSize, $periodSize);

  $recent = dashboard_metrics_for_games($recentGamesRaw, $username);

  $previousGamesRaw = [];
  if ($previousAnalysisIds) {
    $placeholders = implode(',', array_fill(0, count($previousAnalysisIds), '?'));
    $sql = "SELECT g.id AS game_id, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at,
                   g.imported_at, g.event_name, g.site, g.source,
                   a.id AS analysis_id, a.completed_at, a.created_at AS analysis_created_at
            FROM game_analysis a
            JOIN games g ON g.id=a.game_id
            WHERE a.user_id=? AND a.id IN ($placeholders)
            ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC";
    $st = db()->prepare($sql);
    $st->execute(array_merge([$userId], $previousAnalysisIds));
    $previousGamesRaw = $st->fetchAll();
  }
  $previous = dashboard_metrics_for_games($previousGamesRaw, $username);
  $tags = dashboard_recent_tag_summary($userId, $recentAnalysisIds);
  $focus = dashboard_training_focus($recent['summary'], $previous['summary'], $tags, $minimumGames);

  return [
    'ok' => true,
    'period' => [
      'type' => 'last_analyzed_games',
      'size' => $periodSize,
      'minimum_games_for_trend' => $minimumGames,
      'available_games' => (int)$recent['summary']['games'],
      'has_enough_data' => (int)$recent['summary']['games'] >= $minimumGames,
    ],
    'overview' => $recent['summary'],
    'previous_period' => $previous['summary'],
    'form' => dashboard_form_state($recent['summary'], $previous['summary'], $minimumGames),
    'training_focus' => $focus,
    'strengths' => dashboard_strengths($recent['summary'], $tags),
    'summary_text' => dashboard_recent_summary_text($recent['summary'], $focus),
    'recommended_reviews' => dashboard_recommended_reviews($recent['games'], $tags),
    'patterns' => $tags,
    'recent_games' => $recent['games'],
    'queue' => queue_stats($userId),
  ];
}
