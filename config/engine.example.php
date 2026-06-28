<?php
// Copia este archivo como config/engine.php y ajusta la ruta real del binario Stockfish en tu hosting.
// No sobrescribas config/engine.php en futuras actualizaciones.
return [
  // Ejemplos: '/home/tu_usuario/bin/stockfish' o __DIR__ . '/../bin/stockfish'
  'stockfish_path' => __DIR__ . '/../bin/stockfish',
  'depth' => 10,
  'max_halfmoves' => 90,
  'movetime_ms' => 800,
  'queue_stale_minutes' => 30,
  'worker_batch_size' => 1,
];
