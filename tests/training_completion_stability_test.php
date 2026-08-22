<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function contract_file(string $path): string {
  $contents = file_get_contents($path);
  if ($contents === false) {
    throw new RuntimeException('No se pudo leer ' . $path);
  }
  return $contents;
}

function contract_contains(string $contents, string $needle, string $message): void {
  if (strpos($contents, $needle) === false) {
    throw new RuntimeException($message);
  }
}

$trainingJs = contract_file($root . '/assets/js/training.js');
$scenarioJs = contract_file($root . '/assets/js/training-scenario.js');
$queueJs = contract_file($root . '/assets/js/analysis_queue.js');
$queuePhp = contract_file($root . '/includes/analysis_queue.php');
$styles = contract_file($root . '/assets/css/app.css');
$smartTags = contract_file($root . '/includes/smart_tags.php');
$migration = contract_file($root . '/sql/migrations/036_changes_1.5.2.sql');

contract_contains($trainingJs, 'Finalizar entrenamiento', 'El último ejercicio debe ofrecer finalizar el entrenamiento.');
contract_contains($trainingJs, 'completed_training=', 'El cierre debe abrir el resumen del entrenamiento completado.');
contract_contains($trainingJs, 'repeatCompletedTraining', 'El historial debe permitir repetir entrenamientos.');
contract_contains($scenarioJs, 'Finalizar entrenamiento', 'El último escenario debe ofrecer finalizar el entrenamiento.');
contract_contains($queueJs, 'api/analyze.php?action=retry', 'La cola debe reintentar el análisis exacto.');
contract_contains($queuePhp, 'queue_recover_stale_analyses($userId);', 'La consulta de la cola debe recuperar trabajos antiguos sin esperar al cron.');
contract_contains($styles, 'trainingCoachAdvance .85s', 'La transición del carrusel debe ser claramente perceptible.');
contract_contains($smartTags, 'SMART_TAG_GENERATOR_VERSION = 2', 'Smart Tags debe usar una versión de generación persistente.');
contract_contains($migration, 'smart_tags_version', 'La migración debe guardar el estado de Smart Tags.');
contract_contains($migration, 'repeated_from_session_id', 'La migración debe relacionar entrenamientos repetidos.');

echo "Training completion and stability contract: OK\n";
