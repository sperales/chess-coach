<?php

const TRAINING_CANONICAL_VERSION = 1;

function training_canonical_normalize_fen(string $fen): ?string {
  $parts = preg_split('/\s+/', trim($fen));
  if (count($parts) < 4) return null;
  [$board, $side, $castling, $ep] = array_slice($parts, 0, 4);
  $ranks = explode('/', $board);
  if (count($ranks) !== 8 || !in_array($side, ['w', 'b'], true)) return null;
  foreach ($ranks as $rank) {
    $squares = 0;
    foreach (str_split($rank) as $char) {
      if (ctype_digit($char)) $squares += (int)$char;
      elseif (preg_match('/^[prnbqkPRNBQK]$/', $char)) $squares++;
      else return null;
    }
    if ($squares !== 8) return null;
  }
  if ($castling !== '-' && !preg_match('/^[KQkq]+$/', $castling)) return null;
  if ($ep !== '-' && !preg_match('/^[a-h][36]$/', $ep)) return null;
  return implode(' ', [$board, $side, $castling, $ep]);
}

function training_canonical_normalize_uci(?string $move): ?string {
  $move = strtolower(trim((string)$move));
  return preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $move) ? $move : null;
}

function training_canonical_stable_value(mixed $value): mixed {
  if (!is_array($value)) return $value;
  if (array_is_list($value)) return array_map('training_canonical_stable_value', $value);
  ksort($value);
  foreach ($value as $key => $item) $value[$key] = training_canonical_stable_value($item);
  return $value;
}

function training_canonical_identity(array $candidate): ?array {
  $fen = training_canonical_normalize_fen((string)($candidate['fen'] ?? ''));
  $solution = training_canonical_normalize_uci($candidate['solution_uci'] ?? null);
  $concept = trim((string)($candidate['primary_concept_code'] ?? ''));
  $objective = trim((string)($candidate['objective_code'] ?? ''));
  if (!$fen || !$solution || $concept === '' || $objective === '') return null;
  $alternatives = [];
  foreach ($candidate['accepted_alternatives'] ?? [] as $alternative) {
    $normalized = training_canonical_normalize_uci((string)$alternative);
    if ($normalized && $normalized !== $solution) $alternatives[] = $normalized;
  }
  $alternatives = array_values(array_unique($alternatives));
  sort($alternatives, SORT_STRING);
  $payload = [
    'v' => TRAINING_CANONICAL_VERSION,
    'fen' => $fen,
    'side' => explode(' ', $fen)[1],
    'concept' => $concept,
    'objective' => $objective,
    'objective_data' => training_canonical_stable_value($candidate['objective_data'] ?? []),
    'solution' => $solution,
    'alternatives' => $alternatives,
  ];
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json === false) return null;
  return $payload + ['hash' => hash('sha256', $json)];
}

