<?php

const TRAINING_COMPOSER_VERSION = 1;

function training_composer_profile_for_target(int $targetItems): array {
  if ($targetItems <= 3) {
    return ['code' => 'short', 'item_count' => 3, 'duration_min' => 5, 'flash_target' => 2, 'scenario_target' => 1];
  }
  if ($targetItems <= 5) {
    return ['code' => 'standard', 'item_count' => 5, 'duration_min' => 9, 'flash_target' => 3, 'scenario_target' => 2];
  }
  return ['code' => 'long', 'item_count' => 8, 'duration_min' => 15, 'flash_target' => 5, 'scenario_target' => 3];
}

function training_composer_is_review(array $rankedItem): bool {
  $reason = (string)($rankedItem['selection']['reason_code'] ?? '');
  return in_array($reason, ['previous_failure', 'due_review', 'mastery_consolidation', 'maintenance'], true);
}

function training_composer_identity(array $candidate): string {
  $canonical = trim((string)($candidate['canonical_key'] ?? ''));
  return $canonical !== '' ? $canonical : 'opportunity:' . (int)($candidate['id'] ?? 0);
}

function training_composer_v2_compose(array $ranked, array $profile, ?string $primaryFocus = null, ?string $secondaryFocus = null): array {
  $target = max(1, (int)($profile['item_count'] ?? 5));
  $eligible = array_values(array_filter($ranked, static function (array $item): bool {
    $candidate = $item['candidate'] ?? [];
    return (int)($candidate['pedagogical_score'] ?? 0) >= 65
      && ($candidate['publication_state'] ?? 'published') === 'published'
      && ($candidate['currency_state'] ?? 'current') === 'current';
  }));

  $selected = [];
  $used = [];
  $formatCounts = ['flash' => 0, 'scenario' => 0];
  $primaryCount = 0;
  $secondaryCount = 0;
  $reviewCount = 0;
  $recentGameCount = 0;
  $primaryTarget = max(1, (int)ceil($target * 0.6));
  $secondaryTarget = $secondaryFocus ? max(1, (int)floor($target * 0.2)) : 0;
  $reviewTarget = max(1, (int)ceil($target * 0.6));

  while ($eligible && count($selected) < $target) {
    $bestIndex = null;
    $bestScore = null;
    foreach ($eligible as $index => $item) {
      $candidate = $item['candidate'];
      $identity = training_composer_identity($candidate);
      if (isset($used[$identity])) continue;
      $reason = (string)($item['selection']['reason_code'] ?? 'maintenance');
      if ($reason === 'recent_game' && $recentGameCount >= 1) continue;

      $concept = (string)($candidate['primary_concept_code'] ?? '');
      $format = (string)($candidate['recommended_format'] ?? 'flash');
      $score = (int)($item['selection']['selection_priority'] ?? 0);
      if ($concept === $primaryFocus && $primaryCount < $primaryTarget) $score += 24;
      if ($secondaryFocus && $concept === $secondaryFocus && $secondaryCount < $secondaryTarget) $score += 16;
      if (training_composer_is_review($item) && $reviewCount < $reviewTarget) $score += 18;
      $formatTarget = (int)($profile[$format . '_target'] ?? 0);
      if (($formatCounts[$format] ?? 0) < $formatTarget) $score += 12;
      if ($bestScore === null || $score > $bestScore) {
        $bestIndex = $index;
        $bestScore = $score;
      }
    }
    if ($bestIndex === null) break;
    $item = $eligible[$bestIndex];
    array_splice($eligible, $bestIndex, 1);
    $candidate = $item['candidate'];
    $identity = training_composer_identity($candidate);
    $used[$identity] = true;
    $concept = (string)($candidate['primary_concept_code'] ?? '');
    $format = (string)($candidate['recommended_format'] ?? 'flash');
    $formatCounts[$format] = ($formatCounts[$format] ?? 0) + 1;
    if ($concept === $primaryFocus) $primaryCount++;
    if ($secondaryFocus && $concept === $secondaryFocus) $secondaryCount++;
    if (training_composer_is_review($item)) $reviewCount++;
    if (($item['selection']['reason_code'] ?? '') === 'recent_game') $recentGameCount++;
    $item['rank'] = count($selected) + 1;
    $selected[] = $item;
  }

  return [
    'composer_version' => TRAINING_COMPOSER_VERSION,
    'profile' => $profile,
    'selected' => $selected,
    'planned_count' => $target,
    'selected_count' => count($selected),
    'stopped_early' => count($selected) < $target,
    'mix' => [
      'primary_focus' => $primaryCount,
      'secondary_focus' => $secondaryCount,
      'review' => $reviewCount,
      'recent_game' => $recentGameCount,
      'flash' => $formatCounts['flash'],
      'scenario' => $formatCounts['scenario'],
    ],
  ];
}
