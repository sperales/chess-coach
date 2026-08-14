<?php
require_once __DIR__ . '/../includes/coach.php';

$failures = [];
function expect_coach(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

$context = [
  'training_focus' => [[
    'code' => 'tactics',
    'title' => 'Visión táctica',
    'description' => 'Se repiten omisiones tácticas recientes.',
    'evidence' => ['3 omisiones graves en 8 partidas'],
    'tag_codes' => ['blunder_own', 'missed_mate'],
  ]],
  'sample_size' => 8,
  'dna_confidence' => 'high',
  'recent_training' => 4,
];
$candidates = [
  ['id' => 1, 'exercise_type' => 'find_tactic', 'priority_score' => 200, 'difficulty' => 'hard', 'source_side' => 'user', 'smart_tags' => [['tag_code' => 'blunder_own']]],
  ['id' => 2, 'exercise_type' => 'find_mate', 'priority_score' => 180, 'difficulty' => 'medium', 'source_side' => 'user', 'last_training_result' => 'failed', 'smart_tags' => [['tag_code' => 'missed_mate']]],
  ['id' => 3, 'exercise_type' => 'convert_advantage', 'priority_score' => 250, 'difficulty' => 'medium', 'source_side' => 'user', 'smart_tags' => [['tag_code' => 'lost_winning_position']]],
  ['id' => 4, 'exercise_type' => 'find_best_move', 'priority_score' => 100, 'difficulty' => 'easy', 'source_side' => 'opponent', 'smart_tags' => []],
];

$plan = coach_compose_training_blueprint($context, $candidates, 3, [3]);
expect_coach($plan['focus']['code'] === 'tactics', 'El Coach debe consumir el foco existente.');
expect_coach($plan['item_count'] === 3, 'El plan debe respetar el tamaño solicitado cuando hay candidatos.');
expect_coach($plan['items'][0]['exercise_id'] === 2, 'Un fallo relacionado con el foco debe recibir prioridad alta.');
expect_coach($plan['items'][0]['item_type'] === 'flash', 'Los ejercicios actuales deben inferirse como Flash.');
expect_coach(!in_array(3, array_column($plan['items'], 'exercise_id'), true), 'Un ejercicio recién resuelto debe evitarse si hay alternativas.');
expect_coach(in_array('3 omisiones graves en 8 partidas', $plan['evidence'], true), 'La recomendación debe conservar evidencia estructurada.');
expect_coach($plan['intro_message']['state'] === 'welcome', 'El Coach debe emitir un estado semántico, no un color.');
expect_coach(!array_key_exists('color', $plan['intro_message']), 'La lógica del Coach no debe contener decisiones visuales.');

$message = coach_message_payload('hint', 'thinking', 'Mira el flanco de rey.', ['hint_level' => 1]);
expect_coach($message['type'] === 'hint' && $message['state'] === 'thinking', 'El contrato de mensajes debe conservar tipo y estado.');
expect_coach(coach_message_payload('bad', 'orange', 'Fallback')['state'] === 'neutral', 'Estados de UI o inválidos deben normalizarse.');

$source = file_get_contents(__DIR__ . '/../includes/coach.php');
expect_coach(stripos($source, 'nova') === false, 'El servicio Coach no debe depender de Nova.');

if ($failures) {
  fwrite(STDERR, "Fallos de Coach foundation:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: selección, evidencia y separación Coach/Nova verificadas.\n";
