<?php

function assert_training_exercise_mobile(bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, $message."\n");
    exit(1);
  }
}

$page = file_get_contents(__DIR__.'/../training-exercise.php');
$script = file_get_contents(__DIR__.'/../assets/js/training.js');
$styles = file_get_contents(__DIR__.'/../assets/css/app.css');

assert_training_exercise_mobile(str_contains($page, 'training-mobile-progress'), 'Solver must expose compact exercise progress.');
assert_training_exercise_mobile(str_contains($page, 'trainingMobileCoach'), 'Solver must include the mobile Nova coach card.');
assert_training_exercise_mobile(str_contains($page, 'Explícame'), 'Solver must expose the explanation action.');
assert_training_exercise_mobile(str_contains($page, 'Dame una pista'), 'Solver must expose progressive hints.');
assert_training_exercise_mobile(str_contains($page, 'Progreso del módulo'), 'Solver must show module progress.');
assert_training_exercise_mobile(str_contains($page, 'Objetivos de hoy'), 'Solver must show daily progress.');
assert_training_exercise_mobile(str_contains($script, "trainingMobileCoachMode = data.solved ? 'success'"), 'Nova state must follow real attempt results.');
assert_training_exercise_mobile(str_contains($script, 'renderTrainingMobileProgress'), 'Mobile progress must be data driven.');
assert_training_exercise_mobile(str_contains($styles, 'exact mobile training exercise composition'), 'Dedicated mobile solver styles must exist.');
assert_training_exercise_mobile(str_contains($styles, '.training-board-frame .board-rank-labels'), 'Board coordinates must be positioned inside the mobile board.');
assert_training_exercise_mobile(str_contains($styles, '.training-auto-submit .training-mobile-check'), 'Auto-submit preference must hide the redundant check action.');

echo "Training exercise mobile layout tests passed.\n";
