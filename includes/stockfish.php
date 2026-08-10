<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/chess_evaluation.php';

function stockfish_available(): array {
  $cfg = engine_config();
  $path = $cfg['stockfish_path'] ?? '';
  $pathConfigured = is_string($path) && $path !== '';
  return [
    'ok' => function_exists('proc_open') && $pathConfigured && is_file($path) && is_executable($path),
    'path_configured' => $pathConfigured,
    'proc_open' => function_exists('proc_open')
  ];
}

class StockfishException extends RuntimeException {
  private $reason;
  private $engineExitCode;
  private $stderrOutput;
  private $engineOutput;

  public function __construct(
    string $message,
    string $reason,
    ?int $engineExitCode = null,
    string $stderrOutput = '',
    string $engineOutput = ''
  ) {
    parent::__construct($message);
    $this->reason = $reason;
    $this->engineExitCode = $engineExitCode;
    $this->stderrOutput = $stderrOutput;
    $this->engineOutput = $engineOutput;
  }

  public function reason(): string { return $this->reason; }
  public function engineExitCode(): ?int { return $this->engineExitCode; }
  public function stderrOutput(): string { return $this->stderrOutput; }
  public function engineOutput(): string { return $this->engineOutput; }
}

function stockfish_valid_uci_move(string $move): bool {
  return (bool)preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', strtolower(trim($move)));
}

function stockfish_normalize_moves(array $moves): array {
  $normalized = [];
  foreach ($moves as $move) {
    $uci = strtolower(trim((string)$move));
    if (!stockfish_valid_uci_move($uci)) {
      throw new InvalidArgumentException('El historial contiene una jugada UCI no válida.');
    }
    $normalized[] = $uci;
  }
  return $normalized;
}

function stockfish_startpos_command(array $moves): string {
  $history = stockfish_normalize_moves($moves);
  return $history ? 'position startpos moves '.implode(' ', $history) : 'position startpos';
}

function stockfish_parse_engine_identity(string $output, ?string $configuredBuild = null): array {
  $name = 'Stockfish';
  if (preg_match('/^id name\s+(.+)$/mi', $output, $match)) {
    $candidate = trim($match[1]);
    if ($candidate !== '') $name = substr($candidate, 0, 80);
  }
  $version = null;
  if (preg_match('/\b(\d+(?:\.\d+){0,2})\b/', $name, $match)) {
    $version = substr($match[1], 0, 40);
  }
  $build = trim((string)$configuredBuild);
  return [
    'name' => $name,
    'version' => $version,
    'build' => $build !== '' ? substr($build, 0, 80) : null,
  ];
}

class StockfishRunner {
  private $process;
  private $pipes = [];
  private $depth;
  private $movetime;
  private $minimumDepth;
  private $searchTimeout;
  private $identity = ['name' => 'Stockfish', 'version' => null, 'build' => null];
  private $exitCode = null;
  private $stderrBuffer = '';

  public function __construct(array $cfg) {
    $path = $cfg['stockfish_path'] ?? '';
    if (!function_exists('proc_open')) throw new Exception('El hosting tiene proc_open deshabilitado. No se puede ejecutar Stockfish en este servidor.');
    if (!is_file($path) || !is_executable($path)) throw new Exception('Stockfish no está disponible o no es ejecutable. Revisa config/engine.php.');

    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $this->process = proc_open($path, $desc, $this->pipes);
    if (!is_resource($this->process)) throw new Exception('No se pudo arrancar Stockfish.');

    $this->depth = max(1, (int)($cfg['depth'] ?? 10));
    $this->movetime = max(0, (int)($cfg['movetime_ms'] ?? 0));
    $this->minimumDepth = $this->movetime > 0
      ? max(1, (int)($cfg['minimum_depth'] ?? 1))
      : $this->depth;
    $this->searchTimeout = max(10, (int)($cfg['search_timeout_seconds'] ?? 60));
    stream_set_blocking($this->pipes[1], false);
    stream_set_blocking($this->pipes[2], false);

    try {
      $this->send('uci');
      $uciOutput = $this->readUntil('uciok', 10);
      $this->identity = stockfish_parse_engine_identity($uciOutput, $cfg['engine_build'] ?? null);
      $this->setOption('Threads', (string)max(1, (int)($cfg['threads'] ?? 1)));
      $this->setOption('Hash', (string)max(1, (int)($cfg['hash_mb'] ?? 32)));
      $this->setOption('MultiPV', '1');
      $this->send('isready');
      $this->readUntil('readyok', 10);
      $this->newGame();
    } catch (Throwable $e) {
      $this->close();
      throw $e;
    }
  }

  public function identity(): array {
    return $this->identity;
  }

  public function searchProfile(): array {
    return [
      'mode' => $this->movetime > 0 ? 'movetime' : 'depth',
      'value' => $this->movetime > 0 ? $this->movetime : $this->depth,
      'minimum_depth' => $this->minimumDepth,
    ];
  }

  public function exitCode(): ?int {
    return $this->exitCode;
  }

  public function stderrOutput(): string {
    $this->drainStderr();
    return $this->stderrBuffer;
  }

  public function newGame(): void {
    $this->send('ucinewgame');
    $this->send('isready');
    $this->readUntil('readyok', 10);
  }

  public function evalMoves(array $moves): array {
    return $this->evaluatePosition(stockfish_startpos_command($moves), []);
  }

  public function evalFen(string $fen): array {
    return $this->evalFenWithSearchMoves($fen, []);
  }

  public function evalFenWithSearchMoves(string $fen, array $moves): array {
    if (preg_match('/[\r\n]/', $fen) || count(preg_split('/\s+/', trim($fen)) ?: []) < 4) {
      throw new InvalidArgumentException('La posición FEN no es válida.');
    }
    $searchMoves = stockfish_normalize_moves($moves);
    if ($moves && !$searchMoves) throw new InvalidArgumentException('No se indicó ninguna jugada válida para evaluar.');
    return $this->evaluatePosition('position fen '.trim($fen), $searchMoves);
  }

  public function close(): void {
    if (!is_resource($this->process)) return;
    $status = @proc_get_status($this->process);
    if (is_array($status) && empty($status['running']) && isset($status['exitcode']) && (int)$status['exitcode'] >= 0) {
      $this->exitCode = (int)$status['exitcode'];
    }
    if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
      @fwrite($this->pipes[0], "quit\n");
      @fflush($this->pipes[0]);
    }
    $this->drainStderr();
    foreach ($this->pipes as $pipe) {
      if (is_resource($pipe)) @fclose($pipe);
    }
    $exit = @proc_close($this->process);
    if (is_int($exit) && $exit >= 0) $this->exitCode = $exit;
    $this->process = null;
    $this->pipes = [];
  }

  public function __destruct() {
    $this->close();
  }

  private function evaluatePosition(string $positionCommand, array $searchMoves): array {
    $this->send($positionCommand);
    $go = $this->movetime > 0 ? 'go movetime '.$this->movetime : 'go depth '.$this->depth;
    if ($searchMoves) $go .= ' searchmoves '.implode(' ', array_values(array_unique($searchMoves)));
    $this->send($go);
    $timeout = $this->movetime > 0
      ? max($this->searchTimeout, (int)ceil($this->movetime / 1000) + 10)
      : $this->searchTimeout;
    $output = $this->readUntil('bestmove', $timeout);
    $evaluation = stockfish_parse_output($output);
    stockfish_assert_complete_evaluation($evaluation, $this->minimumDepth);
    $evaluation['engine_name'] = $this->identity['name'];
    $evaluation['engine_version'] = $this->identity['version'];
    return $evaluation;
  }

  private function setOption(string $name, string $value): void {
    $this->send('setoption name '.$name.' value '.$value);
  }

  private function send(string $cmd): void {
    if (!isset($this->pipes[0]) || !is_resource($this->pipes[0])) {
      throw new StockfishException('Stockfish no está disponible.', 'process_unavailable', $this->exitCode, $this->stderrOutput());
    }
    $written = @fwrite($this->pipes[0], $cmd."\n");
    @fflush($this->pipes[0]);
    if ($written === false || $written === 0) {
      throw new StockfishException('No se pudo enviar el comando a Stockfish.', 'write_failed', $this->exitCode, $this->stderrOutput());
    }
  }

  private function readUntil(string $needle, int $timeout = 8): string {
    $buffer = '';
    $start = microtime(true);
    while ((microtime(true) - $start) < $timeout) {
      $chunk = stream_get_contents($this->pipes[1]);
      if ($chunk !== false && $chunk !== '') {
        $buffer .= $chunk;
        if (strpos($buffer, $needle) !== false) {
          $this->drainStderr();
          return $buffer;
        }
      }
      $this->drainStderr();
      $status = @proc_get_status($this->process);
      if (is_array($status) && empty($status['running'])) {
        $exitCode = isset($status['exitcode']) && (int)$status['exitcode'] >= 0 ? (int)$status['exitcode'] : null;
        if ($exitCode !== null) $this->exitCode = $exitCode;
        throw new StockfishException(
          'Stockfish terminó antes de completar la evaluación.',
          'process_exit',
          $exitCode,
          $this->stderrBuffer,
          substr($buffer, -2000)
        );
      }
      usleep(50000);
    }
    @fwrite($this->pipes[0], "stop\n");
    @fflush($this->pipes[0]);
    throw new StockfishException(
      'Stockfish superó el tiempo máximo de evaluación.',
      'timeout',
      $this->exitCode,
      $this->stderrBuffer,
      substr($buffer, -2000)
    );
  }

  private function drainStderr(): void {
    if (!isset($this->pipes[2]) || !is_resource($this->pipes[2])) return;
    $chunk = stream_get_contents($this->pipes[2]);
    if ($chunk !== false && $chunk !== '') {
      $this->stderrBuffer = substr($this->stderrBuffer.$chunk, -4000);
    }
  }
}

function stockfish_runner(): StockfishRunner {
  return new StockfishRunner(engine_config());
}

function stockfish_process_lock_enabled(): bool {
  $cfg = engine_config();
  return !array_key_exists('serialize_stockfish_processes', $cfg)
    || !empty($cfg['serialize_stockfish_processes']);
}

function stockfish_process_lock_acquire(): bool {
  if (!stockfish_process_lock_enabled()) return true;
  $st = db()->query("SELECT GET_LOCK('chess_coach_stockfish_engine', 0)");
  return (int)$st->fetchColumn() === 1;
}

function stockfish_process_lock_release(): void {
  if (!stockfish_process_lock_enabled()) return;
  try {
    db()->query("SELECT RELEASE_LOCK('chess_coach_stockfish_engine')");
  } catch (Throwable $error) {
    // The connection also releases the advisory lock when the request ends.
  }
}

function stockfish_parse_output(string $output): array {
  $bestmoveRaw = null;
  if (preg_match('/^bestmove\s+(\S+)/mi', $output, $match)) {
    $bestmoveRaw = strtolower(trim($match[1]));
  }

  $metrics = [
    'score_type' => null,
    'score' => null,
    'depth' => null,
    'seldepth' => null,
    'nodes' => null,
    'time_ms' => null,
    'nps' => null,
    'hashfull' => null,
    'tbhits' => null,
    'pv' => [],
  ];

  foreach (preg_split('/\R/', $output) ?: [] as $line) {
    $line = trim($line);
    if (!str_starts_with($line, 'info ') || !preg_match('/\bscore\s+(cp|mate)\s+(-?\d+)/', $line, $scoreMatch)) continue;
    $candidate = $metrics;
    $candidate['score_type'] = $scoreMatch[1];
    $candidate['score'] = (int)$scoreMatch[2];
    foreach ([
      'depth' => '/\bdepth\s+(\d+)/',
      'seldepth' => '/\bseldepth\s+(\d+)/',
      'nodes' => '/\bnodes\s+(\d+)/',
      'time_ms' => '/\btime\s+(\d+)/',
      'nps' => '/\bnps\s+(\d+)/',
      'hashfull' => '/\bhashfull\s+(\d+)/',
      'tbhits' => '/\btbhits\s+(\d+)/',
    ] as $key => $pattern) {
      if (preg_match($pattern, $line, $metricMatch)) $candidate[$key] = (int)$metricMatch[1];
    }
    if (preg_match('/\bpv\s+(.+)$/', $line, $pvMatch)) {
      $candidate['pv'] = array_values(array_filter(
        preg_split('/\s+/', trim($pvMatch[1])) ?: [],
        fn($move) => stockfish_valid_uci_move((string)$move)
      ));
    }
    $metrics = $candidate;
  }

  $terminal = in_array($bestmoveRaw, ['(none)', 'none', '0000'], true);
  return array_merge($metrics, [
    'bestmove' => $bestmoveRaw !== null && stockfish_valid_uci_move($bestmoveRaw) ? $bestmoveRaw : null,
    'bestmove_raw' => $bestmoveRaw,
    'terminal' => $terminal,
    'complete' => $bestmoveRaw !== null && $metrics['score'] !== null && $metrics['score_type'] !== null,
    'raw' => substr($output, -2000),
  ]);
}

function stockfish_assert_complete_evaluation(array $evaluation, int $minimumDepth): void {
  if (empty($evaluation['complete'])) {
    throw new StockfishException('Stockfish devolvió una evaluación incompleta.', 'incomplete_output', null, '', (string)($evaluation['raw'] ?? ''));
  }
  $terminal = !empty($evaluation['terminal']);
  if (!$terminal && !stockfish_valid_uci_move((string)($evaluation['bestmove'] ?? ''))) {
    throw new StockfishException('Stockfish no devolvió una mejor jugada válida.', 'invalid_bestmove', null, '', (string)($evaluation['raw'] ?? ''));
  }
  if (!$terminal && (int)($evaluation['depth'] ?? 0) < max(1, $minimumDepth)) {
    throw new StockfishException('Stockfish no alcanzó la profundidad mínima configurada.', 'minimum_depth', null, '', (string)($evaluation['raw'] ?? ''));
  }
  if (!$terminal && (int)($evaluation['nodes'] ?? 0) < 1) {
    throw new StockfishException('Stockfish no informó nodos de búsqueda válidos.', 'missing_nodes', null, '', (string)($evaluation['raw'] ?? ''));
  }
}

function stockfish_eval_fen(string $fen): array {
  $runner = stockfish_runner();
  try {
    return $runner->evalFen($fen);
  } finally {
    $runner->close();
  }
}

function normalize_eval_for_side(array $evaluation, string $turn): int {
  if (!array_key_exists('score', $evaluation) || $evaluation['score'] === null) {
    throw new UnexpectedValueException('No se puede normalizar una evaluación ausente.');
  }
  if (($evaluation['score_type'] ?? null) === 'mate') {
    $value = (int)$evaluation['score'];
    $centipawns = $value > 0 ? 100000 - abs($value) * 1000 : -100000 + abs($value) * 1000;
  } elseif (($evaluation['score_type'] ?? null) === 'cp') {
    $centipawns = (int)$evaluation['score'];
  } else {
    throw new UnexpectedValueException('El tipo de evaluación de Stockfish no es válido.');
  }
  return $turn === 'w' ? $centipawns : -$centipawns;
}
