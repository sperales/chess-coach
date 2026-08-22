<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/coach_messages.php';
require_once __DIR__ . '/../includes/nova.php';

function expect_nova(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

expect_nova(coach_message_semantic_state('neutral') === 'idle', 'Los mensajes legacy deben exponerse como estado idle.');
expect_nova(coach_message_semantic_state('warning') === 'hint', 'El estado visual warning debe conservar semántica de pista.');
expect_nova(coach_message_payload('completion', 'session_complete', 'Fin')['state'] === 'session_complete', 'Debe aceptar el cierre semántico del entrenamiento.');
expect_nova(nova_state('hint') === 'warning', 'La pista debe reutilizar la celda visual naranja del sprite.');
expect_nova(nova_state('explaining') === 'focus', 'La explicación debe reutilizar la expresión de foco.');
expect_nova(str_contains(nova_avatar_html('correct'), 'data-nova-state="correct"'), 'El componente debe exponer el estado semántico al frontend.');

$trainingJs = file_get_contents(__DIR__ . '/../assets/js/training.js');
$scenarioJs = file_get_contents(__DIR__ . '/../assets/js/training-scenario.js');
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
expect_nova(str_contains($trainingJs, '}, 220);'), 'Training debe esperar procesamiento real antes de mostrar thinking.');
expect_nova(str_contains($scenarioJs, "['scenario_move', 'scenario_hint', 'scenario_why']"), 'Scenario debe reaccionar solo a eventos reales.');
expect_nova(str_contains($css, '@media(prefers-reduced-motion:reduce)'), 'Nova reactiva debe respetar reduced motion.');

echo "Nova reactive tests passed.\n";
