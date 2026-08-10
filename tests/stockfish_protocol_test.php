<?php
require_once __DIR__ . '/../includes/stockfish.php';

$failures = [];

function assert_stockfish_value(string $label, mixed $actual, mixed $expected): void {
  global $failures;
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

function assert_stockfish_reason(string $label, callable $callback, string $expectedReason): void {
  global $failures;
  try {
    $callback();
    $failures[] = $label . ': no lanzó ninguna excepción';
  } catch (StockfishException $error) {
    if ($error->reason() !== $expectedReason) {
      $failures[] = $label . ': motivo esperado ' . $expectedReason . ', recibido ' . $error->reason();
    }
  } catch (Throwable $error) {
    $failures[] = $label . ': excepción inesperada ' . get_class($error);
  }
}

$completeOutput = implode("\n", [
  'info depth 17 seldepth 24 multipv 1 score cp 8 nodes 900 time 10 nps 90000 hashfull 1 tbhits 0 pv e2e4 e7e5',
  'info depth 18 seldepth 26 multipv 1 score cp 0 nodes 1234 time 15 nps 82266 hashfull 2 tbhits 0 pv e2e4 e7e5 g1f3',
  'bestmove e2e4 ponder e7e5',
]);
$evaluation = stockfish_parse_output($completeOutput);
assert_stockfish_value('Score cero explícito se conserva', $evaluation['score'], 0);
assert_stockfish_value('Score cero tiene tipo', $evaluation['score_type'], 'cp');
assert_stockfish_value('Bestmove válida', $evaluation['bestmove'], 'e2e4');
assert_stockfish_value('Profundidad final', $evaluation['depth'], 18);
assert_stockfish_value('Nodos finales', $evaluation['nodes'], 1234);
assert_stockfish_value('Tiempo final', $evaluation['time_ms'], 15);
assert_stockfish_value('PV final', $evaluation['pv'], ['e2e4', 'e7e5', 'g1f3']);
assert_stockfish_value('Evaluación completa', $evaluation['complete'], true);
stockfish_assert_complete_evaluation($evaluation, 18);

$missing = stockfish_parse_output("info depth 18 nodes 1234 time 15\nbestmove e2e4\n");
assert_stockfish_value('Score ausente permanece NULL', $missing['score'], null);
assert_stockfish_value('Salida sin score es incompleta', $missing['complete'], false);
assert_stockfish_reason(
  'Salida incompleta se rechaza',
  fn() => stockfish_assert_complete_evaluation($missing, 18),
  'incomplete_output'
);

$shallow = stockfish_parse_output("info depth 12 score cp 15 nodes 500 time 5 pv e2e4\nbestmove e2e4\n");
assert_stockfish_reason(
  'Profundidad insuficiente se rechaza',
  fn() => stockfish_assert_complete_evaluation($shallow, 18),
  'minimum_depth'
);

$terminal = stockfish_parse_output("info depth 0 score mate 0 nodes 0 time 0\nbestmove (none)\n");
assert_stockfish_value('Posición terminal detectada', $terminal['terminal'], true);
stockfish_assert_complete_evaluation($terminal, 18);

assert_stockfish_value('Comando inicial', stockfish_startpos_command([]), 'position startpos');
assert_stockfish_value(
  'Comando con historial completo',
  stockfish_startpos_command(['e2e4', 'e7e5', 'g1f3']),
  'position startpos moves e2e4 e7e5 g1f3'
);

$invalidHistoryRejected = false;
try {
  stockfish_startpos_command(['e2e4', "e7e5\nquit"]);
} catch (InvalidArgumentException $error) {
  $invalidHistoryRejected = true;
}
assert_stockfish_value('Historial UCI inválido rechazado', $invalidHistoryRejected, true);

$identity = stockfish_parse_engine_identity("id name Stockfish 18\nid author the Stockfish developers\nuciok\n", 'AVX2+PGO');
assert_stockfish_value('Nombre UCI registrado', $identity['name'], 'Stockfish 18');
assert_stockfish_value('Versión UCI registrada', $identity['version'], '18');
assert_stockfish_value('Build configurada registrada', $identity['build'], 'AVX2+PGO');

$nullRejected = false;
try {
  normalize_eval_for_side(['score' => null, 'score_type' => null], 'w');
} catch (UnexpectedValueException $error) {
  $nullRejected = true;
}
assert_stockfish_value('Evaluación ausente no se normaliza como cero', $nullRejected, true);

if ($failures) {
  fwrite(STDERR, "Fallos del protocolo Stockfish:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: protocolo UCI, telemetría y evaluaciones incompletas.\n";
