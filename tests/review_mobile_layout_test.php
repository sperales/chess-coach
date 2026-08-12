<?php

function assert_review_mobile(bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, $message."\n");
    exit(1);
  }
}

$review = file_get_contents(__DIR__.'/../review.php');
$script = file_get_contents(__DIR__.'/../assets/js/review.js');
$styles = file_get_contents(__DIR__.'/../assets/css/app.css');

assert_review_mobile(str_contains($review, 'data-review-expanded="false"'), 'Mobile review must begin with analysis details collapsed.');
preg_match_all('/<button[^>]+data-review-tab="[^"]+"/', $review, $tabButtons);
assert_review_mobile(count($tabButtons[0]) === 4, 'Mobile review must expose exactly four analysis tabs.');
assert_review_mobile(str_contains($review, 'review-mobile-feedback'), 'The current move explanation must remain visible below the mobile board.');
assert_review_mobile(str_contains($review, "nova_avatar_html('neutral'"), 'The Coach view must use the shared Nova component.');
assert_review_mobile(str_contains($review, 'review-mobile-sheet-body'), 'Detailed review panels must render inside the mobile overlay sheet.');
assert_review_mobile(str_contains($script, "const allowed = ['summary', 'analysis', 'moves', 'coach']"), 'Review tab selection must use an explicit allowlist.');
assert_review_mobile(str_contains($script, 'renderReviewMobileSheet()'), 'Changing tabs must render dedicated content inside the mobile sheet.');
assert_review_mobile(str_contains($script, 'sq-coordinate-rank'), 'Board coordinates must be rendered inside the mobile squares.');
assert_review_mobile(str_contains($styles, '@media(max-width:760px)'), 'Mobile review rules must be isolated from desktop layouts.');
assert_review_mobile(str_contains($styles, '.review-shell .topbar'), 'The dedicated mobile app bar must replace the global header only in review.');
assert_review_mobile(str_contains($styles, 'position:fixed') && str_contains($styles, '.review-mobile-sheet[aria-hidden="false"]'), 'The mobile analysis must open as an overlay sheet.');

echo "Review mobile layout tests passed.\n";
