<?php

require_once __DIR__ . '/../includes/training.php';

function expect_habit(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$settings = [
  'daily_goal_mode' => 'exercises',
  'daily_exercise_goal' => 5,
  'daily_minutes_goal' => 10,
];
$today = new DateTimeImmutable('2026-08-22');
$rows = [
  '2026-08-22' => ['exercises' => 1, 'duration_ms' => 15000],
  '2026-08-21' => ['exercises' => 2, 'duration_ms' => 40000],
  '2026-08-20' => ['exercises' => 1, 'duration_ms' => 20000],
];
$streak = training_streak_from_activity_rows($rows, $settings, $today);
expect_habit($streak['days'] === 3, 'Una actividad finalizada al día debe mantener la racha aunque no complete el objetivo.');
expect_habit($streak['trained_today'] === true, 'La actividad finalizada debe marcar el día como entrenado.');
expect_habit($streak['today_goal_met'] === false, 'La racha y el objetivo diario deben permanecer separados.');

$withoutToday = $rows;
unset($withoutToday['2026-08-22']);
$pending = training_streak_from_activity_rows($withoutToday, $settings, $today);
expect_habit($pending['days'] === 2 && $pending['continues_if_completed_today'] === true, 'La racha previa debe poder continuar al entrenar hoy.');

$broken = $withoutToday;
unset($broken['2026-08-21']);
expect_habit(training_streak_from_activity_rows($broken, $settings, $today)['days'] === 0, 'Un día sin actividad debe cortar la racha.');

echo "training_habit_streak_test: OK\n";
