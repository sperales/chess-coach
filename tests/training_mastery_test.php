<?php
require_once __DIR__ . '/../includes/training_mastery.php';

$failures = [];
function expect_mastery(bool $condition, string $message): void {
  global $failures;
  if (!$condition) $failures[] = $message;
}
function mastery_event(string $date, float $quality = 100, int $attempts = 1, int $hint = 0, string $result = 'solved'): array {
  return ['completed_at' => $date . ' 12:00:00', 'quality_score' => $quality, 'evidence_weight' => 1,
    'attempts_count' => $attempts, 'highest_hint_level' => $hint, 'result_code' => $result];
}

$starting = training_mastery_calculate([mastery_event('2026-01-01'), mastery_event('2026-01-01')], null, strtotime('2026-01-02'));
expect_mastery($starting['mastery_state'] === 'starting', 'Dos evidencias en una fecha siguen en iniciando.');

$learning = training_mastery_calculate([
  mastery_event('2026-01-01', 55, 3, 1), mastery_event('2026-01-02', 50, 3, 2), mastery_event('2026-01-02', 60, 2, 1),
], null, strtotime('2026-01-03'));
expect_mastery($learning['mastery_state'] === 'learning', 'Tres evidencias con apoyo en dos fechas deben estar aprendiendo.');

$consolidatingEvents = [];
foreach (['2026-01-01','2026-01-02','2026-01-05','2026-01-08','2026-01-11','2026-01-14'] as $date) $consolidatingEvents[] = mastery_event($date, 82);
$consolidating = training_mastery_calculate($consolidatingEvents, null, strtotime('2026-01-15'));
expect_mastery($consolidating['mastery_state'] === 'consolidating', 'Seis éxitos autónomos distribuidos deben consolidar.');

$stableEvents = [];
foreach (['2026-01-01','2026-01-02','2026-01-03','2026-01-04','2026-01-05','2026-01-12','2026-01-13','2026-01-20','2026-01-21','2026-02-01'] as $date) $stableEvents[] = mastery_event($date, 88);
$stable = training_mastery_calculate($stableEvents, null, strtotime('2026-02-02'));
expect_mastery($stable['mastery_state'] === 'stable', 'Diez evidencias autónomas con repaso espaciado deben ser estables.');

$oneBadDay = $stableEvents;
$oneBadDay[] = mastery_event('2026-02-02', 0, 5, 0, 'failed');
$protected = training_mastery_calculate($oneBadDay, 'stable', strtotime('2026-02-03'));
expect_mastery($protected['mastery_state'] === 'stable', 'Un único fallo no debe destruir mastery estable.');

$inactive = training_mastery_calculate($stableEvents, 'stable', strtotime('2026-05-10'));
expect_mastery($inactive['review_pending'] === true && $inactive['confirmation_required'] === true, 'La inactividad prolongada debe pedir revisión y confirmación sin borrar historial.');

$failedReview = training_review_schedule('failed', 5, 3, 'stable', '2026-01-01 10:00:00');
$autonomousReview = training_review_schedule('solved', 1, 0, 'stable', '2026-01-01 10:00:00');
expect_mastery($failedReview['interval_days'] === 1, 'Un fallo debe volver pronto.');
expect_mastery($autonomousReview['interval_days'] === 21, 'Un éxito autónomo estable debe espaciarse.');

if ($failures) {
  fwrite(STDERR, "Fallos de mastery/repetición:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}
echo "OK: mastery, rendimiento reciente, protección e intervalos verificados.\n";
