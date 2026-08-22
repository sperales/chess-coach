<?php
require_once __DIR__ . '/../includes/training_metrics.php';

function expect_training_metric(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$summary = training_shadow_metric_summary([
  ['average_quality' => 78, 'overlap_count' => 2, 'legacy_opportunity_ids' => [1,2,3], 'v2_opportunity_ids' => [2,3,4], 'composition' => ['stopped_early' => false]],
  ['average_quality' => 82, 'overlap_count' => 1, 'legacy_opportunity_ids' => [5,6], 'v2_opportunity_ids' => [6], 'composition' => ['stopped_early' => true]],
]);
expect_training_metric($summary['runs'] === 2, 'Debe contar ejecuciones shadow acotadas.');
expect_training_metric($summary['average_quality'] === 80.0, 'Debe calcular calidad media sin inventar datos.');
expect_training_metric($summary['overlap_rate'] === 75.0, 'Debe comparar el solapamiento contra el menor inventario seleccionable.');
expect_training_metric($summary['stopped_early_runs'] === 1, 'Debe observar sesiones acortadas por falta de contenido de calidad.');

echo "Training metrics tests passed.\n";
