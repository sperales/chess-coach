<?php
function assert_stream_ui(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$runner = file_get_contents(__DIR__.'/../includes/stockfish.php');
$service = file_get_contents(__DIR__.'/../includes/position_analysis.php');
$endpoint = file_get_contents(__DIR__.'/../api/position-analysis-stream.php');
$client = file_get_contents(__DIR__.'/../assets/js/interactive-position.js');
$board = file_get_contents(__DIR__.'/../assets/js/analysis-board.js');

assert_stream_ui(str_contains($runner, 'evalFenProgressive') && str_contains($runner, '$onOutput'), 'Stockfish must expose progressive search updates.');
assert_stream_ui(str_contains($service, '?callable $onUpdate') && str_contains($service, '$onUpdate($presented, true)'), 'Position service must distinguish progress and final results.');
assert_stream_ui(str_contains($endpoint, 'application/x-ndjson') && str_contains($endpoint, "'type' => \$final ? 'final' : 'info'"), 'Streaming endpoint must use incremental NDJSON events.');
assert_stream_ui(str_contains($client, 'response.body.getReader') && str_contains($client, 'streamAnalysis'), 'Browser client must consume streamed engine output with a synchronous fallback.');
assert_stream_ui(str_contains($board, 'renderAnalysisEngine(event.analysis') && str_contains($board, 'analysisTreeHtml'), 'Analysis Board must render live engine data and a navigable tree.');

echo "Position analysis streaming tests passed.\n";
