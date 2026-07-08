<?php
require_once __DIR__.'/config.php';

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

class StockfishRunner {
  private $process;
  private $pipes = [];
  private $depth;
  private $movetime;

  public function __construct(array $cfg) {
    $path = $cfg['stockfish_path'] ?? '';
    if (!function_exists('proc_open')) throw new Exception('El hosting tiene proc_open deshabilitado. No se puede ejecutar Stockfish en este servidor.');
    if (!is_file($path) || !is_executable($path)) throw new Exception('Stockfish no esta disponible o no es ejecutable. Revisa config/engine.php.');

    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $this->process = proc_open($path, $desc, $this->pipes);
    if (!is_resource($this->process)) throw new Exception('No se pudo arrancar Stockfish.');

    $this->depth = (int)($cfg['depth'] ?? 10);
    $this->movetime = (int)($cfg['movetime_ms'] ?? 0);
    stream_set_blocking($this->pipes[1], false);

    $this->send('uci');
    $this->readUntil('uciok');
    $this->setOption('Threads', (string)max(1, (int)($cfg['threads'] ?? 1)));
    $this->setOption('Hash', (string)max(1, (int)($cfg['hash_mb'] ?? 32)));
    $this->send('isready');
    $this->readUntil('readyok');
    $this->send('ucinewgame');
    $this->send('isready');
    $this->readUntil('readyok');
  }

  public function evalFen(string $fen): array {
    $this->send('position fen '.$fen);
    $this->send($this->movetime > 0 ? 'go movetime '.$this->movetime : 'go depth '.$this->depth);
    $timeout = $this->movetime > 0 ? max(10, (int)ceil($this->movetime / 1000) + 5) : 10;
    return stockfish_parse_output($this->readUntil('bestmove', $timeout));
  }

  public function close(): void {
    if (!is_resource($this->process)) return;
    if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
      @fwrite($this->pipes[0], "quit\n");
    }
    foreach ($this->pipes as $pipe) {
      if (is_resource($pipe)) fclose($pipe);
    }
    proc_close($this->process);
    $this->process = null;
    $this->pipes = [];
  }

  public function __destruct() {
    $this->close();
  }

  private function setOption(string $name, string $value): void {
    $this->send('setoption name '.$name.' value '.$value);
  }

  private function send(string $cmd): void {
    if (!isset($this->pipes[0]) || !is_resource($this->pipes[0])) throw new Exception('Stockfish no esta disponible.');
    fwrite($this->pipes[0], $cmd."\n");
  }

  private function readUntil(string $needle, int $timeout = 8): string {
    $buf = '';
    $start = microtime(true);
    while ((microtime(true) - $start) < $timeout) {
      $chunk = stream_get_contents($this->pipes[1]);
      if ($chunk !== false && $chunk !== '') {
        $buf .= $chunk;
        if (strpos($buf, $needle) !== false) break;
      }
      usleep(50000);
    }
    return $buf;
  }
}

function stockfish_runner(): StockfishRunner {
  return new StockfishRunner(engine_config());
}

function stockfish_parse_output(string $out): array {
  $best = null;
  if (preg_match('/bestmove\s+(\S+)/', $out, $m)) $best = $m[1];

  $scoreType = 'cp';
  $score = 0;
  if (preg_match_all('/score\s+(cp|mate)\s+(-?\d+)/', $out, $ms, PREG_SET_ORDER)) {
    $last = end($ms);
    $scoreType = $last[1];
    $score = (int)$last[2];
  }

  return ['bestmove' => $best, 'score_type' => $scoreType, 'score' => $score, 'raw' => substr($out, -2000)];
}

function stockfish_eval_fen(string $fen): array {
  $runner = stockfish_runner();
  try {
    return $runner->evalFen($fen);
  } finally {
    $runner->close();
  }
}

function normalize_eval_for_side(array $ev, string $turn): int {
  if (($ev['score_type'] ?? 'cp') === 'mate') {
    $v = (int)$ev['score'];
    $cp = $v > 0 ? 100000 - abs($v) * 1000 : -100000 + abs($v) * 1000;
  } else {
    $cp = (int)$ev['score'];
  }
  return $turn === 'w' ? $cp : -$cp;
}

function classify_loss(int $loss): string {
  if ($loss >= 300) return 'blunder';
  if ($loss >= 150) return 'mistake';
  if ($loss >= 70) return 'inaccuracy';
  return 'ok';
}
