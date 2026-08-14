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
assert_training_exercise_mobile(str_contains($script, 'trainingMobileCoachMessages = []'), 'Nova carousel must keep an exercise-scoped message history.');
assert_training_exercise_mobile(str_contains($script, 'appendTrainingMobileCoachMessage'), 'Nova feedback must append to the existing carousel.');
assert_training_exercise_mobile(str_contains($script, 'syncTrainingMobileHintMessages'), 'Progressive hints must join the existing carousel.');
assert_training_exercise_mobile(str_contains($script, 'renderTrainingMobileProgress'), 'Mobile progress must be data driven.');
assert_training_exercise_mobile(str_contains($script, 'settings.daily_exercise_goal'), 'Top progress must use the configured daily goal.');
assert_training_exercise_mobile(str_contains($script, 'bindTrainingMobileCoachSwipe'), 'Nova messages must support touch navigation.');
assert_training_exercise_mobile(str_contains($script, "originLink.href = activeExercise.review_url"), 'Source game must link directly to Review.');
assert_training_exercise_mobile(str_contains($styles, 'exact mobile training exercise composition'), 'Dedicated mobile solver styles must exist.');
assert_training_exercise_mobile(str_contains($styles, '.training-board-frame .board-rank-labels'), 'Board coordinates must be positioned inside the mobile board.');
assert_training_exercise_mobile(str_contains($styles, '.training-auto-submit .training-mobile-check'), 'Auto-submit preference must hide the redundant check action.');
assert_training_exercise_mobile(str_contains($styles, '#trainingSolverHeroSide'), 'Duplicate side-to-move text must be hidden on mobile.');
assert_training_exercise_mobile(substr_count($page, 'Detalles del ejercicio') === 1, 'Exercise details must only appear once.');

echo "Training exercise mobile layout tests passed.\n";
