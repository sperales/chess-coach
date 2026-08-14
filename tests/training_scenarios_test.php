<?php
require_once __DIR__ . '/../includes/training.php';
require_once __DIR__ . '/../includes/scenario_runtime.php';
require_once __DIR__ . '/../includes/coach.php';

$failures = [];
function expect_scenario(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}

foreach (['easy', 'medium', 'hard', 'critical'] as $difficulty) {
  $profile = training_scenario_profile($difficulty);
  expect_scenario($profile['target_player_moves'] >= 2, "{$difficulty}: el escenario debe exigir al menos dos decisiones.");
  expect_scenario($profile['max_player_moves'] <= 6, "{$difficulty}: el escenario no debe superar seis decisiones.");
  expect_scenario($profile['target_player_moves'] <= $profile['max_player_moves'], "{$difficulty}: el objetivo debe caber en el límite.");
}

$mateMove = ['score_before' => 4, 'score_before_type' => 'mate', 'bestmove' => 'g7g8', 'centipawn_loss' => 300];
expect_scenario(training_scenario_candidate_type($mateMove, [], []) === 'mate', 'Una secuencia de mate favorable debe generar escenario de mate.');
$mateInOne = ['score_before' => 1, 'score_before_type' => 'mate', 'bestmove' => 'g7g8', 'centipawn_loss' => 300];
expect_scenario(training_scenario_candidate_type($mateInOne, [], []) === null, 'Un mate en una jugada pertenece a Flash, no a un escenario multi-jugada.');

$conversionMove = ['score_before' => 420, 'score_before_type' => 'cp', 'bestmove' => 'e2e4', 'centipawn_loss' => 180];
expect_scenario(training_scenario_candidate_type($conversionMove, [], []) === 'conversion', 'Una ventaja clara desperdiciada debe generar conversión.');

$defenseMove = ['score_before' => -180, 'score_before_type' => 'cp', 'bestmove' => 'g1f3', 'centipawn_loss' => 160];
expect_scenario(training_scenario_candidate_type($defenseMove, [], []) === 'defense', 'Una posición difícil empeorada debe generar defensa.');

$best = training_scenario_decision(250, 245, 75, true);
$alternative = training_scenario_decision(250, 190, 75, false);
$bad = training_scenario_decision(250, 100, 75, false);
expect_scenario($best['accepted'] && $best['bucket'] === 'optimal', 'La bestmove debe aceptarse como óptima.');
expect_scenario($alternative['accepted'] && $alternative['bucket'] === 'acceptable', 'Una alternativa suficientemente buena debe aceptarse.');
expect_scenario(!$bad['accepted'] && $bad['bucket'] === 'problematic', 'Una pérdida superior al umbral debe rechazarse.');

$conversion = ['scenario_type' => 'conversion', 'target_player_moves' => 2];
$defense = ['scenario_type' => 'defense', 'target_player_moves' => 2];
$mate = ['scenario_type' => 'mate', 'target_player_moves' => 2];
expect_scenario(training_scenario_objective_met($conversion, 2, 180, false), 'Conversión debe completarse al conservar ventaja tras el mínimo.');
expect_scenario(!training_scenario_objective_met($conversion, 1, 500, false), 'Conversión no debe terminar con una sola decisión.');
expect_scenario(training_scenario_objective_met($defense, 2, -50, false), 'Defensa debe completarse al estabilizar la posición.');
expect_scenario(training_scenario_objective_met($mate, 2, 100000, true), 'Mate debe requerir una posición de jaque mate real.');
expect_scenario(!training_scenario_objective_met($mate, 6, 100000, false), 'Una evaluación de mate no sustituye al mate ejecutado.');

$startFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
expect_scenario(chess_apply_uci_to_fen($startFen, 'e2e4') !== null, 'Una jugada legal debe avanzar el tablero.');
expect_scenario(chess_apply_uci_to_fen($startFen, 'e2e5') === null, 'Una jugada ilegal nunca debe avanzar el escenario.');

$context = ['training_focus' => [['code' => 'tactics', 'title' => 'Visión táctica', 'tag_codes' => ['missed_mate']]]];
$flash = [];
for ($id = 1; $id <= 6; $id++) $flash[] = ['id' => $id, 'priority_score' => 100 - $id, 'difficulty' => 'medium', 'source_side' => 'user', 'smart_tags' => []];
$scenarios = [
  ['id' => 20, 'scenario_type' => 'mate', 'difficulty' => 'hard', 'starting_ply' => 21, 'initial_eval_cp' => 95000],
  ['id' => 21, 'scenario_type' => 'defense', 'difficulty' => 'medium', 'starting_ply' => 30, 'initial_eval_cp' => -180],
];
$plan = coach_compose_training_blueprint($context, $flash, 6, [], $scenarios);
expect_scenario(count(array_filter($plan['items'], fn(array $item): bool => $item['item_type'] === 'scenario')) === 2, 'Nova debe poder combinar Flash con dos escenarios distintos.');
expect_scenario(array_column($plan['items'], 'position') === [1, 2, 3, 4, 5, 6], 'El plan mixto debe conservar posiciones consecutivas.');

if ($failures) {
  fwrite(STDERR, "Fallos de escenarios:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: generación, límites, alternativas y plan mixto de escenarios verificados.\n";
