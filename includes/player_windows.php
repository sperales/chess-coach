<?php

const PLAYER_DNA_STABLE_WINDOW = 50;
const PLAYER_RECENT_FORM_WINDOW = 10;
const PLAYER_COACH_FOCUS_WINDOW = 15;
const PLAYER_RECENT_METRICS_WINDOW = 20;
const PLAYER_TREND_MINIMUM_GAMES = 6;

function player_metric_windows(): array {
  return [
    'dna' => PLAYER_DNA_STABLE_WINDOW,
    'recent_form' => PLAYER_RECENT_FORM_WINDOW,
    'coach_focus' => PLAYER_COACH_FOCUS_WINDOW,
    'recent_metrics' => PLAYER_RECENT_METRICS_WINDOW,
    'trend_minimum' => PLAYER_TREND_MINIMUM_GAMES,
  ];
}

function player_recency_weight(int $index): float {
  $block = max(0, intdiv(max(0, $index), 10));
  return max(0.6, 1.0 - ($block * 0.1));
}

function player_recency_weights(int $count): array {
  $weights = [];
  for ($index = 0; $index < max(0, min(PLAYER_DNA_STABLE_WINDOW, $count)); $index++) {
    $weights[] = player_recency_weight($index);
  }
  return $weights;
}

function player_window_slice(array $items, int $size): array {
  return array_slice(array_values($items), 0, max(0, $size));
}

function player_sample_confidence(int $observations, int $minimum): string {
  return $observations >= $minimum ? 'sufficient' : 'limited';
}

function player_snapshot_matches_analysis(?array $snapshot, int $analysisId): bool {
  if ($snapshot === null) return false;
  return (int)($snapshot['latest_analysis_id'] ?? 0) === $analysisId
    || (string)($snapshot['trigger_source'] ?? '') === 'analysis_completed:' . $analysisId;
}
