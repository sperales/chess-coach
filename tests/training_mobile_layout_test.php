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
assert_training_mobile(str_contains($page, 'Continuar entrenamiento'), 'Training must expose resumable exercises.');
assert_training_mobile(!str_contains($page, 'Empezar sesión'), 'Training must not expose sessions in the UI.');
assert_training_mobile(str_contains($script, "action: 'disabled'"), 'Scenarios must remain visibly disabled.');
assert_training_mobile(str_contains($script, 'training-exercise.php?id='), 'Existing exercises must keep direct solver links.');
assert_training_mobile(str_contains($styles, '.training-nova-proposal'), 'Nova recommendation styles must be present.');
assert_training_mobile(str_contains($styles, '@media(max-width:680px)'), 'Training must have dedicated mobile layout rules.');
assert_training_mobile(str_contains($styles, 'width:140px') && str_contains($styles, 'white-space:nowrap'), 'Category chips must use stable dimensions.');
assert_training_mobile(str_contains($styles, 'aspect-ratio:1.44/1'), 'Nova card must preserve the approved compact mobile proportion.');
assert_training_mobile(!str_contains($styles, '.training-nova-proposal {min-height:710px'), 'Nova card must not restore the oversized mobile layout.');

echo "Training mobile layout tests passed.\n";
