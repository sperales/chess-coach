<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/pgn.php';
require_once __DIR__.'/../includes/analysis_queue.php';
$u=require_login();
$action=$_GET['action']??$_POST['action']??'';
if($action==='list'){
  $cfg = app_config();
  $defaultPerPage = max(1, (int)($cfg['games_per_page'] ?? 50));
  $perPage = (int)($_GET['per_page'] ?? $defaultPerPage);
  $perPage = max(1, min(200, $perPage));
  $page = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page - 1) * $perPage;

  $countSt = db()->prepare('SELECT COUNT(*) FROM games WHERE user_id=?');
  $countSt->execute([$u['id']]);
  $total = (int)$countSt->fetchColumn();
  $pages = max(1, (int)ceil($total / $perPage));
  if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $perPage; }

  $statsGlobal = game_stats_for_user($u['id'], false);
  $statsRecent = game_stats_for_user($u['id'], true);

  $sql = 'SELECT g.id, g.game_uid, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at, g.event_name, g.site, g.imported_at, g.source, a.status AS analysis_status, a.blunders, a.mistakes, a.inaccuracies FROM games g LEFT JOIN game_analysis a ON a.id=(SELECT id FROM game_analysis WHERE game_id=g.id ORDER BY id DESC LIMIT 1) WHERE g.user_id=? ORDER BY COALESCE(g.played_at, g.imported_at) DESC, g.id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset;
  $st=db()->prepare($sql);
  $st->execute([$u['id']]);
  json_response([
    'ok'=>true,
    'games'=>$st->fetchAll(),
    'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages],
    'stats'=>['global'=>$statsGlobal,'recent10'=>$statsRecent,'queue'=>queue_stats((int)$u['id'])]
  ]);
}

function game_stats_for_user(int $userId, bool $recent): array {
  $where = 'user_id=?';
  $params = [$userId];
  if ($recent) {
    $where .= ' AND played_at IS NOT NULL AND played_at >= DATE_SUB(CURDATE(), INTERVAL 9 DAY)';
  }
  $st = db()->prepare("SELECT COUNT(*) AS total, SUM(user_result='win') AS wins, SUM(user_result='loss') AS losses, SUM(user_result='draw') AS draws, MIN(played_at) AS first_day, MAX(played_at) AS last_day FROM games WHERE $where");
  $st->execute($params);
  $r = $st->fetch() ?: [];
  $total = (int)($r['total'] ?? 0);
  $wins = (int)($r['wins'] ?? 0);
  $losses = (int)($r['losses'] ?? 0);
  $draws = (int)($r['draws'] ?? 0);
  if ($recent) {
    $days = 10;
  } elseif (!empty($r['first_day']) && !empty($r['last_day'])) {
    $days = max(1, (int)((strtotime($r['last_day']) - strtotime($r['first_day'])) / 86400) + 1);
  } else {
    $days = 1;
  }
  return ['total'=>$total,'wins'=>$wins,'losses'=>$losses,'draws'=>$draws,'avg_per_day'=>number_format($total / $days, 2, '.', '')];
}
if($action==='import'){
  $body=json_decode(file_get_contents('php://input'),true) ?: [];
  $pgns=split_pgns($body['pgn']??''); $added=0; $skipped=0;
  foreach($pgns as $pgn){
    $uid=hash('sha256',$pgn);
    $dt=pgn_date(pgn_tag($pgn,'Date'));
    $data=[pgn_tag($pgn,'White'),pgn_tag($pgn,'Black'),pgn_tag($pgn,'Result'),result_for_user($pgn,$u['username']),$dt,pgn_tag($pgn,'Event'),pgn_tag($pgn,'Site'),$pgn,$u['id'],$uid,'manual'];
    try{
      $st=db()->prepare('INSERT INTO games (white_player,black_player,result_raw,user_result,played_at,event_name,site,pgn,user_id,game_uid,source) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
      $st->execute($data);
      $gameId = (int)db()->lastInsertId();
      if (app_config()['auto_queue_imports'] ?? true) queue_game_analysis($gameId, (int)$u['id'], false);
      $added++;
    }
    catch(PDOException $e){ if(($e->errorInfo[1]??0)==1062) $skipped++; else throw $e; }
  }
  json_response(['ok'=>true,'added'=>$added,'skipped'=>$skipped]);
}
if($action==='delete'){
  $id=(int)($_POST['id']??0); $st=db()->prepare('DELETE FROM games WHERE id=? AND user_id=?'); $st->execute([$id,$u['id']]); json_response(['ok'=>true]);
}
json_response(['ok'=>false,'error'=>'Acción no soportada']);
