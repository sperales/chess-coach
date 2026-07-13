<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/dashboard.php';

function player_dna_period_size(): int {
  return 10;
}

function player_dna_minimum_games(): int {
  return 6;
}

function player_dna_baseline_limit(): int {
  return 50;
}

function player_dna_clamp(float $value, float $min = 0, float $max = 100): float {
  return max($min, min($max, $value));
}

function player_dna_round_score(float $value): int {
  return (int)round(player_dna_clamp($value));
}

function player_dna_json(array $value): string {
  return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function player_dna_decode(?string $json): array {
  if (!is_string($json) || trim($json) === '') return [];
  $decoded = json_decode($json, true);
  return is_array($decoded) ? $decoded : [];
}

function player_dna_latest_analyzed_games(int $userId, int $limit): array {
  $limit = max(1, min(100, $limit));
  $sql = 'SELECT g.id AS game_id, g.white_player, g.black_player, g.result_raw, g.user_result,
                 g.played_at, g.imported_at, g.event_name, g.site, g.source,
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

function player_dna_month_analyzed_games(int $userId, string $monthStart, string $nextMonthStart, int $limit = 100): array {
  $limit = max(1, min(150, $limit));
  $sql = 'SELECT g.id AS game_id, g.white_player, g.black_player, g.result_raw, g.user_result,
                 g.played_at, g.imported_at, g.event_name, g.site, g.source,
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
            AND COALESCE(g.played_at, DATE(g.imported_at)) >= ?
            AND COALESCE(g.played_at, DATE(g.imported_at)) < ?
          ORDER BY COALESCE(g.played_at, DATE(g.imported_at)) DESC, g.id DESC
          LIMIT ' . (int)$limit;
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $monthStart, $nextMonthStart]);
  return $st->fetchAll();
}

function player_dna_moves_by_analysis(array $analysisIds): array {
  $analysisIds = array_values(array_unique(array_filter(array_map('intval', $analysisIds))));
  if (!$analysisIds) return [];

  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $sql = "SELECT analysis_id, ply, san, uci, fen_after, score_after, score_after_type,
                 centipawn_loss, classification
          FROM game_move_analysis
          WHERE analysis_id IN ($placeholders)
          ORDER BY analysis_id ASC, ply ASC";
  $st = db()->prepare($sql);
  $st->execute($analysisIds);

  $grouped = [];
  foreach ($st->fetchAll() as $move) {
    $grouped[(int)$move['analysis_id']][] = $move;
  }
  return $grouped;
}

function player_dna_score_after_for_user(array $move, ?string $userSide): ?int {
  if (($move['score_after_type'] ?? 'cp') !== 'cp') return null;
  if ($userSide !== 'w' && $userSide !== 'b') return null;
  if ($move['score_after'] === null || $move['score_after'] === '') return null;

  $score = (int)$move['score_after'];
  $parts = preg_split('/\s+/', trim((string)($move['fen_after'] ?? '')));
  $sideToMove = ($parts[1] ?? 'w') === 'b' ? 'b' : 'w';
  $scoreForWhite = $sideToMove === 'w' ? $score : -$score;
  return $userSide === 'w' ? $scoreForWhite : -$scoreForWhite;
}

function player_dna_material_total(?string $fen): ?int {
  $board = trim((string)($fen ?? ''));
  if ($board === '') return null;
  $board = explode(' ', $board)[0] ?? '';
  $values = ['p' => 1, 'n' => 3, 'b' => 3, 'r' => 5, 'q' => 9];
  $total = 0;
  foreach (str_split($board) as $char) {
    $piece = strtolower($char);
    if (isset($values[$piece])) $total += $values[$piece];
  }
  return $total > 0 ? $total : null;
}

function player_dna_metric_pack(array $games, string $username): array {
  $movesByAnalysis = player_dna_moves_by_analysis(array_column($games, 'analysis_id'));
  $summaryPack = dashboard_metrics_for_games($games, $username);
  $itemsByAnalysis = [];
  foreach ($summaryPack['games'] as $item) {
    $itemsByAnalysis[(int)$item['analysis_id']] = $item;
  }

  $phase = [
    'opening' => ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0],
    'endgame' => ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0],
  ];
  $volatility = [];
  $materialAfter20 = [];
  $gamesAfterBlunder = ['total' => 0, 'non_losses' => 0];

  foreach ($games as $game) {
    $analysisId = (int)$game['analysis_id'];
    $moves = $movesByAnalysis[$analysisId] ?? [];
    $userSide = dashboard_user_side($game, $username);
    $lastPly = 0;
    foreach ($moves as $move) {
      $lastPly = max($lastPly, (int)($move['ply'] ?? 0));
    }

    $previousScore = null;
    $firstOwnBlunder = false;
    foreach ($moves as $move) {
      $ply = (int)($move['ply'] ?? 0);
      if ($userSide !== null && dashboard_move_side($ply) !== $userSide) continue;

      $class = (string)($move['classification'] ?? 'ok');
      if ($ply <= 16 && isset($phase['opening'][$class])) $phase['opening'][$class]++;
      if ($lastPly > 0 && $ply >= max(1, (int)floor($lastPly * 0.72)) && isset($phase['endgame'][$class])) {
        $phase['endgame'][$class]++;
      }
      if ($class === 'blunder') $firstOwnBlunder = true;

      $score = player_dna_score_after_for_user($move, $userSide);
      if ($score !== null) {
        if ($previousScore !== null) $volatility[] = min(800, abs($score - $previousScore));
        $previousScore = $score;
      }

      if ($ply >= 20 && $ply <= 24) {
        $material = player_dna_material_total($move['fen_after'] ?? null);
        if ($material !== null) $materialAfter20[] = $material;
      }
    }

    if ($firstOwnBlunder) {
      $gamesAfterBlunder['total']++;
      if (($game['user_result'] ?? '') !== 'loss') $gamesAfterBlunder['non_losses']++;
    }
  }

  $summary = $summaryPack['summary'];
  $summary['phase'] = $phase;
  $summary['avg_volatility'] = $volatility ? round(array_sum($volatility) / count($volatility), 1) : null;
  $summary['avg_material_after_20'] = $materialAfter20 ? round(array_sum($materialAfter20) / count($materialAfter20), 1) : null;
  $summary['resilience_after_blunder'] = $gamesAfterBlunder;
  $summary['items'] = array_values($itemsByAnalysis);

  return [
    'games' => $summaryPack['games'],
    'summary' => $summary,
  ];
}

function player_dna_tag_summary(int $userId, array $analysisIds): array {
  $raw = dashboard_recent_tag_summary($userId, $analysisIds);
  $combined = [];
  foreach (array_merge($raw['game_tags'] ?? [], $raw['move_tags'] ?? []) as $tag) {
    $code = (string)($tag['tag_code'] ?? '');
    if ($code === '') continue;
    if (!isset($combined[$code])) {
      $combined[$code] = [
        'tag_code' => $code,
        'label' => (string)($tag['label'] ?? $code),
        'category' => (string)($tag['category'] ?? ''),
        'severity' => (string)($tag['severity'] ?? 'info'),
        'total' => 0,
        'evidence_count' => 0,
      ];
    }
    $combined[$code]['total'] += (int)($tag['total'] ?? 0);
    $combined[$code]['evidence_count'] += (int)($tag['evidence_count'] ?? $tag['total'] ?? 0);
  }
  usort($combined, fn($a, $b) => ((int)$b['total'] <=> (int)$a['total']) ?: strcmp($a['label'], $b['label']));
  return array_values($combined);
}

function player_dna_tag_count(array $tags, array $codes): int {
  $total = 0;
  foreach ($tags as $tag) {
    if (in_array((string)$tag['tag_code'], $codes, true)) $total += (int)$tag['total'];
  }
  return $total;
}

function player_dna_dimension_scores(array $recent, array $previous, array $baselineTags): array {
  $summary = $recent['summary'];
  $games = max(1, (int)($summary['games'] ?? 0));
  $accuracy = $summary['avg_accuracy'] === null ? 65.0 : (float)$summary['avg_accuracy'];
  $acpl = $summary['avg_acpl'] === null ? 110.0 : (float)$summary['avg_acpl'];

  $openingErrors = array_sum($summary['phase']['opening'] ?? []);
  $endgameErrors = array_sum($summary['phase']['endgame'] ?? []);
  $missedMate = player_dna_tag_count($baselineTags, ['missed_mate']);
  $allowedMate = player_dna_tag_count($baselineTags, ['allowed_mate']);
  $openingIssues = player_dna_tag_count($baselineTags, ['opening_issue']);
  $endgameIssues = player_dna_tag_count($baselineTags, ['endgame_mistake']);
  $lostWinning = player_dna_tag_count($baselineTags, ['lost_winning_position']);
  $converted = player_dna_tag_count($baselineTags, ['converted_advantage']);
  $comebacks = player_dna_tag_count($baselineTags, ['comeback']);

  $resilience = $summary['resilience_after_blunder'] ?? ['total' => 0, 'non_losses' => 0];
  $resilienceRate = (int)($resilience['total'] ?? 0) > 0
    ? ((int)$resilience['non_losses'] / max(1, (int)$resilience['total'])) * 100
    : 50;

  $previousAccuracy = $previous['summary']['avg_accuracy'] ?? null;
  $accuracyDelta = $previousAccuracy === null ? 0 : ((float)$accuracy - (float)$previousAccuracy);
  $volatility = $summary['avg_volatility'] === null ? 220.0 : (float)$summary['avg_volatility'];
  $accuracyStdProxy = abs($accuracyDelta) + (($summary['own_blunders_per_game'] ?? 0) * 8) + (($summary['own_mistakes_per_game'] ?? 0) * 3);

  $dimensions = [
    'tactical_awareness' => [
      'label' => 'Visión táctica',
      'score' => player_dna_round_score(100 - (($summary['own_blunders_per_game'] ?? 0) * 18) - (($summary['own_mistakes_per_game'] ?? 0) * 7) - (($missedMate + $allowedMate) / $games * 14)),
      'evidence' => [
        'Omisiones graves: ' . (int)($summary['own_blunders'] ?? 0),
        'Errores importantes: ' . (int)($summary['own_mistakes'] ?? 0),
      ],
    ],
    'opening_discipline' => [
      'label' => 'Disciplina de apertura',
      'score' => player_dna_round_score(100 - ($openingErrors / $games * 10) - ($openingIssues / $games * 16)),
      'evidence' => ['Errores tempranos detectados: ' . $openingErrors],
    ],
    'calculation' => [
      'label' => 'Cálculo y precisión',
      'score' => player_dna_round_score($accuracy - max(0, ($acpl - 80) * 0.08) - (($summary['own_inaccuracies_per_game'] ?? 0) * 4)),
      'evidence' => ['Accuracy reciente: ' . number_format($accuracy, 1) . '%', 'ACPL: ' . number_format($acpl, 1)],
    ],
    'endgame_skill' => [
      'label' => 'Finales',
      'score' => player_dna_round_score(100 - ($endgameErrors / $games * 12) - ($endgameIssues / $games * 18)),
      'evidence' => ['Errores en tramo final: ' . $endgameErrors],
    ],
    'risk_management' => [
      'label' => 'Gestión del riesgo',
      'score' => player_dna_round_score(100 - min(45, $volatility / 12) - ($lostWinning / $games * 10)),
      'evidence' => ['Volatilidad media: ' . number_format($volatility, 1), 'Ventajas desperdiciadas: ' . $lostWinning],
    ],
    'conversion' => [
      'label' => 'Conversión de ventajas',
      'score' => player_dna_round_score(55 + ($converted / $games * 16) - ($lostWinning / $games * 20) + (((int)($summary['score_rate'] ?? 0) - 50) * 0.25)),
      'evidence' => ['Ventajas convertidas: ' . $converted, 'Ventajas desperdiciadas: ' . $lostWinning],
    ],
    'defensive_awareness' => [
      'label' => 'Defensa y amenazas rivales',
      'score' => player_dna_round_score(100 - ($allowedMate / $games * 24) - (($summary['own_blunders_per_game'] ?? 0) * 12)),
      'evidence' => ['Mates permitidos: ' . $allowedMate],
    ],
    'consistency' => [
      'label' => 'Consistencia',
      'score' => player_dna_round_score(100 - ($accuracyStdProxy * 2.2)),
      'evidence' => ['Delta accuracy vs bloque anterior: ' . number_format($accuracyDelta, 1) . ' puntos'],
    ],
    'resilience' => [
      'label' => 'Resiliencia tras errores',
      'score' => player_dna_round_score(45 + ($resilienceRate * 0.35) + ($comebacks / $games * 12)),
      'evidence' => ['Partidas no perdidas tras omisión grave: ' . (int)($resilience['non_losses'] ?? 0) . '/' . (int)($resilience['total'] ?? 0)],
    ],
  ];

  foreach ($dimensions as $code => &$dimension) {
    $dimension['code'] = $code;
    $dimension['level'] = player_dna_score_level((int)$dimension['score']);
  }
  unset($dimension);

  return $dimensions;
}

function player_dna_score_level(int $score): string {
  if ($score >= 80) return 'fortaleza';
  if ($score >= 65) return 'estable';
  if ($score >= 50) return 'mejorable';
  return 'prioridad';
}

function player_dna_top_strengths(array $dimensions, array $tags): array {
  $items = [];
  foreach ($dimensions as $dimension) {
    if ((int)$dimension['score'] >= 65) {
      $items[] = [
        'code' => $dimension['code'],
        'source' => 'dimension',
        'title' => $dimension['label'],
        'score' => (int)$dimension['score'],
        'evidence' => $dimension['evidence'][0] ?? 'Buen rendimiento relativo.',
      ];
    }
  }
  foreach ($tags as $tag) {
    if (($tag['category'] ?? '') !== 'positive') continue;
    $items[] = [
      'code' => (string)$tag['tag_code'],
      'source' => 'tag',
      'category' => (string)($tag['category'] ?? ''),
      'severity' => (string)($tag['severity'] ?? 'info'),
      'title' => (string)$tag['label'],
      'score' => 70 + min(20, (int)$tag['total'] * 3),
      'evidence' => (int)$tag['total'] . ' aparición(es) recientes.',
    ];
  }
  usort($items, fn($a, $b) => ((int)$b['score'] <=> (int)$a['score']) ?: strcmp($a['title'], $b['title']));
  return array_slice($items, 0, 3);
}

function player_dna_top_weaknesses(array $dimensions, array $tags): array {
  $items = [];
  foreach ($dimensions as $dimension) {
    if ((int)$dimension['score'] < 70) {
      $items[] = [
        'code' => $dimension['code'],
        'source' => 'dimension',
        'title' => $dimension['label'],
        'score' => (int)$dimension['score'],
        'evidence' => $dimension['evidence'][0] ?? 'Hay margen de mejora.',
      ];
    }
  }
  foreach ($tags as $tag) {
    if (($tag['category'] ?? '') === 'positive') continue;
    $severityBonus = dashboard_severity_weight((string)($tag['severity'] ?? 'info')) * 4;
    $items[] = [
      'code' => (string)$tag['tag_code'],
      'source' => 'tag',
      'category' => (string)($tag['category'] ?? ''),
      'severity' => (string)($tag['severity'] ?? 'info'),
      'title' => (string)$tag['label'],
      'score' => 100 - min(90, ((int)$tag['total'] * 6) + $severityBonus),
      'evidence' => (int)$tag['total'] . ' aparición(es) recientes.',
    ];
  }
  usort($items, fn($a, $b) => ((int)$a['score'] <=> (int)$b['score']) ?: strcmp($a['title'], $b['title']));
  return array_slice($items, 0, 3);
}

function player_dna_style_indicators(array $recentSummary, array $dimensions): array {
  $volatility = $recentSummary['avg_volatility'] === null ? 220.0 : (float)$recentSummary['avg_volatility'];
  $material = $recentSummary['avg_material_after_20'] === null ? 68.0 : (float)$recentSummary['avg_material_after_20'];
  $opening = (int)($dimensions['opening_discipline']['score'] ?? 50);
  $endgame = (int)($dimensions['endgame_skill']['score'] ?? 50);
  $resilience = (int)($dimensions['resilience']['score'] ?? 50);
  $tactical = (int)($dimensions['tactical_awareness']['score'] ?? 50);
  $calculation = (int)($dimensions['calculation']['score'] ?? 50);

  return [
    [
      'code' => 'aggressive_solid',
      'left' => 'Agresivo',
      'right' => 'Sólido',
      'value' => player_dna_round_score(50 + (180 - $volatility) / 5),
      'summary' => $volatility > 260 ? 'Tus partidas cambian mucho de evaluación.' : 'Tu perfil reciente es relativamente controlado.',
    ],
    [
      'code' => 'tactical_positional',
      'left' => 'Táctico',
      'right' => 'Posicional',
      'value' => player_dna_round_score(50 + ($calculation - $tactical) * 0.4),
      'summary' => 'Indicador aproximado basado en errores tácticos y precisión.',
    ],
    [
      'code' => 'simplifier_tension',
      'left' => 'Simplifica rápido',
      'right' => 'Mantiene tensión',
      'value' => player_dna_round_score(($material - 48) * 3),
      'summary' => 'Estimado por material restante alrededor de la jugada 10.',
    ],
    [
      'code' => 'starter_finisher',
      'left' => 'Buen inicio',
      'right' => 'Buen final',
      'value' => player_dna_round_score(50 + ($endgame - $opening) * 0.8),
      'summary' => $opening >= $endgame ? 'Tu inicio parece más estable que el tramo final.' : 'Tu tramo final resiste mejor que la apertura.',
    ],
    [
      'code' => 'pressure',
      'left' => 'Se cae tras error',
      'right' => 'Resiste presión',
      'value' => $resilience,
      'summary' => $resilience >= 65 ? 'Sueles mantener opciones después de errores.' : 'Los errores graves suelen pesar mucho en el resultado.',
    ],
  ];
}

function player_dna_comparisons(array $recent, array $previous, array $currentMonth, array $previousMonth, array $baseline): array {
  $recentSummary = $recent['summary'];
  $previousSummary = $previous['summary'];
  $baselineSummary = $baseline['summary'];

  return [
    'recent_vs_previous' => [
      'label' => 'Últimas 10 vs 10 anteriores',
      'recent_games' => (int)$recentSummary['games'],
      'previous_games' => (int)$previousSummary['games'],
      'accuracy_delta' => player_dna_delta($recentSummary['avg_accuracy'] ?? null, $previousSummary['avg_accuracy'] ?? null),
      'score_delta' => player_dna_delta($recentSummary['score_rate'] ?? null, $previousSummary['score_rate'] ?? null),
      'acpl_delta' => player_dna_delta($previousSummary['avg_acpl'] ?? null, $recentSummary['avg_acpl'] ?? null),
    ],
    'month_vs_previous_month' => [
      'label' => 'Mes actual vs mes anterior',
      'current_games' => (int)$currentMonth['summary']['games'],
      'previous_games' => (int)$previousMonth['summary']['games'],
      'accuracy_delta' => player_dna_delta($currentMonth['summary']['avg_accuracy'] ?? null, $previousMonth['summary']['avg_accuracy'] ?? null),
      'score_delta' => player_dna_delta($currentMonth['summary']['score_rate'] ?? null, $previousMonth['summary']['score_rate'] ?? null),
    ],
    'recent_vs_baseline' => [
      'label' => 'Accuracy reciente vs baseline',
      'recent_games' => (int)$recentSummary['games'],
      'baseline_games' => (int)$baselineSummary['games'],
      'accuracy_delta' => player_dna_delta($recentSummary['avg_accuracy'] ?? null, $baselineSummary['avg_accuracy'] ?? null),
      'acpl_delta' => player_dna_delta($baselineSummary['avg_acpl'] ?? null, $recentSummary['avg_acpl'] ?? null),
    ],
  ];
}

function player_dna_delta($current, $previous): ?float {
  if ($current === null || $previous === null) return null;
  return round((float)$current - (float)$previous, 1);
}

function player_dna_improvement_and_problem(array $dimensions, array $recentDimensions, array $previousDimensions): array {
  $improvements = [];
  foreach ($recentDimensions as $code => $dimension) {
    if (!isset($previousDimensions[$code])) continue;
    $improvements[] = [
      'code' => $code,
      'title' => $dimension['label'],
      'delta' => (int)$dimension['score'] - (int)$previousDimensions[$code]['score'],
    ];
  }
  usort($improvements, fn($a, $b) => ((int)$b['delta'] <=> (int)$a['delta']));

  $weaknesses = array_values($dimensions);
  usort($weaknesses, fn($a, $b) => ((int)$a['score'] <=> (int)$b['score']));

  return [
    'biggest_improvement' => $improvements[0] ?? null,
    'most_persistent_problem' => $weaknesses[0] ?? null,
  ];
}

function player_dna_confidence(int $recentGames, int $baselineGames): string {
  if ($recentGames >= 10 && $baselineGames >= 30) return 'high';
  if ($recentGames >= player_dna_minimum_games() && $baselineGames >= 12) return 'medium';
  return 'low';
}

function player_dna_profile_label(array $dimensions): string {
  $weaknesses = player_dna_top_weaknesses($dimensions, []);
  $strengths = player_dna_top_strengths($dimensions, []);
  $mainStrength = $strengths[0]['title'] ?? 'perfil en construcción';
  $mainWeakness = $weaknesses[0]['title'] ?? 'sin prioridad clara';
  return $mainStrength . ' con foco en ' . $mainWeakness;
}

function player_dna_summary_text(array $recentSummary, array $dimensions): string {
  $games = (int)($recentSummary['games'] ?? 0);
  if ($games === 0) return 'Aún no hay partidas analizadas suficientes para construir tu ADN de jugador.';
  $weakness = player_dna_top_weaknesses($dimensions, [])[0]['title'] ?? 'mantener consistencia';
  $strength = player_dna_top_strengths($dimensions, [])[0]['title'] ?? 'tu base actual';
  return 'En tus últimas ' . $games . ' partidas analizadas, tu punto más sólido es ' . $strength . ' y el foco principal es ' . $weakness . '.';
}

function player_dna_build_snapshot(int $userId, string $username, string $trigger = 'manual'): array {
  $periodSize = player_dna_period_size();
  $minimumGames = player_dna_minimum_games();
  $rawGames = player_dna_latest_analyzed_games($userId, player_dna_baseline_limit());
  $recentRaw = array_slice($rawGames, 0, $periodSize);
  $previousRaw = array_slice($rawGames, $periodSize, $periodSize);

  $monthStart = date('Y-m-01');
  $nextMonthStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));
  $previousMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));

  $recent = player_dna_metric_pack($recentRaw, $username);
  $previous = player_dna_metric_pack($previousRaw, $username);
  $baseline = player_dna_metric_pack($rawGames, $username);
  $currentMonth = player_dna_metric_pack(player_dna_month_analyzed_games($userId, $monthStart, $nextMonthStart), $username);
  $previousMonth = player_dna_metric_pack(player_dna_month_analyzed_games($userId, $previousMonthStart, $monthStart), $username);

  $recentAnalysisIds = array_map('intval', array_column($recentRaw, 'analysis_id'));
  $baselineAnalysisIds = array_map('intval', array_column($rawGames, 'analysis_id'));
  $recentTags = player_dna_tag_summary($userId, $recentAnalysisIds);
  $baselineTags = player_dna_tag_summary($userId, $baselineAnalysisIds);

  $dimensions = player_dna_dimension_scores($recent, $previous, $recentTags);
  $previousDimensions = player_dna_dimension_scores($previous, ['summary' => dashboard_empty_period_summary()], $baselineTags);
  $extras = player_dna_improvement_and_problem($dimensions, $dimensions, $previousDimensions);

  $latest = $rawGames[0] ?? null;
  $latestGameDate = $latest ? ($latest['played_at'] ?: ($latest['imported_at'] ? substr((string)$latest['imported_at'], 0, 10) : null)) : null;

  return [
    'user_id' => $userId,
    'trigger_source' => $trigger,
    'period_size' => $periodSize,
    'minimum_games' => $minimumGames,
    'analyzed_games' => count($rawGames),
    'recent_games' => (int)$recent['summary']['games'],
    'previous_games' => (int)$previous['summary']['games'],
    'baseline_games' => (int)$baseline['summary']['games'],
    'current_month_games' => (int)$currentMonth['summary']['games'],
    'previous_month_games' => (int)$previousMonth['summary']['games'],
    'latest_analysis_id' => $latest ? (int)$latest['analysis_id'] : null,
    'latest_game_id' => $latest ? (int)$latest['game_id'] : null,
    'latest_game_date' => $latestGameDate,
    'confidence' => player_dna_confidence((int)$recent['summary']['games'], (int)$baseline['summary']['games']),
    'profile_label' => player_dna_profile_label($dimensions),
    'summary_text' => player_dna_summary_text($recent['summary'], $dimensions),
    'dimensions' => array_values($dimensions),
    'style' => player_dna_style_indicators($recent['summary'], $dimensions),
    'strengths' => player_dna_top_strengths($dimensions, $recentTags),
    'weaknesses' => player_dna_top_weaknesses($dimensions, $recentTags),
    'comparisons' => player_dna_comparisons($recent, $previous, $currentMonth, $previousMonth, $baseline),
    'recommendations' => player_dna_recommendations($dimensions, $recentTags, $extras),
    'overview' => [
      'recent' => $recent['summary'],
      'previous' => $previous['summary'],
      'baseline' => $baseline['summary'],
      'frequent_tags' => array_slice($recentTags, 0, 8),
      'biggest_improvement' => $extras['biggest_improvement'],
      'most_persistent_problem' => $extras['most_persistent_problem'],
    ],
  ];
}

function player_dna_recommendations(array $dimensions, array $tags, array $extras): array {
  $weakness = player_dna_top_weaknesses($dimensions, $tags)[0] ?? null;
  $code = (string)($weakness['code'] ?? '');
  $map = [
    'tactical_awareness' => ['label' => 'Revisar errores tácticos', 'url' => 'games.php?tag=blunder_own'],
    'opening_discipline' => ['label' => 'Ir al Lab de Aperturas', 'url' => 'openings-lab.php'],
    'calculation' => ['label' => 'Entrenar precisión', 'url' => 'training.php?type=recommended'],
    'endgame_skill' => ['label' => 'Revisar finales', 'url' => 'games.php?tag=endgame_mistake'],
    'conversion' => ['label' => 'Revisar ventajas desperdiciadas', 'url' => 'games.php?tag=lost_winning_position'],
    'defensive_awareness' => ['label' => 'Entrenar defensa', 'url' => 'training.php?type=defend_position'],
    'consistency' => ['label' => 'Ver partidas recomendadas', 'url' => 'app.php#games'],
    'resilience' => ['label' => 'Revisar remontadas y caídas', 'url' => 'games.php'],
  ];
  $primary = $map[$code] ?? ['label' => 'Ver partidas recomendadas', 'url' => 'app.php#games'];

  return [
    'primary' => [
      'code' => $weakness['code'] ?? null,
      'source' => $weakness['source'] ?? null,
      'category' => $weakness['category'] ?? null,
      'severity' => $weakness['severity'] ?? null,
      'title' => $weakness['title'] ?? 'Mantener consistencia',
      'text' => $weakness ? 'Tu foco principal ahora mismo es ' . $weakness['title'] . '.' : 'No hay un foco dominante con la muestra actual.',
      'action_label' => $primary['label'],
      'url' => $primary['url'],
    ],
    'biggest_improvement' => $extras['biggest_improvement'],
    'most_persistent_problem' => $extras['most_persistent_problem'],
  ];
}

function player_dna_save_snapshot(array $snapshot): int {
  $sql = 'INSERT INTO player_dna_snapshots
            (user_id,trigger_source,period_size,minimum_games,analyzed_games,recent_games,previous_games,
             baseline_games,current_month_games,previous_month_games,latest_analysis_id,latest_game_id,
             latest_game_date,confidence,profile_label,summary_text,dimensions_json,style_json,strengths_json,
             weaknesses_json,comparisons_json,recommendations_json,generated_at,created_at,updated_at)
          VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())';
  $st = db()->prepare($sql);
  $st->execute([
    $snapshot['user_id'],
    $snapshot['trigger_source'],
    $snapshot['period_size'],
    $snapshot['minimum_games'],
    $snapshot['analyzed_games'],
    $snapshot['recent_games'],
    $snapshot['previous_games'],
    $snapshot['baseline_games'],
    $snapshot['current_month_games'],
    $snapshot['previous_month_games'],
    $snapshot['latest_analysis_id'],
    $snapshot['latest_game_id'],
    $snapshot['latest_game_date'],
    $snapshot['confidence'],
    $snapshot['profile_label'],
    $snapshot['summary_text'],
    player_dna_json($snapshot['dimensions']),
    player_dna_json($snapshot['style']),
    player_dna_json($snapshot['strengths']),
    player_dna_json($snapshot['weaknesses']),
    player_dna_json($snapshot['comparisons']),
    player_dna_json($snapshot['recommendations']),
  ]);
  return (int)db()->lastInsertId();
}

function player_dna_public_snapshot(array $row): array {
  return [
    'id' => (int)$row['id'],
    'user_id' => (int)$row['user_id'],
    'trigger_source' => (string)$row['trigger_source'],
    'period_size' => (int)$row['period_size'],
    'minimum_games' => (int)$row['minimum_games'],
    'analyzed_games' => (int)$row['analyzed_games'],
    'recent_games' => (int)$row['recent_games'],
    'previous_games' => (int)$row['previous_games'],
    'baseline_games' => (int)$row['baseline_games'],
    'current_month_games' => (int)$row['current_month_games'],
    'previous_month_games' => (int)$row['previous_month_games'],
    'latest_analysis_id' => $row['latest_analysis_id'] === null ? null : (int)$row['latest_analysis_id'],
    'latest_game_id' => $row['latest_game_id'] === null ? null : (int)$row['latest_game_id'],
    'latest_game_date' => $row['latest_game_date'],
    'confidence' => (string)$row['confidence'],
    'profile_label' => (string)($row['profile_label'] ?? ''),
    'summary_text' => (string)($row['summary_text'] ?? ''),
    'dimensions' => player_dna_decode($row['dimensions_json'] ?? null),
    'style' => player_dna_decode($row['style_json'] ?? null),
    'strengths' => player_dna_decode($row['strengths_json'] ?? null),
    'weaknesses' => player_dna_decode($row['weaknesses_json'] ?? null),
    'comparisons' => player_dna_decode($row['comparisons_json'] ?? null),
    'recommendations' => player_dna_decode($row['recommendations_json'] ?? null),
    'generated_at' => $row['generated_at'],
    'created_at' => $row['created_at'],
  ];
}

function player_dna_latest_snapshot(int $userId): ?array {
  $st = db()->prepare('SELECT * FROM player_dna_snapshots WHERE user_id=? ORDER BY generated_at DESC, id DESC LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  return $row ? player_dna_public_snapshot($row) : null;
}

function player_dna_payload(int $userId): array {
  return [
    'ok' => true,
    'snapshot' => player_dna_latest_snapshot($userId),
    'period' => [
      'size' => player_dna_period_size(),
      'minimum_games' => player_dna_minimum_games(),
      'baseline_limit' => player_dna_baseline_limit(),
    ],
  ];
}

function player_dna_recalculate(int $userId, string $username, string $trigger = 'manual'): array {
  $started = microtime(true);
  $runId = null;
  try {
    $run = db()->prepare('INSERT INTO player_dna_runs (user_id,trigger_source,status,started_at) VALUES (?,?,"running",NOW())');
    $run->execute([$userId, $trigger]);
    $runId = (int)db()->lastInsertId();
  } catch (Throwable $e) {
    $runId = null;
  }

  try {
    $snapshot = player_dna_build_snapshot($userId, $username, $trigger);
    $snapshotId = player_dna_save_snapshot($snapshot);
    $durationMs = (int)round((microtime(true) - $started) * 1000);
    $message = 'ADN del jugador recalculado correctamente.';

    if ($runId) {
      $up = db()->prepare('UPDATE player_dna_runs
                           SET status="done", snapshot_id=?, processed_games=?, generated_snapshots=1,
                               duration_ms=?, message=?, completed_at=NOW()
                           WHERE id=?');
      $up->execute([$snapshotId, $snapshot['analyzed_games'], $durationMs, $message, $runId]);
    }

    $saved = player_dna_latest_snapshot($userId);
    return [
      'ok' => true,
      'run_id' => $runId,
      'snapshot_id' => $snapshotId,
      'processed_games' => (int)$snapshot['analyzed_games'],
      'duration_ms' => $durationMs,
      'message' => $message,
      'snapshot' => $saved,
    ];
  } catch (Throwable $e) {
    $durationMs = (int)round((microtime(true) - $started) * 1000);
    $message = public_error_message($e);
    if ($runId) {
      try {
        $up = db()->prepare('UPDATE player_dna_runs
                             SET status="error", error_count=1, duration_ms=?, message=?, error_message=?, completed_at=NOW()
                             WHERE id=?');
        $up->execute([$durationMs, 'No se pudo recalcular el ADN del jugador.', $message, $runId]);
      } catch (Throwable $ignored) {
        // Preserve the public error response even if run logging fails.
      }
    }
    return [
      'ok' => false,
      'run_id' => $runId,
      'duration_ms' => $durationMs,
      'error' => $message,
    ];
  }
}
