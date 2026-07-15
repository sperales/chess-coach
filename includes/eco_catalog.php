<?php
require_once __DIR__ . '/db.php';

function eco_catalog_normalize_code(?string $ecoCode): ?string {
  $ecoCode = strtoupper(trim((string)$ecoCode));
  return preg_match('/^[A-E][0-9]{2}$/', $ecoCode) ? $ecoCode : null;
}

function eco_catalog_all(): array {
  static $catalog = null;
  if (is_array($catalog)) return $catalog;

  $catalog = [];
  try {
    $sql = 'SELECT ec.eco_code, ec.family_key, f.family_name,
                   ec.opening_name, ec.variation_name
            FROM eco_codes ec
            JOIN opening_families f ON f.family_key=ec.family_key
            ORDER BY ec.eco_code ASC';
    foreach (db()->query($sql)->fetchAll() as $row) {
      $code = eco_catalog_normalize_code($row['eco_code'] ?? null);
      if ($code !== null) $catalog[$code] = $row;
    }
  } catch (Throwable $e) {
    // Deployments remain usable while the catalog migration is pending.
  }

  return $catalog;
}

function eco_catalog_lookup(?string $ecoCode): ?array {
  $code = eco_catalog_normalize_code($ecoCode);
  if ($code === null) return null;
  $catalog = eco_catalog_all();
  return $catalog[$code] ?? null;
}

function eco_catalog_resolve_labels(?string $ecoCode, ?string $preferredOpeningName, ?array $catalogEntry): array {
  $rawCode = trim((string)$ecoCode);
  $normalizedCode = eco_catalog_normalize_code($rawCode);
  $preferredOpeningName = trim((string)$preferredOpeningName);
  $catalogOpeningName = trim((string)($catalogEntry['opening_name'] ?? ''));
  $catalogVariationName = trim((string)($catalogEntry['variation_name'] ?? ''));

  $openingName = $preferredOpeningName !== '' ? $preferredOpeningName : $catalogOpeningName;

  return [
    'eco_code' => $normalizedCode ?? ($rawCode !== '' ? substr($rawCode, 0, 10) : null),
    'family_key' => $catalogEntry['family_key'] ?? null,
    'family_name' => $catalogEntry['family_name'] ?? null,
    'opening_name' => $openingName !== '' ? substr($openingName, 0, 255) : null,
    'variation_name' => $catalogVariationName !== '' ? substr($catalogVariationName, 0, 255) : null,
    'catalog_opening_name' => $catalogOpeningName !== '' ? $catalogOpeningName : null,
    'catalog_variation_name' => $catalogVariationName !== '' ? $catalogVariationName : null,
    'label_source' => $preferredOpeningName !== '' ? 'pgn' : ($catalogEntry ? 'catalog' : 'unknown'),
    'catalog_match' => $catalogEntry !== null,
  ];
}

function eco_catalog_resolve(?string $ecoCode, ?string $preferredOpeningName = null): array {
  return eco_catalog_resolve_labels(
    $ecoCode,
    $preferredOpeningName,
    eco_catalog_lookup($ecoCode)
  );
}
