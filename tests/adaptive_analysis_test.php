<?php
require_once __DIR__ . '/../includes/adaptive_analysis.php';

$failures = [];

function assert_adaptive_value(string $label, mixed $actual, mixed $expected): void {
  global $failures;
  if ($actual !== $expected) {
    $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recibido ' . var_export($actual, true);
  }
}

function adaptive_fixture(int $score, string $type = 'cp', int $depth = 15, bool $terminal = false): array {
  return [
    'score' => $score,
    'score_type' => $type,
    'depth' => $depth,
    'terminal' => $terminal,
  ];
}

$profile = adaptive_analysis_profile([]);
assert_adaptive_value('Presupuesto base', $profile['baseline_nodes'], 40000);
assert_adaptive_value('Presupuesto crítico', $profile['critical_nodes'], 200000);
assert_adaptive_value('Modo desactivado sin configuración', adaptive_analysis_enabled([]), false);
assert_adaptive_value(
  'Modo adaptativo explícito',
  adaptive_analysis_enabled(['analysis_strategy' => 'adaptive_nodes']),
  true
);

$moves = [[
  'uci' => 'e2e4',
  'fen_before' => '8/8/8/8/8/8/8/8 w - - 0 1',
  'fen_after' => '8/8/8/8/8/8/8/8 b - - 0 1',
]];

$quiet = [adaptive_fixture(0), adaptive_fixture(-10)];
assert_adaptive_value('Una jugada estable no se profundiza', adaptive_analysis_critical_positions($moves, $quiet, []), []);

$critical = [adaptive_fixture(0), adaptive_fixture(100)];
assert_adaptive_value(
  'Una pérdida relevante profundiza ambos lados de la jugada',
  adaptive_analysis_critical_positions($moves, $critical, []),
  [0, 1]
);

$nearThreshold = [adaptive_fixture(0), adaptive_fixture(60)];
assert_adaptive_value(
  'Un resultado cercano a un umbral se profundiza',
  adaptive_analysis_critical_positions($moves, $nearThreshold, []),
  [0, 1]
);

$lowDepth = [adaptive_fixture(0, 'cp', 8), adaptive_fixture(-5, 'cp', 8)];
assert_adaptive_value(
  'Una búsqueda preliminar poco profunda se refuerza',
  adaptive_analysis_critical_positions($moves, $lowDepth, []),
  [0, 1]
);

assert_adaptive_value(
  'El límite mínimo conserva ambos lados de una jugada crítica',
  adaptive_analysis_critical_positions($moves, $critical, ['max_critical_positions' => 1]),
  [0, 1]
);

if ($failures) {
  fwrite(STDERR, "Fallos del análisis adaptativo:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "OK: presupuestos y selección de posiciones críticas.\n";
