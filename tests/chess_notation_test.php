<?php
require_once __DIR__ . '/../includes/chess_notation.php';

$tests = [
  ['Movimiento normal', 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', 'g1f3', 'Nf3'],
  ['Captura', '4k3/8/8/3p4/4P3/8/8/4K3 w - - 0 1', 'e4d5', 'exd5'],
  ['Desambiguacion', '4k3/8/8/8/8/8/8/1N2KN2 w - - 0 1', 'b1d2', 'Nbd2'],
  ['Jaque', '4k3/8/8/8/8/8/8/R3K3 w - - 0 1', 'a1a8', 'Ra8+'],
  ['Mate', '7k/5Q2/6K1/8/8/8/8/8 w - - 0 1', 'f7g7', 'Qg7#'],
  ['Enroque corto', 'r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1', 'e1g1', 'O-O'],
  ['Promocion', '4k3/P7/8/8/8/8/8/4K3 w - - 0 1', 'a7a8q', 'a8=Q+'],
  ['Captura al paso', '4k3/8/8/3pP3/8/8/8/4K3 w - d6 0 1', 'e5d6', 'exd6'],
];

$failures = [];
foreach ($tests as [$label, $fen, $uci, $expected]) {
  $actual = chess_uci_to_san($fen, $uci);
  if ($actual !== $expected) $failures[] = $label . ': esperado ' . $expected . ', recibido ' . var_export($actual, true);
}

$fallbacks = [
  ['FEN invalido', 'invalid fen', 'b1c3', 'b1 → c3'],
  ['UCI ilegal', 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', 'b1b8', 'b1 → b8'],
  ['Promocion fallback', 'invalid fen', 'e7e8q', 'e7 → e8=Q'],
];
foreach ($fallbacks as [$label, $fen, $uci, $expected]) {
  $actual = chess_uci_display($fen, $uci);
  if ($actual !== $expected) $failures[] = $label . ': esperado ' . $expected . ', recibido ' . var_export($actual, true);
}

if ($failures) {
  fwrite(STDERR, "Fallos de notacion SAN:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo 'OK: ' . (count($tests) + count($fallbacks)) . " casos de notacion SAN.\n";
