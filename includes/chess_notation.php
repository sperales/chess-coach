<?php
require_once __DIR__ . '/chess_server.php';

function chess_state_from_fen(string $fen): ?array {
  $parts = preg_split('/\s+/', trim($fen));
  if (!$parts || count($parts) < 4) return null;

  $ranks = explode('/', $parts[0]);
  if (count($ranks) !== 8 || !in_array($parts[1], ['w', 'b'], true)) return null;

  $board = [];
  foreach ($ranks as $rankIndex => $rank) {
    $row = [];
    foreach (str_split($rank) as $char) {
      if (ctype_digit($char)) {
        $empty = (int)$char;
        if ($empty < 1 || $empty > 8) return null;
        for ($i = 0; $i < $empty; $i++) $row[] = '';
      } elseif (strpos('prnbqkPRNBQK', $char) !== false) {
        $row[] = $char;
      } else {
        return null;
      }
    }
    if (count($row) !== 8) return null;
    $board[$rankIndex] = $row;
  }

  $castle = $parts[2] === '-' ? '' : $parts[2];
  if ($castle !== '' && !preg_match('/^[KQkq]+$/', $castle)) return null;
  $ep = strtolower($parts[3]);
  if ($ep !== '-' && !preg_match('/^[a-h][36]$/', $ep)) return null;

  return [
    'b' => $board,
    'turn' => $parts[1],
    'castle' => $castle,
    'ep' => $ep,
    'half' => isset($parts[4]) ? max(0, (int)$parts[4]) : 0,
    'full' => isset($parts[5]) ? max(1, (int)$parts[5]) : 1,
  ];
}

function chess_move_to_uci(array $move): string {
  $uci = rf_to_sq($move['fr'], $move['ff']) . rf_to_sq($move['tr'], $move['tf']);
  if (!empty($move['promo'])) $uci .= strtolower((string)$move['promo']);
  return $uci;
}

function chess_uci_to_san(string $fenBefore, string $uci): ?string {
  $state = chess_state_from_fen($fenBefore);
  $uci = strtolower(trim($uci));
  if (!$state || !preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $uci)) return null;

  $legalMoves = pseudo_moves($state);
  $move = null;
  foreach ($legalMoves as $candidate) {
    if (chess_move_to_uci($candidate) === $uci) {
      $move = $candidate;
      break;
    }
  }
  if (!$move) return null;

  $piece = $state['b'][$move['fr']][$move['ff']];
  if ($piece === '') return null;
  if (!empty($move['castle'])) {
    $san = $move['tf'] === 6 ? 'O-O' : 'O-O-O';
  } else {
    $pieceType = strtoupper($piece);
    $isPawn = $pieceType === 'P';
    $isCapture = $state['b'][$move['tr']][$move['tf']] !== '' || !empty($move['epcap']);
    $san = $isPawn ? '' : $pieceType;

    if (!$isPawn) {
      $alternatives = [];
      foreach ($legalMoves as $candidate) {
        if ($candidate['fr'] === $move['fr'] && $candidate['ff'] === $move['ff']) continue;
        if ($candidate['tr'] !== $move['tr'] || $candidate['tf'] !== $move['tf']) continue;
        $candidatePiece = $state['b'][$candidate['fr']][$candidate['ff']];
        if (strtoupper($candidatePiece) === $pieceType) $alternatives[] = $candidate;
      }
      if ($alternatives) {
        $sameFile = false;
        $sameRank = false;
        foreach ($alternatives as $alternative) {
          if ($alternative['ff'] === $move['ff']) $sameFile = true;
          if ($alternative['fr'] === $move['fr']) $sameRank = true;
        }
        if (!$sameFile) {
          $san .= chr(97 + $move['ff']);
        } elseif (!$sameRank) {
          $san .= (string)(8 - $move['fr']);
        } else {
          $san .= chr(97 + $move['ff']) . (string)(8 - $move['fr']);
        }
      }
    } elseif ($isCapture) {
      $san .= chr(97 + $move['ff']);
    }

    if ($isCapture) $san .= 'x';
    $san .= rf_to_sq($move['tr'], $move['tf']);
    if (!empty($move['promo'])) $san .= '=' . strtoupper((string)$move['promo']);
  }

  $nextState = apply_move($state, $move);
  if (in_check($nextState, $nextState['turn'])) {
    $san .= count(pseudo_moves($nextState)) === 0 ? '#' : '+';
  }
  return $san;
}

function chess_uci_fallback(string $uci): string {
  $uci = strtolower(trim($uci));
  if (!preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $uci, $matches)) {
    return $uci !== '' ? $uci : 'Jugada no disponible';
  }
  $display = $matches[1] . ' → ' . $matches[2];
  if (!empty($matches[3])) $display .= '=' . strtoupper($matches[3]);
  return $display;
}

function chess_uci_display(string $fenBefore, string $uci): string {
  return chess_uci_to_san($fenBefore, $uci) ?? chess_uci_fallback($uci);
}

function chess_uci_presentations(string $fenBefore, array $moves): array {
  $presentations = [];
  foreach ($moves as $move) {
    $uci = strtolower(trim((string)$move));
    if (!preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $uci)) continue;
    $san = chess_uci_to_san($fenBefore, $uci);
    $presentations[] = [
      'uci' => $uci,
      'san' => $san,
      'display' => $san ?? chess_uci_fallback($uci),
    ];
  }
  return $presentations;
}
