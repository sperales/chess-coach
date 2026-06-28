<?php
function split_pgns(string $txt): array {
  $txt = trim($txt);
  if ($txt === '') return [];
  $parts = preg_split('/
\s*
(?=\[Event\s+")/m', $txt);
  return array_values(array_filter(array_map('trim', $parts)));
}
function pgn_tag(string $pgn, string $tag): string {
  return preg_match('/\['.preg_quote($tag,'/').'\s+"([^"]*)"\]/', $pgn, $m) ? $m[1] : '';
}
function result_for_user(string $pgn, string $username): string {
  $res=pgn_tag($pgn,'Result'); $w=pgn_tag($pgn,'White'); $b=pgn_tag($pgn,'Black');
  if($res==='1/2-1/2') return 'draw';
  $isW=strtolower($w)===strtolower($username); $isB=strtolower($b)===strtolower($username);
  if(($isW&&$res==='1-0')||($isB&&$res==='0-1')) return 'win';
  if(($isW&&$res==='0-1')||($isB&&$res==='1-0')) return 'loss';
  return 'unknown';
}
function pgn_date(?string $date): ?string {
  if ($date && preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $date)) return str_replace('.', '-', $date);
  return null;
}
