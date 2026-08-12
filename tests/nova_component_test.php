<?php
require_once __DIR__ . '/../includes/helpers.php';

function assert_nova(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$fallback = nova_avatar_html('unknown');
assert_nova(str_contains($fallback, 'nova-avatar--neutral'), 'Unknown states must fall back to neutral.');

$component = nova_coach_html([
  'state' => 'success',
  'title' => '<Nova>',
  'message' => 'Buen trabajo & sigue así.',
  'action_label' => 'Continuar',
  'action_href' => 'training.php?type=a&b=1',
  'compact' => true,
]);

assert_nova(str_contains($component, 'nova-coach--success'), 'The requested Nova state must be rendered.');
assert_nova(str_contains($component, 'nova-coach--compact'), 'Compact presentation must be rendered.');
assert_nova(str_contains($component, '&lt;Nova&gt;'), 'Titles must be HTML escaped.');
assert_nova(str_contains($component, 'Buen trabajo &amp; sigue así.'), 'Messages must be HTML escaped.');
assert_nova(str_contains($component, 'training.php?type=a&amp;b=1'), 'Action URLs must be HTML escaped.');

$unsafeAction = nova_coach_html([
  'action_label' => 'No ejecutar',
  'action_href' => 'javascript:alert(1)',
]);
assert_nova(!str_contains($unsafeAction, '<a '), 'Nova actions must only accept internal URLs.');

$helperSource = file_get_contents(__DIR__ . '/../includes/helpers.php');
$layoutSource = file_get_contents(__DIR__ . '/../assets/js/layout.js');
assert_nova(str_contains($helperSource, 'nova-streak-core'), 'The header must render Nova core streak state.');
assert_nova(str_contains($helperSource, 'nova-core-off.svg'), 'The header must start from Nova inactive core.');
assert_nova(str_contains($layoutSource, 'nova-core-turn-on.svg'), 'The streak activation transition must be available.');
assert_nova(str_contains($layoutSource, 'nova-core-glow-loop.svg'), 'The active streak loop must be available.');

echo "Nova component tests passed.\n";
