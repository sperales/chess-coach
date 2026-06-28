<?php
function http_get_text(string $url): string {
  $opts=['http'=>['method'=>'GET','header'=>"User-Agent: ChessCoachPWA/0.6.0 (personal training app)\r\nAccept: application/json,text/plain,*/*\r\n",'timeout'=>20]];
  $data=@file_get_contents($url,false,stream_context_create($opts));
  if($data===false) throw new Exception('No se pudo consultar: '.$url);
  return $data;
}
function chesscom_archives(string $username): array {
  $u=strtolower(trim($username)); if(!preg_match('/^[a-z0-9_-]{2,40}$/i',$u)) throw new Exception('Usuario de Chess.com no válido.');
  $json=http_get_text('https://api.chess.com/pub/player/'.rawurlencode($u).'/games/archives');
  $data=json_decode($json,true); if(!isset($data['archives'])||!is_array($data['archives'])) throw new Exception('Chess.com no devolvió archivos de partidas.');
  return $data['archives'];
}
function chesscom_fetch_pgns(string $username, int $limit=20): string {
  $archives=chesscom_archives($username); $archives=array_reverse($archives); $all=''; $count=0;
  foreach($archives as $a){ $pgnUrl=$a.'/pgn'; $txt=http_get_text($pgnUrl); if(trim($txt)==='')continue; $chunks=preg_split('/\n\s*\n(?=\[Event )/', trim($txt)); foreach($chunks as $p){ if(trim($p)==='')continue; $all.=trim($p)."\n\n"; $count++; if($count>=$limit) return $all; } }
  return $all;
}
