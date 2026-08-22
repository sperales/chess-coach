<?php
return [
  'app_name' => 'Chess Coach',
  'session_name' => 'chess_coach_session',
  'default_timezone' => 'Europe/Madrid',
  'games_per_page' => 50,
  'analysis_per_page' => 50,
  'default_user_elo' => 1426,
  'auto_queue_imports' => true,
  'interactive_pv_min_plies' => 6,
  'interactive_pv_target_plies' => 8,
  'interactive_pv_max_plies' => 10,
  // legacy keeps Selection v2 disabled, shadow compares without changing UX, active enables it explicitly.
  'training_selection_mode' => 'shadow',
  'training_foundation_backfill_batch_size' => 100,
];
