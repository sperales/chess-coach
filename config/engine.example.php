<?php
// Copia este archivo como config/engine.php y ajusta la ruta real del binario Stockfish en tu hosting.
// No sobrescribas config/engine.php en futuras actualizaciones.
return [
  // Ejemplos: '/home/tu_usuario/bin/stockfish' o __DIR__ . '/../bin/stockfish'
  'stockfish_path' => __DIR__ . '/../bin/stockfish',
  // Etiqueta informativa opcional; la versión real se detecta mediante el handshake UCI.
  'engine_build' => null,
  'depth' => 10,
  // Solo se usa como criterio mínimo cuando movetime_ms es mayor que cero.
  'minimum_depth' => 1,
  'threads' => 1,
  'hash_mb' => 32,
  'search_timeout_seconds' => 60,
  'evaluation_retries' => 1,
  // Evita que dos llamadas HTTP simultáneas levanten dos motores en el hosting compartido.
  'serialize_stockfish_processes' => true,
  'restart_after_evaluations' => 40,
  'max_halfmoves' => 90,
  'movetime_ms' => 800,
  'queue_stale_minutes' => 30,
  'analysis_max_attempts' => 2,
  'worker_batch_size' => 1,
];
