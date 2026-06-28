<?php
return [
  // Token secreto para ejecutar el worker por HTTP GET desde el cron del hosting.
  // Ejemplo de URL: https://tu-dominio.com/chess/worker/analyze_queue.php?token=4f2c...
  'worker_token' => 'TU_TOKEN',
  // Intervalo esperado del cron en minutos. Solo se usa para mostrar la próxima ejecución estimada.
  'expected_interval_minutes' => 360,
];
