<?php
require_once __DIR__ . '/training_plan.php';
require_once __DIR__ . '/coach_messages.php';

const COACH_TRAINING_VERSION = 1;
const COACH_FLASH_MIN_ITEMS = 3;
const COACH_FLASH_MAX_ITEMS = 10;

function coach_focus_from_context(array $context): array {
  $forcedFocus = is_array($context['forced_focus'] ?? null) ? $context['forced_focus'] : [];
  if ($forcedFocus) return $forcedFocus;

  $focuses = is_array($context['training_focus'] ?? null) ? $context['training_focus'] : [];
  $focus = $focuses[0] ?? [];
  if ($focus) {
    return [
      'code' => (string)($focus['code'] ?? 'recommended'),
      'title' => (string)($focus['title'] ?? 'Entrenamiento recomendado'),
      'description' => (string)($focus['description'] ?? ''),
      'evidence' => array_values(array_filter(array_map('strval', $focus['evidence'] ?? []))),
      'tag_codes' => array_values(array_filter(array_map('strval', $focus['tag_codes'] ?? []))),
      'sample_size' => (int)($context['sample_size'] ?? 0),
    ];
  }

  $planFocus = is_array($context['plan_focus'] ?? null) ? $context['plan_focus'] : [];
  if ($planFocus) {
    return [
      'code' => (string)($planFocus['tag_code'] ?? 'recommended'),
      'title' => (string)($planFocus['label'] ?? 'Entrenamiento recomendado'),
      'description' => 'Patrón pendiente detectado en tus ejercicios.',
      'evidence' => [(int)($planFocus['exercise_count'] ?? 0) . ' posiciones relacionadas pendientes'],
      'tag_codes' => array_values(array_filter([(string)($planFocus['tag_code'] ?? '')])),
      'sample_size' => (int)($planFocus['exercise_count'] ?? 0),
    ];
  }

  return [
    'code' => 'maintenance',
    'title' => 'Mantener consistencia',
    'description' => 'No hay un patrón dominante con suficiente evidencia.',
    'evidence' => ['Selección equilibrada de ejercicios pendientes'],
    'tag_codes' => [],
    'sample_size' => 0,
  ];
}

function coach_candidate_tag_codes(array $candidate): array {
  $tags = is_array($candidate['smart_tags'] ?? null) ? $candidate['smart_tags'] : [];
  return array_values(array_unique(array_filter(array_map(
    fn(array $tag): string => (string)($tag['tag_code'] ?? ''),
    $tags
  ))));
}

function coach_score_flash_candidate(array $candidate, array $focusTagCodes, array $recentExerciseIds = []): int {
  $score = (int)($candidate['priority_score'] ?? 0);
  if (($candidate['last_training_result'] ?? '') === 'failed') $score += 140;
  if (!empty($candidate['is_repeat_due'])) $score += 110;
  if (($candidate['source_side'] ?? 'user') === 'user') $score += 20;
  if (array_intersect($focusTagCodes, coach_candidate_tag_codes($candidate))) $score += 120;
  if (in_array((int)($candidate['id'] ?? 0), $recentExerciseIds, true)) $score -= 180;
  return $score;
}

function coach_select_flash_items(array $candidates, array $focus, int $limit, array $recentExerciseIds = []): array {
  $limit = max(COACH_FLASH_MIN_ITEMS, min(COACH_FLASH_MAX_ITEMS, $limit));
  $focusTags = $focus['tag_codes'] ?? [];
  foreach ($candidates as &$candidate) {
    $candidate['_coach_score'] = coach_score_flash_candidate($candidate, $focusTags, $recentExerciseIds);
  }
  unset($candidate);
  usort($candidates, fn(array $a, array $b): int => ($b['_coach_score'] <=> $a['_coach_score']) ?: ((int)$b['id'] <=> (int)$a['id']));

  $selected = [];
  $conceptCounts = [];
  foreach ($candidates as $candidate) {
    if (count($selected) >= $limit) break;
    $tags = coach_candidate_tag_codes($candidate);
    $matched = array_values(array_intersect($focusTags, $tags));
    $concept = $matched[0] ?? ($tags[0] ?? (string)($candidate['exercise_type'] ?? 'general'));
    if (($conceptCounts[$concept] ?? 0) >= 2 && count($selected) + 1 < $limit) continue;
    $conceptCounts[$concept] = ($conceptCounts[$concept] ?? 0) + 1;
    $reason = $matched
      ? 'Refuerza el foco actual con una posición relacionada.'
      : (($candidate['last_training_result'] ?? '') === 'failed'
        ? 'Recupera una posición que todavía necesita consolidación.'
        : 'Aporta variedad útil al entrenamiento de hoy.');
    $selected[] = [
      'position' => count($selected) + 1,
      'item_type' => 'flash',
      'exercise_id' => (int)$candidate['id'],
      'scenario_id' => null,
      'concept_code' => $concept,
      'reason' => $reason,
      'evidence' => [
        'priority_score' => (int)($candidate['priority_score'] ?? 0),
        'difficulty' => (string)($candidate['difficulty'] ?? 'medium'),
        'exercise_type' => (string)($candidate['exercise_type'] ?? 'other'),
        'source_side' => (string)($candidate['source_side'] ?? 'user'),
        'tag_codes' => $tags,
      ],
    ];
  }
  if (count($selected) < $limit) {
    $selectedIds = array_map('intval', array_column($selected, 'exercise_id'));
    foreach ($candidates as $candidate) {
      if (count($selected) >= $limit) break;
      if (in_array((int)$candidate['id'], $selectedIds, true)) continue;
      $tags = coach_candidate_tag_codes($candidate);
      $selected[] = [
        'position' => count($selected) + 1,
        'item_type' => 'flash',
        'exercise_id' => (int)$candidate['id'],
        'scenario_id' => null,
        'concept_code' => $tags[0] ?? (string)($candidate['exercise_type'] ?? 'general'),
        'reason' => 'Completa el plan con variedad disponible para hoy.',
        'evidence' => [
          'priority_score' => (int)($candidate['priority_score'] ?? 0),
          'difficulty' => (string)($candidate['difficulty'] ?? 'medium'),
          'exercise_type' => (string)($candidate['exercise_type'] ?? 'other'),
          'source_side' => (string)($candidate['source_side'] ?? 'user'),
          'tag_codes' => $tags,
        ],
      ];
      $selectedIds[] = (int)$candidate['id'];
    }
  }
  return $selected;
}

function coach_score_scenario_candidate(array $candidate, array $focusTagCodes): int {
  $score = ['critical' => 180, 'hard' => 140, 'medium' => 100, 'easy' => 70][$candidate['difficulty'] ?? 'medium'] ?? 100;
  $typeFocus = ['mate' => ['tactics', 'missed_mate'], 'defense' => ['defense', 'accuracy'], 'conversion' => ['conversion', 'endgame']];
  if (array_intersect($focusTagCodes, $typeFocus[$candidate['scenario_type'] ?? ''] ?? [])) $score += 140;
  if (!empty($candidate['last_failed_at'])) $score += 120;
  return $score + min(100, (int)($candidate['initial_eval_cp'] ?? 0) / 10);
}

function coach_select_scenario_items(array $candidates, array $focus, int $limit = 2): array {
  $limit = max(0, min(2, $limit));
  foreach ($candidates as &$candidate) $candidate['_coach_score'] = coach_score_scenario_candidate($candidate, $focus['tag_codes'] ?? []);
  unset($candidate);
  usort($candidates, fn(array $a, array $b): int => ($b['_coach_score'] <=> $a['_coach_score']) ?: ((int)$b['id'] <=> (int)$a['id']));
  $selected = [];
  $types = [];
  foreach ($candidates as $candidate) {
    if (count($selected) >= $limit) break;
    $type = (string)$candidate['scenario_type'];
    if (isset($types[$type])) continue;
    $types[$type] = true;
    $selected[] = [
      'position' => 0, 'item_type' => 'scenario', 'exercise_id' => null, 'scenario_id' => (int)$candidate['id'],
      'concept_code' => $type, 'reason' => 'Practica varias decisiones contra la mejor respuesta de Stockfish.',
      'evidence' => ['difficulty' => (string)$candidate['difficulty'], 'scenario_type' => $type, 'starting_ply' => (int)$candidate['starting_ply']],
    ];
  }
  return $selected;
}

function coach_compose_training_blueprint(array $context, array $candidates, int $targetItems, array $recentExerciseIds = [], array $scenarioCandidates = []): array {
  $focus = coach_focus_from_context($context);
  $focusTitleLower = function_exists('mb_strtolower')
    ? mb_strtolower((string)$focus['title'], 'UTF-8')
    : strtolower((string)$focus['title']);
  $scenarioLimit = $targetItems >= 5 ? 2 : ($targetItems >= 3 ? 1 : 0);
  $scenarioItems = coach_select_scenario_items($scenarioCandidates, $focus, $scenarioLimit);
  $flashItems = coach_select_flash_items($candidates, $focus, max(COACH_FLASH_MIN_ITEMS, $targetItems - count($scenarioItems)), $recentExerciseIds);
  $items = [];
  while ($flashItems || $scenarioItems) {
    for ($i = 0; $i < 2 && $flashItems; $i++) $items[] = array_shift($flashItems);
    if ($scenarioItems) $items[] = array_shift($scenarioItems);
  }
  $items = array_slice($items, 0, $targetItems);
  foreach ($items as $index => &$item) $item['position'] = $index + 1;
  unset($item);
  $evidence = $focus['evidence'];
  if (!empty($context['dna_confidence'])) $evidence[] = 'ADN del jugador: confianza ' . (string)$context['dna_confidence'];
  if (!empty($context['recent_training'])) $evidence[] = (int)$context['recent_training'] . ' ejercicios completados recientemente';
  $rationale = $focus['description'] ?: 'Selección basada en tu foco y tu historial reciente.';
  return [
    'coach_version' => COACH_TRAINING_VERSION,
    'focus' => $focus,
    'rationale' => $rationale,
    'evidence' => array_values(array_unique(array_filter($evidence))),
    'estimated_duration_min' => max(3, (int)ceil(count($items) * 1.5)),
    'item_count' => count($items),
    'items' => $items,
    'intro_message' => coach_message_payload(
      'intro',
      'welcome',
      'Hoy vamos a trabajar ' . $focusTitleLower . '. ' . $rationale,
      ['focus_code' => $focus['code'], 'evidence' => $evidence]
    ),
  ];
}

function coach_recent_exercise_ids(int $userId, int $limit = 12): array {
  $limit = max(1, min(50, $limit));
  $st = db()->prepare('SELECT exercise_id FROM training_solve_runs WHERE user_id=? AND status="solved" ORDER BY completed_at DESC,id DESC LIMIT ' . $limit);
  $st->execute([$userId]);
  return array_map('intval', array_column($st->fetchAll(), 'exercise_id'));
}

function coach_latest_dna_context(int $userId): array {
  $st = db()->prepare('SELECT confidence,recent_games,weaknesses_json,recommendations_json FROM player_dna_snapshots WHERE user_id=? ORDER BY generated_at DESC,id DESC LIMIT 1');
  $st->execute([$userId]);
  $row = $st->fetch();
  return $row ? [
    'confidence' => (string)$row['confidence'],
    'recent_games' => (int)$row['recent_games'],
    'weaknesses' => coach_decode_json($row['weaknesses_json'] ?? null),
    'recommendations' => coach_decode_json($row['recommendations_json'] ?? null),
  ] : [];
}

function coach_flash_candidates_for_user(int $userId, int $limit = 80, string $selectedType = 'recommended'): array {
  $types = training_exercise_types();
  if (!isset($types[$selectedType])) $selectedType = 'recommended';
  $list = training_list_exercises($userId, $selectedType, 'pending', 1, max(10, min(100, $limit)));
  return $list['items'];
}

function coach_scenario_candidates_for_user(int $userId, int $limit = 20): array {
  $limit = max(1, min(50, $limit));
  $sql = 'SELECT s.*,
            (SELECT MAX(r.completed_at) FROM training_scenario_runs r WHERE r.scenario_id=s.id AND r.user_id=s.user_id AND r.status="failed") AS last_failed_at
          FROM training_scenarios s
          WHERE s.user_id=? AND s.status="active"
            AND NOT EXISTS (SELECT 1 FROM training_scenario_runs r WHERE r.scenario_id=s.id AND r.user_id=s.user_id AND r.status="completed")
          ORDER BY s.updated_at DESC,s.id DESC LIMIT ' . $limit;
  $st = db()->prepare($sql);
  $st->execute([$userId]);
  return $st->fetchAll();
}

function coach_forced_focus(string $selectedType): array {
  if ($selectedType === 'recommended') return [];
  $types = training_exercise_types();
  if (!isset($types[$selectedType])) return [];
  return [
    'code' => $selectedType,
    'title' => (string)($types[$selectedType]['label'] ?? 'Entrenamiento personalizado'),
    'description' => 'Plan preparado con el tipo de entrenamiento que has elegido.',
    'evidence' => ['Selección manual del tipo de entrenamiento'],
    'tag_codes' => [],
    'sample_size' => 0,
  ];
}

function coach_scenarios_for_selected_type(array $candidates, string $selectedType): array {
  $scenarioType = match ($selectedType) {
    'find_mate' => 'mate',
    'defend_position', 'spot_threat', 'avoid_blunder' => 'defense',
    'convert_advantage' => 'conversion',
    default => null,
  };
  if ($selectedType === 'recommended') return $candidates;
  if ($scenarioType === null) return [];
  return array_values(array_filter($candidates, fn(array $item): bool => ($item['scenario_type'] ?? '') === $scenarioType));
}

function coach_recommendation_for_user(int $userId, array $trainingFocus = [], string $selectedType = 'recommended'): array {
  $types = training_exercise_types();
  if (!isset($types[$selectedType])) $selectedType = 'recommended';
  $settings = training_goal_settings_for_user($userId);
  $dna = coach_latest_dna_context($userId);
  $planFocus = training_plan_focus_candidate($userId);
  $recentSt = db()->prepare('SELECT COUNT(*) FROM training_solve_runs WHERE user_id=? AND status IN ("solved","failed") AND completed_at>=DATE_SUB(NOW(),INTERVAL 14 DAY)');
  $recentSt->execute([$userId]);
  $target = max(COACH_FLASH_MIN_ITEMS, min(COACH_FLASH_MAX_ITEMS, (int)$settings['daily_exercise_goal']));
  return coach_compose_training_blueprint([
    'forced_focus' => coach_forced_focus($selectedType),
    'training_focus' => $trainingFocus,
    'plan_focus' => $planFocus,
    'sample_size' => $trainingFocus[0]['sample_size'] ?? ($dna['recent_games'] ?? 0),
    'dna_confidence' => $dna['confidence'] ?? null,
    'recent_training' => (int)$recentSt->fetchColumn(),
  ], coach_flash_candidates_for_user($userId, 80, $selectedType), $target, coach_recent_exercise_ids($userId),
    coach_scenarios_for_selected_type(coach_scenario_candidates_for_user($userId), $selectedType));
}

function coach_public_session_item(array $row): array {
  return [
    'id' => (int)$row['id'],
    'position' => (int)$row['position'],
    'item_type' => (string)$row['item_type'],
    'exercise_id' => $row['exercise_id'] === null ? null : (int)$row['exercise_id'],
    'scenario_id' => $row['scenario_id'] === null ? null : (int)$row['scenario_id'],
    'concept_code' => (string)($row['concept_code'] ?? ''),
    'reason' => (string)($row['reason'] ?? ''),
    'evidence' => coach_decode_json($row['evidence_json'] ?? null),
    'status' => (string)$row['status'],
  ];
}

function coach_session_plan(int $userId, int $sessionId): ?array {
  $st = db()->prepare('SELECT * FROM training_sessions WHERE id=? AND user_id=? LIMIT 1');
  $st->execute([$sessionId, $userId]);
  $session = $st->fetch();
  if (!$session) return null;
  $itemSt = db()->prepare('SELECT * FROM training_session_items WHERE session_id=? AND user_id=? ORDER BY position');
  $itemSt->execute([$sessionId, $userId]);
  return [
    'training_id' => (int)$session['id'],
    'selected_type' => (string)($session['selected_type'] ?? 'recommended'),
    'coach_version' => (int)($session['coach_version'] ?? 1),
    'focus' => [
      'code' => (string)($session['coach_focus_code'] ?? ''),
      'title' => (string)($session['coach_focus_title'] ?? ''),
    ],
    'rationale' => (string)($session['coach_rationale'] ?? ''),
    'evidence' => coach_decode_json($session['coach_evidence_json'] ?? null),
    'estimated_duration_min' => (int)($session['estimated_duration_min'] ?? 0),
    'item_count' => (int)($session['planned_item_count'] ?? 0),
    'items' => array_map('coach_public_session_item', $itemSt->fetchAll()),
  ];
}

function coach_prepare_session(int $userId, int $sessionId, array $trainingFocus = []): array {
  $existing = coach_session_plan($userId, $sessionId);
  if (!$existing) throw new RuntimeException('Entrenamiento no encontrado.');
  if ($existing['items'] || $existing['focus']['code'] !== '') return $existing;
  $sessionSt = db()->prepare('SELECT selected_type FROM training_sessions WHERE id=? AND user_id=? LIMIT 1');
  $sessionSt->execute([$sessionId, $userId]);
  $selectedType = (string)($sessionSt->fetchColumn() ?: 'recommended');
  $blueprint = coach_recommendation_for_user($userId, $trainingFocus, $selectedType);
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $evidenceJson = json_encode($blueprint['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('UPDATE training_sessions SET coach_version=?,coach_focus_code=?,coach_focus_title=?,coach_rationale=?,coach_evidence_json=?,estimated_duration_min=?,planned_item_count=?,updated_at=NOW() WHERE id=? AND user_id=?')
      ->execute([
        $blueprint['coach_version'], $blueprint['focus']['code'], $blueprint['focus']['title'], $blueprint['rationale'],
        $evidenceJson, $blueprint['estimated_duration_min'], $blueprint['item_count'], $sessionId, $userId,
      ]);
    $insert = $pdo->prepare('INSERT INTO training_session_items (session_id,user_id,position,item_type,exercise_id,scenario_id,concept_code,reason,evidence_json,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,"pending",NOW())');
    foreach ($blueprint['items'] as $item) {
      $insert->execute([
        $sessionId, $userId, $item['position'], $item['item_type'], $item['exercise_id'], $item['scenario_id'],
        $item['concept_code'], $item['reason'], json_encode($item['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ]);
    }
    $pdo->commit();
    coach_record_message($userId, $sessionId, $blueprint['intro_message']);
    error_log('Coach training prepared: user=' . $userId . ' training=' . $sessionId . ' focus=' . $blueprint['focus']['code'] . ' items=' . $blueprint['item_count']);
    return coach_session_plan($userId, $sessionId) ?: $blueprint;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

function coach_current_plan_for_user(int $userId, array $trainingFocus = []): array {
  $training = training_active_session($userId, false);
  if ($training) {
    $plan = coach_session_plan($userId, (int)$training['id']);
    if ($plan && !empty($plan['items'])) return ['training' => $training, 'plan' => $plan];
  }
  return ['training' => $training, 'plan' => coach_recommendation_for_user($userId, $trainingFocus)];
}

function coach_flash_explanation(array $exercise, ?array $attempt = null): string {
  $type = (string)($exercise['exercise_type'] ?? 'find_best_move');
  if ($attempt && empty($attempt['is_solved'])) {
    return match ($type) {
      'find_mate' => 'La jugada elegida no mantiene una secuencia forzada contra el rey. Revisa primero las respuestas defensivas del rival.',
      'defend_position', 'spot_threat' => 'La jugada no neutraliza la amenaza principal y permite que el rival conserve la iniciativa.',
      'convert_advantage' => 'La jugada concede contrajuego y reduce la ventaja que queríamos aprender a conservar.',
      default => 'La jugada pierde demasiado valor frente a las alternativas disponibles en esta posición.',
    };
  }
  return match ($type) {
    'find_mate' => 'El objetivo es reconocer una red de mate y comprobar que el rival no dispone de una defensa suficiente.',
    'defend_position', 'spot_threat' => 'El objetivo es identificar primero el recurso más peligroso del rival y reducir su impacto.',
    'convert_advantage' => 'El objetivo es transformar la ventaja sin abrir nuevas fuentes de contrajuego.',
    'find_tactic' => 'El objetivo es comparar recursos forzantes y entender qué característica concreta hace funcionar la táctica.',
    default => 'El objetivo es comparar jugadas candidatas y elegir una que conserve la calidad de la posición.',
  };
}
