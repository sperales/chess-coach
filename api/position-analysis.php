<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/chess_notation.php';
require_once __DIR__ . '/../includes/position_analysis.php';

$user = require_login();
$userId = (int)$user['id'];
require_post_csrf();
$body = request_json_body();
$action = (string)($body['action'] ?? 'analyze');
$fen = chess_validate_strict_fen((string)($body['fen'] ?? ''));
if ($fen === null) json_response(['ok' => false, 'error' => 'La posición FEN no es válida.']);

function position_stored_review_analysis(int $userId, int $gameId, int $ply, string $fen, ?string $firstMove): ?array {
  if ($gameId <= 0 || $ply <= 0) return null;
  $sql = 'SELECT m.* FROM game_move_analysis m
          INNER JOIN game_analysis a ON a.id=m.analysis_id
          INNER JOIN games g ON g.id=a.game_id
          WHERE g.id=? AND g.user_id=? AND a.status="done" AND m.ply=?
          ORDER BY a.id DESC LIMIT 1';
  $st = db()->prepare($sql);
  $st->execute([$gameId, $userId, $ply]);
  $row = $st->fetch();
  if (!$row || trim((string)$row['fen_before']) !== $fen) return null;

  $bestmove = strtolower(trim((string)($row['bestmove'] ?? '')));
  $pv = preg_split('/\s+/', trim((string)($row['pv_before_uci'] ?? '')));
  $pv = array_values(array_filter($pv ?: []));
  if ($firstMove !== null) {
    $firstMove = strtolower(trim($firstMove));
    if (!$pv || strtolower((string)$pv[0]) !== $firstMove) return null;
  }
  $max = max(2, (int)(app_config()['interactive_pv_max_plies'] ?? 12));
  $pvMoves = chess_normalize_pv($fen, $pv, $max);
  $min = max(1, (int)(app_config()['interactive_pv_min_plies'] ?? 6));
  $lastFen = $pvMoves ? $pvMoves[count($pvMoves) - 1]['fen_after'] : $fen;
  if (count($pvMoves) < $min && !chess_position_is_terminal($lastFen)) return null;
  if (!$pvMoves && $bestmove === '') return null;

  $score = $row['score_before'] === null ? null : (int)$row['score_before'];
  $turn = explode(' ', $fen)[1] ?? 'w';
  return [
    'source' => 'stored_review', 'cached' => true,
    'score' => $score, 'score_type' => (string)($row['score_before_type'] ?? 'cp'),
    'score_pov' => 'side_to_move', 'score_white' => $score === null ? null : ($turn === 'w' ? $score : -$score),
    'bestmove' => $bestmove !== '' ? $bestmove : null,
    'bestmove_san' => $bestmove !== '' ? (chess_uci_to_san($fen, $bestmove) ?? chess_uci_fallback($bestmove)) : null,
    'pv_moves' => $pvMoves, 'pv_complete' => true,
    'depth' => $row['depth_before'] === null ? null : (int)$row['depth_before'],
    'nodes' => $row['nodes_before'] === null ? null : (int)$row['nodes_before'],
    'time_ms' => $row['time_before_ms'] === null ? null : (int)$row['time_before_ms'],
  ];
}

try {
  if ($action === 'validate') json_response(['ok' => true, 'fen' => $fen]);
  if ($action === 'moves') json_response(['ok' => true, 'fen' => $fen, 'moves' => chess_legal_uci_moves($fen)]);
  if ($action === 'apply') {
    $uci = strtolower(trim((string)($body['move_uci'] ?? '')));
    $nextFen = chess_apply_uci_to_fen($fen, $uci);
    if ($nextFen === null) json_response(['ok' => false, 'error' => 'La jugada no es legal en esta posición.']);
    json_response([
      'ok' => true, 'fen_before' => $fen, 'fen_after' => $nextFen, 'move_uci' => $uci,
      'move_san' => chess_uci_to_san($fen, $uci) ?? chess_uci_fallback($uci),
    ]);
  }
  if ($action !== 'analyze') json_response(['ok' => false, 'error' => 'Acción no disponible.']);

  $stored = position_stored_review_analysis(
    $userId, (int)($body['game_id'] ?? 0), (int)($body['ply'] ?? 0), $fen,
    isset($body['first_move']) ? (string)$body['first_move'] : null
  );
  $analysis = $stored ?? position_analysis_service()->analyze($fen);
  json_response(['ok' => true, 'fen' => $fen, 'analysis' => $analysis]);
} catch (InvalidArgumentException|RuntimeException $error) {
  json_response(['ok' => false, 'error' => public_error_message($error, 'No se pudo analizar la posición.')]);
} catch (Throwable $error) {
  error_log('[position-analysis] endpoint failure: ' . $error->getMessage());
  json_response(['ok' => false, 'error' => 'No se pudo analizar la posición.']);
}
