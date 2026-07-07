<?php

function human_move_notation(?string $uci): string {
  $uci = strtolower(trim((string)$uci));
  if ($uci === '' || $uci === '(none)' || $uci === '0000') return 'no disponible';

  if (in_array($uci, ['e1g1', 'e8g8'], true)) return 'O-O';
  if (in_array($uci, ['e1c1', 'e8c8'], true)) return 'O-O-O';

  if (!preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $uci, $m)) {
    return $uci;
  }

  $text = $m[1].' -> '.$m[2];
  if (!empty($m[3])) $text .= '='.strtoupper($m[3]);
  return $text;
}
