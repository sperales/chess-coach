<?php

function assert_scenario_ui(bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, $message."\n");
    exit(1);
  }
}

$page = file_get_contents(__DIR__.'/../training-scenario.php');
$script = file_get_contents(__DIR__.'/../assets/js/training-scenario.js');
$styles = file_get_contents(__DIR__.'/../assets/css/app.css');
$runtime = file_get_contents(__DIR__.'/../includes/scenario_runtime.php');

assert_scenario_ui(str_contains($page, 'training-scenario-workspace'), 'Scenario must have a dedicated solver workspace.');
assert_scenario_ui(str_contains($page, '¿Por qué?') && str_contains($page, 'Ayuda'), 'Scenario must separate explanations from progressive help.');
assert_scenario_ui(str_contains($page, 'Plan de hoy') && !str_contains($page, 'Sesión'), 'Scenario UI must expose the plan without internal session wording.');
assert_scenario_ui(str_contains($page, 'Partida origen'), 'Scenario must preserve provenance and direct Review access.');
assert_scenario_ui(str_contains($script, "scenarioPost('scenario_move'"), 'Scenario moves must use the Training v2 API.');
assert_scenario_ui(str_contains($script, "scenarioPost('scenario_hint'") && str_contains($script, "scenarioPost('scenario_why'"), 'Scenario actions must call their independent endpoints.');
assert_scenario_ui(str_contains($script, 'bindScenarioSwipe'), 'Coach Feed must support horizontal touch navigation.');
assert_scenario_ui(str_contains($script, 'SCENARIO_PLAN_ID <= 0') && str_contains($page, '$trainingId > 0'), 'Isolated scenarios must not create or display a plan context.');
assert_scenario_ui(str_contains($script, 'scenarioFeedAnimateAdvance'), 'New scenario messages must animate into view.');
assert_scenario_ui(str_contains($script, "item.item_type === 'scenario'"), 'Plan navigation must support mixed Flash and Scenario items.');
assert_scenario_ui(str_contains($script, 'scenarioWrongDestination'), 'Rejected decisions must remain visually identifiable.');
assert_scenario_ui(str_contains($styles, '.training-scenario-board') && str_contains($styles, '.scenario-file-label'), 'Scenario board must be responsive and show internal coordinates.');
assert_scenario_ui(str_contains($styles, '--coach-state:#ef5350'), 'Error Nova state and border must share the red semantic color.');
assert_scenario_ui(str_contains($runtime, "'player_fen' => \$fenAfterUser"), 'Runtime must expose the accepted player position before the rival response.');

echo "Training scenario UI tests passed.\n";
