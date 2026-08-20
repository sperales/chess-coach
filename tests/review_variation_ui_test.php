<?php
function assert_variation_ui(bool $condition, string $message): void {
  if (!$condition) { fwrite(STDERR, $message."\n"); exit(1); }
}
$page = file_get_contents(__DIR__.'/../review.php');
$script = file_get_contents(__DIR__.'/../assets/js/review.js');
$api = file_get_contents(__DIR__.'/../api/position-analysis.php');
$menu = file_get_contents(__DIR__.'/../includes/helpers.php');

assert_variation_ui(str_contains($page, 'reviewVariationPanel') && str_contains($page, 'Explorando variante'), 'Review must contain its integrated variation mode.');
assert_variation_ui(str_contains($page, 'variationReturn') && !str_contains($page, 'variationExit'), 'Variation must expose one clear return action in its upper context banner.');
assert_variation_ui(!str_contains($page, 'id="exploreVariationBtn"') && !str_contains($page, '>Reiniciar</button>'), 'Main Review controls must stay compact and delegate exploration to Analysis.');
assert_variation_ui(str_contains($page, 'review-best-icon') && str_contains($page, '>★</button>'), 'Best move control must use the compact star affordance.');
assert_variation_ui(!str_contains($page, 'data-variation-tab='), 'Variation mode must not duplicate the Review bottom-sheet tabs.');
assert_variation_ui(str_contains($script, 'originAnalysis.analysis.pv_moves'), 'Variation must populate the complete Stockfish continuation, not only its first move.');
assert_variation_ui(str_contains($script, 'm.bestmove_fen_after'), 'Best must render the resulting alternative position.');
assert_variation_ui(str_contains($script, 'bestMoveBtn.disabled = !m.can_show_bestmove'), 'Best must remain visible and use an enabled policy.');
assert_variation_ui(str_contains($script, 'originIndex') && str_contains($script, 'originTab'), 'Variation must retain Review context.');
assert_variation_ui(str_contains($script, 'PositionTree') && str_contains($script, 'selectVariationSquare'), 'Variation must preserve in-memory branches.');
assert_variation_ui(str_contains($api, 'stored_review') && str_contains($api, 'position_analysis_service'), 'Position analysis must prefer stored Review data before Stockfish.');
assert_variation_ui(str_contains($menu, "'Tablero de análisis'"), 'Analysis Board must be available in the hamburger menu.');

echo "Review variation UI tests passed.\n";
