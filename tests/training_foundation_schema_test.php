<?php

$migration = file_get_contents(__DIR__ . '/../sql/migrations/037_changes_1.6.0.sql');
$install = file_get_contents(__DIR__ . '/../sql/install.sql');
$opportunities = file_get_contents(__DIR__ . '/../includes/training_opportunities.php');
$failures = [];

foreach ([
  'training_concepts', 'training_concept_mappings', 'training_opportunities', 'training_opportunity_sources',
  'training_opportunity_audits', 'training_selection_runs', 'training_selection_items',
  'training_concept_mastery', 'training_mastery_events', 'coach_decisions', 'training_foundation_backfill_runs',
] as $table) {
  if (!str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table)) $failures[] = "La migración no crea {$table}.";
  if (!str_contains($install, 'CREATE TABLE IF NOT EXISTS ' . $table)) $failures[] = "install.sql no refleja {$table}.";
}
foreach (['opportunity_id', 'selection_reason_code', 'selection_version', 'first_move_at', 'time_to_first_move_ms'] as $column) {
  if (!str_contains($migration, $column)) $failures[] = "La migración no contiene {$column}.";
  if (!str_contains($install, $column)) $failures[] = "install.sql no contiene {$column}.";
}

$upsert = [];
if (!preg_match('/INSERT INTO training_opportunities\s*\((?<columns>[\s\S]*?)\)\s*VALUES\s*\((?<values>[\s\S]*?)\)\s*ON DUPLICATE KEY UPDATE/', $opportunities, $upsert)) {
  $failures[] = 'No se pudo inspeccionar el upsert de oportunidades.';
} else {
  $columnCount = count(array_filter(array_map('trim', explode(',', $upsert['columns']))));
  $placeholderCount = substr_count($upsert['values'], '?');
  $literalCount = preg_match('/\bNOW\(\)/i', $upsert['values']) ? 1 : 0;
  if ($columnCount !== $placeholderCount + $literalCount) {
    $failures[] = "El upsert de oportunidades declara {$columnCount} columnas pero envía " . ($placeholderCount + $literalCount) . ' valores.';
  }
}
if (substr_count($migration, "('tactics_combinations'") !== 1 || substr_count($install, "('tactics_combinations'") !== 1) {
  $failures[] = 'La taxonomía v1 debe sembrarse una sola vez en cada baseline.';
}
if (!str_contains($migration, "'1.6.0-training-quality-foundation'") || !str_contains($install, "'1.6.0-training-quality-foundation'")) {
  $failures[] = 'La migración debe registrarse en ambos baselines.';
}

if ($failures) {
  fwrite(STDERR, "Fallos de paridad de esquema:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: migración 037 e install.sql mantienen la foundation en paridad.\n";
