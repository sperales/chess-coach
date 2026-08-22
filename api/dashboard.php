<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/coach_decision.php';

$u = require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_get_request();

$payload = dashboard_payload((int)$u['id'], (string)$u['username']);
$payload['diagnostic_focus'] = $payload['training_focus'] ?? [];
$coachDecision = null;
try {
  $coachDecision = coach_decision_public(coach_decision_for_user((int)$u['id']));
} catch (Throwable $e) {
  $coachDecision = null;
}
if ($coachDecision) {
  $canonicalFocus = [
    'code' => $coachDecision['primary_concept_code'],
    'title' => $coachDecision['primary_label'],
    'description' => $coachDecision['reason'],
    'evidence' => [$coachDecision['session_objective']],
    'recommended_action' => $coachDecision['session_objective'],
    'games_url' => 'training.php',
    'confidence' => $coachDecision['confidence'],
    'decision_id' => $coachDecision['id'],
  ];
  $payload['training_focus'] = [$canonicalFocus];
  $overview = $payload['overview'] ?? [];
  $games = (int)($overview['games'] ?? 0);
  $accuracy = isset($overview['avg_accuracy']) && $overview['avg_accuracy'] !== null
    ? 'accuracy media ' . number_format((float)$overview['avg_accuracy'], 1) . '%'
    : 'sin accuracy disponible';
  $payload['summary_text'] = $games > 0
    ? "En tus últimas {$games} partidas analizadas tienes " . (int)($overview['wins'] ?? 0) . ' victorias, ' . (int)($overview['losses'] ?? 0) . ' derrotas y ' . (int)($overview['draws'] ?? 0) . " tablas, con {$accuracy}. Foco actual del Coach: {$coachDecision['primary_label']}."
    : 'Aún no hay partidas analizadas para construir un resumen de entrenamiento.';
}
$payload['coach_decision'] = $coachDecision;
$currentTraining = coach_current_plan_for_user((int)$u['id'], $payload['training_focus'] ?? []);
$payload['coach_plan'] = $currentTraining['plan'];
$payload['active_training'] = $currentTraining['training'];
json_response($payload);
