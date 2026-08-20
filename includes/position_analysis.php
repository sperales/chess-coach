<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stockfish.php';
require_once __DIR__ . '/chess_notation.php';

function position_analysis_config(): array {
  $cfg = engine_config();
  $cfg['depth'] = max(1, (int)($cfg['scenario_depth'] ?? 12));
  $cfg['movetime_ms'] = max(0, (int)($cfg['scenario_movetime_ms'] ?? 500));
  $cfg['minimum_depth'] = max(1, (int)($cfg['scenario_minimum_depth'] ?? 1));
  $cfg['search_timeout_seconds'] = max(10, (int)($cfg['scenario_timeout_seconds'] ?? 20));
  $cfg['evaluation_retries'] = max(0, (int)($cfg['scenario_retries'] ?? 1));
  return $cfg;
}

function position_analysis_profile_hash(array $cfg): string {
  return hash('sha256', json_encode([
    'depth' => (int)$cfg['depth'], 'movetime_ms' => (int)$cfg['movetime_ms'],
    'minimum_depth' => (int)$cfg['minimum_depth'], 'threads' => (int)($cfg['threads'] ?? 1),
    'hash_mb' => (int)($cfg['hash_mb'] ?? 32), 'build' => (string)($cfg['engine_build'] ?? ''),
  ], JSON_UNESCAPED_SLASHES));
}

class PositionAnalysisService {
  private array $config;
  private string $profileHash;
  private ?StockfishRunner $runner = null;
  private bool $locked = false;

  public function __construct(?array $config = null) {
    $this->config = $config ?? position_analysis_config();
    $this->profileHash = position_analysis_profile_hash($this->config);
  }

  public function analyze(string $fen): array {
    $normalizedFen = chess_validate_strict_fen($fen);
    if ($normalizedFen === null) throw new InvalidArgumentException('FEN no válido.');
    $cached = $this->cached($normalizedFen);
    if ($cached) return $this->present($normalizedFen, $cached + ['cached' => true, 'source' => 'cache']);

    $runner = $this->runner();
    $attempts = max(1, 1 + (int)($this->config['evaluation_retries'] ?? 1));
    $lastError = null;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
      try {
        $result = $runner->evalFen($normalizedFen);
        $this->store($normalizedFen, $result);
        return $this->present($normalizedFen, $result + ['cached' => false, 'source' => 'engine']);
      } catch (Throwable $error) {
        $lastError = $error;
        if ($attempt < $attempts) {
          $this->closeRunner();
          $runner = $this->runner();
        }
      }
    }
    error_log('[position-analysis] failure fen=' . substr(hash('sha256', $normalizedFen), 0, 12) . ' error=' . ($lastError?->getMessage() ?? 'unknown'));
    throw $lastError ?: new RuntimeException('No se pudo evaluar la posición.');
  }

  public function close(): void {
    $this->closeRunner();
    if ($this->locked) {
      stockfish_process_lock_release();
      $this->locked = false;
    }
  }

  public function __destruct() { $this->close(); }

  private function present(string $fen, array $result): array {
    $score = isset($result['score']) ? (int)$result['score'] : null;
    $type = (string)($result['score_type'] ?? 'cp');
    $turn = explode(' ', $fen)[1] ?? 'w';
    $whiteScore = $score === null ? null : ($turn === 'w' ? $score : -$score);
    $bestmove = trim((string)($result['bestmove'] ?? ''));
    $max = max(2, (int)(app_config()['interactive_pv_max_plies'] ?? 12));
    $pvMoves = chess_normalize_pv($fen, $result['pv'] ?? [], $max);
    $min = max(1, (int)(app_config()['interactive_pv_min_plies'] ?? 6));
    $lastFen = $pvMoves ? $pvMoves[count($pvMoves) - 1]['fen_after'] : $fen;
    return $result + [
      'score' => $score,
      'score_type' => $type,
      'score_pov' => 'side_to_move',
      'score_white' => $whiteScore,
      'bestmove' => $bestmove !== '' ? $bestmove : null,
      'bestmove_san' => $bestmove !== '' ? (chess_uci_to_san($fen, $bestmove) ?? chess_uci_fallback($bestmove)) : null,
      'pv_moves' => $pvMoves,
      'pv_complete' => count($pvMoves) >= $min || chess_position_is_terminal($lastFen),
    ];
  }

  private function runner(): StockfishRunner {
    if ($this->runner) return $this->runner;
    if (!$this->locked && !stockfish_process_lock_acquire()) {
      throw new RuntimeException('Stockfish está ocupado. Inténtalo de nuevo en unos segundos.');
    }
    $this->locked = true;
    $this->runner = new StockfishRunner($this->config);
    return $this->runner;
  }

  private function closeRunner(): void {
    if ($this->runner) $this->runner->close();
    $this->runner = null;
  }

  private function cached(string $fen): ?array {
    $st = db()->prepare('SELECT * FROM training_scenario_engine_cache WHERE fen_hash=? AND profile_hash=? AND expires_at>NOW()');
    $st->execute([hash('sha256', $fen), $this->profileHash]);
    $row = $st->fetch();
    if (!$row) return null;
    return [
      'score' => (int)$row['score'], 'score_type' => (string)$row['score_type'], 'bestmove' => $row['bestmove'],
      'pv' => array_values(array_filter(explode(' ', (string)($row['pv_uci'] ?? '')))),
      'depth' => $row['depth'] === null ? null : (int)$row['depth'], 'nodes' => $row['nodes'] === null ? null : (int)$row['nodes'],
      'time_ms' => $row['time_ms'] === null ? null : (int)$row['time_ms'],
      'engine_name' => $row['engine_name'], 'engine_version' => $row['engine_version'],
    ];
  }

  private function store(string $fen, array $result): void {
    $hours = max(1, min(720, (int)($this->config['scenario_cache_hours'] ?? 168)));
    $sql = 'INSERT INTO training_scenario_engine_cache
              (fen_hash,profile_hash,fen,score,score_type,bestmove,pv_uci,depth,nodes,time_ms,engine_name,engine_version,created_at,expires_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? HOUR))
            ON DUPLICATE KEY UPDATE score=VALUES(score),score_type=VALUES(score_type),bestmove=VALUES(bestmove),pv_uci=VALUES(pv_uci),
              depth=VALUES(depth),nodes=VALUES(nodes),time_ms=VALUES(time_ms),engine_name=VALUES(engine_name),engine_version=VALUES(engine_version),
              created_at=NOW(),expires_at=VALUES(expires_at)';
    db()->prepare($sql)->execute([
      hash('sha256', $fen), $this->profileHash, $fen, (int)$result['score'], (string)$result['score_type'],
      $result['bestmove'] ?? null, implode(' ', $result['pv'] ?? []), $result['depth'] ?? null, $result['nodes'] ?? null,
      $result['time_ms'] ?? null, $result['engine_name'] ?? null, $result['engine_version'] ?? null, $hours,
    ]);
  }
}

function position_analysis_service(): PositionAnalysisService {
  static $service = null;
  if (!$service) $service = new PositionAnalysisService();
  return $service;
}
