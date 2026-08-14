<?php

function assert_login_autocomplete(bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, $message."\n");
    exit(1);
  }
}

$page = file_get_contents(__DIR__.'/../index.php');

assert_login_autocomplete(str_contains($page, '<form class="login-form" method="post" action="index.php" autocomplete="on"'), 'Login form must allow password-manager autocomplete.');
assert_login_autocomplete(str_contains($page, 'id="username" name="username" type="text"'), 'Username input must use standard password-manager identifiers.');
assert_login_autocomplete(str_contains($page, 'autocomplete="username"'), 'Username input must expose the username autocomplete token.');
assert_login_autocomplete(str_contains($page, 'id="password" name="password" type="password"'), 'Password input must use standard password-manager identifiers.');
assert_login_autocomplete(str_contains($page, 'autocomplete="current-password"'), 'Password input must expose the current-password autocomplete token.');
assert_login_autocomplete(!str_contains($page, 'id="loginUsername"'), 'Legacy username id must not confuse iOS password heuristics.');

echo "Login autocomplete tests passed.\n";
