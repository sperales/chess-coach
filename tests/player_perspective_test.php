<?php
require_once __DIR__ . '/../includes/helpers.php';

$failures = [];

function assert_perspective_value(string $label, mixed $actual, mixed $expected): void {
  global $failures;
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

$whiteGame = ['white_player' => 'Sperales', 'black_player' => 'Rival'];
$blackGame = ['white_player' => 'Rival', 'black_player' => ' sperales '];

assert_perspective_value('Usuario con blancas', player_perspective_side($whiteGame, 'sperales'), 'w');
assert_perspective_value('Usuario con negras y espacios', player_perspective_side($blackGame, 'SPERALES'), 'b');
assert_perspective_value('Usuario no identificado', player_perspective_side($whiteGame, 'otro'), null);
assert_perspective_value('Ply impar pertenece a blancas', player_perspective_move_side(1), 'w');
assert_perspective_value('Ply par pertenece a negras', player_perspective_move_side(2), 'b');
assert_perspective_value('Ply invalido no tiene bando', player_perspective_move_side(0), null);
assert_perspective_value('Jugada propia con blancas', player_perspective_is_own_move(3, 'w'), true);
assert_perspective_value('Jugada rival con blancas', player_perspective_is_own_move(4, 'w'), false);
assert_perspective_value('Jugada propia con negras', player_perspective_is_own_move(8, 'b'), true);
assert_perspective_value('Sin color no se mezclan ambos bandos', player_perspective_is_own_move(1, null), false);

if ($failures) {
  fwrite(STDERR, "Fallos de perspectiva del jugador:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: perspectiva del jugador para blancas, negras y color desconocido.\n";
