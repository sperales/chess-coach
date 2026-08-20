<?php
require_once __DIR__ . '/../includes/chess_notation.php';
require_once __DIR__ . '/../includes/chess_evaluation.php';

function assert_interactive(bool $condition, string $message): void {
  if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}

$start = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
assert_interactive(chess_validate_strict_fen($start) === $start, 'A complete legal-shape FEN must normalize.');
assert_interactive(chess_validate_strict_fen('8/8/8/8/8/8/8/8 w - -') === null, 'Strict validation must require six FEN fields.');
assert_interactive(chess_validate_strict_fen('8/8/8/8/8/8/8/8 w - - x 1') === null, 'Strict validation must reject invalid clocks.');

$legal = chess_legal_uci_moves($start);
assert_interactive(count($legal) === 20, 'The initial position must expose 20 legal moves.');
assert_interactive(in_array(['uci' => 'e2e4', 'san' => 'e4'], $legal, true), 'Legal moves must include SAN presentation.');

$pv = chess_normalize_pv($start, 'e2e4 e7e5 g1f3 b8c6 illegal a2a3', 12);
assert_interactive(count($pv) === 4, 'PV normalization must stop at the first invalid move.');
assert_interactive($pv[0]['san'] === 'e4' && $pv[3]['san'] === 'Nc6', 'PV SAN must use each preceding FEN.');

$problem = [
  'fen_before' => $start, 'uci' => 'a2a3', 'bestmove' => 'e2e4',
  'centipawn_loss' => 130, 'classification' => 'mistake',
  'score_before' => 20, 'score_before_type' => 'cp', 'score_after' => -110, 'score_after_type' => 'cp',
];
$assessment = chess_move_assessment($problem);
assert_interactive(chess_should_offer_best_move($problem, $assessment), 'A valid problematic move must offer Best.');
$problem['uci'] = 'e2e4';
assert_interactive(!chess_should_offer_best_move($problem), 'The played best move must not offer itself as an alternative.');
$problem['uci'] = 'a2a3'; $problem['bestmove'] = 'e2e5';
assert_interactive(!chess_should_offer_best_move($problem), 'An illegal best move must disable the control.');

echo "Interactive position domain tests passed.\n";
