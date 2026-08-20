<?php
require_once __DIR__ . '/position_analysis.php';
require_once __DIR__ . '/training_scenarios.php';

function training_scenario_engine_config(): array {
  $cfg = engine_config();
  $cfg['depth'] = max(1, (int)($cfg['scenario_depth'] ?? 12));
  $cfg['movetime_ms'] = max(0, (int)($cfg['scenario_movetime_ms'] ?? 500));
  $cfg['minimum_depth'] = max(1, (int)($cfg['scenario_minimum_depth'] ?? 1));
  $cfg['search_timeout_seconds'] = max(10, (int)($cfg['scenario_timeout_seconds'] ?? 20));
  $cfg['evaluation_retries'] = max(0, (int)($cfg['scenario_retries'] ?? 1));
  return $cfg;
}

function training_scenario_engine_profile_hash(array $cfg): string {
  return hash('sha256', json_encode([
    'depth' => (int)$cfg['depth'], 'movetime_ms' => (int)$cfg['movetime_ms'],
    'minimum_depth' => (int)$cfg['minimum_depth'], 'threads' => (int)($cfg['threads'] ?? 1),
    'hash_mb' => (int)($cfg['hash_mb'] ?? 32), 'build' => (string)($cfg['engine_build'] ?? ''),
  ], JSON_UNESCAPED_SLASHES));
}

class TrainingScenarioEngine extends PositionAnalysisService {
  public function __construct(?array $config = null) {
    parent::__construct($config ?? training_scenario_engine_config());
  }

  public function evaluate(string $fen): array {
    return $this->analyze($fen);
  }
}

function training_scenario_engine(): TrainingScenarioEngine {
  static $engine = null;
  if (!$engine) $engine = new TrainingScenarioEngine();
  return $engine;
}
