<?php

function piece_set_required_files(): array {
  return ['wp.png','wn.png','wb.png','wr.png','wq.png','wk.png','bp.png','bn.png','bb.png','br.png','bq.png','bk.png'];
}

function piece_set_base_dir(): string {
  return __DIR__ . '/../assets/pieces';
}

function piece_set_is_valid_name(string $name): bool {
  return $name !== '' && preg_match('/^[A-Za-z0-9 _-]{1,80}$/', $name) === 1 && !str_contains($name, '..');
}

function piece_set_has_required_files(string $name): bool {
  if (!piece_set_is_valid_name($name)) return false;
  $dir = piece_set_base_dir() . '/' . $name;
  if (!is_dir($dir)) return false;
  foreach (piece_set_required_files() as $file) {
    if (!is_file($dir . '/' . $file)) return false;
  }
  return true;
}

function available_piece_sets(): array {
  $base = piece_set_base_dir();
  if (!is_dir($base)) return [];

  $sets = [];
  foreach (scandir($base) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    if (piece_set_has_required_files($entry)) $sets[] = $entry;
  }
  natcasesort($sets);
  return array_values($sets);
}

function default_piece_set(): string {
  return piece_set_has_required_files('Set 1') ? 'Set 1' : (available_piece_sets()[0] ?? '');
}

function normalize_piece_set(?string $name): string {
  $value = trim((string)$name);
  return piece_set_has_required_files($value) ? $value : default_piece_set();
}

function piece_set_asset_path(?string $name): string {
  $set = normalize_piece_set($name);
  return 'assets/pieces/' . rawurlencode($set) . '/';
}

