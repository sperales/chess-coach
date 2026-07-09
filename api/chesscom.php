<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/pgn.php';
require_once __DIR__.'/../includes/chesscom.php';
require_once __DIR__.'/../includes/analysis_queue.php';
$u=require_login();
require_post_csrf();
$body=request_json_body();
$username=trim($body['username']??$u['username']); $limit=max(1,min(100,(int)($body['limit']??20)));
try{
  $pgn=chesscom_fetch_pgns($username,$limit); $pgns=split_pgns($pgn); $added=0; $skipped=0;
  foreach($pgns as $one){ $uid=hash('sha256',$one); $dt=pgn_date(pgn_tag($one,'Date')); $data=[pgn_tag($one,'White'),pgn_tag($one,'Black'),pgn_tag($one,'Result'),result_for_user($one,$username),$dt,pgn_tag($one,'Event'),pgn_tag($one,'Site'),pgn_eco_code($one),pgn_opening_name($one),pgn_eco_url($one),$one,$u['id'],$uid,'chess.com'];
    try{
      $st=db()->prepare('INSERT INTO games (white_player,black_player,result_raw,user_result,played_at,event_name,site,eco_code,opening_name,eco_url,pgn,user_id,game_uid,source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
      $st->execute($data);
      $gameId = (int)db()->lastInsertId();
      if (app_config()['auto_queue_imports'] ?? true) queue_game_analysis($gameId, (int)$u['id'], false);
      $added++;
    }
    catch(PDOException $e){ if(($e->errorInfo[1]??0)==1062) $skipped++; else throw $e; }
  }
  $st=db()->prepare('INSERT INTO chesscom_imports (user_id,chesscom_username,requested_limit,imported_count,skipped_count,status,created_at) VALUES (?,?,?,?,?,?,NOW())'); $st->execute([$u['id'],$username,$limit,$added,$skipped,'done']);
  json_response(['ok'=>true,'added'=>$added,'skipped'=>$skipped,'found'=>count($pgns)]);
}catch(Throwable $e){
  $publicError = public_error_message($e, 'No se pudo completar la importación desde Chess.com.');
  try{ $st=db()->prepare('INSERT INTO chesscom_imports (user_id,chesscom_username,requested_limit,status,error_message,created_at) VALUES (?,?,?,?,?,NOW())'); $st->execute([$u['id'],$username,$limit,'error',$publicError]); }catch(Throwable $x){}
  json_response(['ok'=>false,'error'=>$publicError]);
}
