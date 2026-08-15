<?php

function assert_training_mobile(bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, $message."\n");
    exit(1);
  }
}

$page = file_get_contents(__DIR__.'/../training.php');
$script = file_get_contents(__DIR__.'/../assets/js/training.js');
$styles = file_get_contents(__DIR__.'/../assets/css/app.css');

assert_training_mobile(str_contains($page, 'training-mobile-first'), 'Training must use the mobile-first layout.');
assert_training_mobile(str_contains($page, 'Nova te propone tu entrenamiento'), 'Training must include Nova recommendation.');
assert_training_mobile(str_contains($page, 'Entrena por categoría'), 'Training must expose training categories.');
assert_training_mobile(str_contains($page, 'Ejercicios disponibles'), 'Training must expose the available exercise list.');
assert_training_mobile(!str_contains($page, 'Empezar sesión'), 'Training must not expose sessions in the UI.');
assert_training_mobile(str_contains($script, "title: 'Escenarios'") && str_contains($script, "category: 'scenarios'"), 'Scenarios must expose their available exercise list.');
assert_training_mobile(str_contains($script, 'training-scenario.php?'), 'Training plans must route scenarios to their dedicated solver.');
assert_training_mobile(str_contains($script, 'trainingContinueCard') && str_contains($script, 'new URLSearchParams({ id:'), 'Existing exercises must keep direct solver links.');
assert_training_mobile(str_contains($page, 'Plan de Nova') && str_contains($script, 'replaceTrainingPlanType'), 'The plan selector must rebuild Nova recommendations, not only filter rows.');
assert_training_mobile(str_contains($script, 'trainingScenarios.map(trainingScenarioListCard)') && !str_contains($script, 'trainingExercises.slice(0, 2)'), 'Categories must list all paginated exercises instead of only two previews.');
assert_training_mobile(str_contains($styles, '.training-nova-proposal'), 'Nova recommendation styles must be present.');
assert_training_mobile(str_contains($styles, '@media(max-width:680px)'), 'Training must have dedicated mobile layout rules.');
assert_training_mobile(str_contains($styles, 'width:140px') && str_contains($styles, 'white-space:nowrap'), 'Category chips must use stable dimensions.');
assert_training_mobile(str_contains($styles, 'aspect-ratio:1.44/1'), 'Nova card must preserve the approved compact mobile proportion.');
assert_training_mobile(!str_contains($styles, '.training-nova-proposal {min-height:710px'), 'Nova card must not restore the oversized mobile layout.');
assert_training_mobile(str_contains($styles, 'width:158px'), 'Nova metrics must leave enough room for long labels.');
assert_training_mobile(str_contains($styles, 'right:9%'), 'Nova must keep clear of the speech bubble on mobile.');

echo "Training mobile layout tests passed.\n";
