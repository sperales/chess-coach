<?php
require_once __DIR__ . '/../includes/player_windows.php';

$failures = [];
$items = range(1, 73);
$shortWindow = player_window_slice(range(1, 12), PLAYER_DNA_STABLE_WINDOW);
if (count($shortWindow) !== 12) {
  $failures[] = 'La ventana estable debe aceptar muestras menores que su máximo.';
}
$window = player_window_slice($items, PLAYER_DNA_STABLE_WINDOW);
if (count($window) !== 50 || $window[0] !== 1 || $window[49] !== 50) {
  $failures[] = 'La ventana estable no conserva las 50 partidas más recientes.';
}

$weights = player_recency_weights(50);
if (count($weights) !== 50 || $weights[0] !== 1.0 || $weights[10] !== 0.9 || $weights[49] !== 0.6) {
  $failures[] = 'Los pesos por recencia no siguen los bloques 1.0-0.6 esperados.';
}
if (player_sample_confidence(5, 6) !== 'limited' || player_sample_confidence(6, 6) !== 'sufficient') {
  $failures[] = 'La confianza por dimensión no respeta su muestra mínima.';
}
if (!player_snapshot_matches_analysis(['latest_analysis_id' => 42], 42)
    || !player_snapshot_matches_analysis(['latest_analysis_id' => 99, 'trigger_source' => 'analysis_completed:42'], 42)
    || player_snapshot_matches_analysis(['latest_analysis_id' => 41], 42)
    || player_snapshot_matches_analysis(null, 42)) {
  $failures[] = 'La detección de snapshot vigente no evita recálculos redundantes.';
}

if ($failures) {
  fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
  exit(1);
}

echo "OK: ventanas, ponderación y confianza verificadas." . PHP_EOL;
