<?php
require_once __DIR__.'/config.php';
function stockfish_available(): array {
  $cfg=engine_config(); $path=$cfg['stockfish_path']??'';
  return ['ok'=>function_exists('proc_open')&&is_string($path)&&$path!==''&&is_file($path)&&is_executable($path),'path'=>$path,'proc_open'=>function_exists('proc_open')];
}
function stockfish_eval_fen(string $fen): array {
  $cfg=engine_config(); $path=$cfg['stockfish_path']; $depth=(int)($cfg['depth']??10); $movetime=(int)($cfg['movetime_ms']??0);
  if(!function_exists('proc_open')) throw new Exception('El hosting tiene proc_open deshabilitado. No se puede ejecutar Stockfish en este servidor.');
  if(!is_file($path)||!is_executable($path)) throw new Exception('Stockfish no está disponible o no es ejecutable. Revisa config/engine.php.');
  $desc=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']]; $p=proc_open($path,$desc,$pipes);
  if(!is_resource($p)) throw new Exception('No se pudo arrancar Stockfish.');
  stream_set_blocking($pipes[1],false);
  $send=function($cmd)use($pipes){ fwrite($pipes[0],$cmd."\n"); };
  $readUntil=function($needle,$timeout=8)use($pipes){ $buf=''; $start=time(); while(time()-$start<$timeout){ $chunk=stream_get_contents($pipes[1]); if($chunk!==false&&$chunk!==''){$buf.=$chunk; if(strpos($buf,$needle)!==false)break;} usleep(50000);} return $buf; };
  $send('uci'); $readUntil('uciok'); $send('isready'); $readUntil('readyok'); $send('position fen '.$fen); $send($movetime>0 ? 'go movetime '.$movetime : 'go depth '.$depth);
  $out=$readUntil('bestmove', max(10, (int)ceil($movetime/1000)+5)); $send('quit'); fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
  $best=null; if(preg_match('/bestmove\s+(\S+)/',$out,$m))$best=$m[1];
  $scoreType='cp'; $score=0; if(preg_match_all('/score\s+(cp|mate)\s+(-?\d+)/',$out,$ms,PREG_SET_ORDER)){ $last=end($ms); $scoreType=$last[1]; $score=(int)$last[2]; }
  return ['bestmove'=>$best,'score_type'=>$scoreType,'score'=>$score,'raw'=>substr($out,-2000)];
}
function normalize_eval_for_side(array $ev, string $turn): int {
  if(($ev['score_type']??'cp')==='mate') { $v=(int)$ev['score']; $cp=$v>0 ? 100000-abs($v)*1000 : -100000+abs($v)*1000; }
  else $cp=(int)$ev['score'];
  return $turn==='w' ? $cp : -$cp;
}
function classify_loss(int $loss): string { if($loss>=300)return 'blunder'; if($loss>=150)return 'mistake'; if($loss>=70)return 'inaccuracy'; return 'ok'; }
