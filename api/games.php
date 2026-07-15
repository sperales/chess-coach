<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/pgn.php';
require_once __DIR__.'/../includes/analysis_queue.php';
require_once __DIR__.'/../includes/eco_catalog.php';
$u=require_login();
$action=$_GET['action']??$_POST['action']??'';
if (in_array($action, ['import', 'delete'], true)) {
  require_post_csrf();
}
if($action==='list'){
  $cfg = app_config();
  $defaultPerPage = max(1, (int)($cfg['games_per_page'] ?? 50));
  $perPage = (int)($_GET['per_page'] ?? $defaultPerPage);
  $perPage = max(1, min(200, $perPage));
  $page = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page - 1) * $perPage;
  [$whereSql, $filterParams] = game_list_filter_sql((int)$u['id'], (string)$u['username']);

  $countSt = db()->prepare("SELECT COUNT(*) FROM games g WHERE $whereSql");
  $countSt->execute($filterParams);
  $total = (int)$countSt->fetchColumn();
  $pages = max(1, (int)ceil($total / $perPage));
  if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $perPage; }

  $statsGlobal = game_stats_for_user($u['id'], false);
  $statsRecent = game_stats_for_user($u['id'], true);
  $accuracyStats = analysis_accuracy_stats_for_user((int)$u['id']);

  $sql = 'SELECT g.id, g.game_uid, g.white_player, g.black_player, g.result_raw, g.user_result, g.played_at, g.event_name, g.site, g.eco_code, g.opening_name, g.eco_url, g.pgn, g.imported_at, g.source, a.id AS analysis_id, a.status AS analysis_status, a.blunders, a.mistakes, a.inaccuracies FROM games g LEFT JOIN game_analysis a ON a.id=(SELECT id FROM game_analysis WHERE game_id=g.id AND user_id=? ORDER BY id DESC LIMIT 1) WHERE '.$whereSql.' ORDER BY COALESCE(g.played_at, g.imported_at) DESC, g.id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset;
  $st=db()->prepare($sql);
  $st->execute(array_merge([(int)$u['id']], $filterParams));
  $games = $st->fetchAll();
  attach_opening_info_to_games($games);
  attach_smart_tags_to_games($games, (int)$u['id']);
  json_response([
    'ok'=>true,
    'games'=>$games,
    'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages],
    'filters'=>['tags'=>smart_tag_options_for_user((int)$u['id'])],
    'stats'=>['global'=>$statsGlobal,'recent10'=>$statsRecent,'analysis_accuracy'=>$accuracyStats,'queue'=>queue_stats((int)$u['id']),'smart_tags'=>smart_tag_summary_for_user((int)$u['id'])]
  ]);
}

function attach_opening_info_to_games(array &$games): void {
  foreach ($games as &$game) {
    if (empty($game['eco_code']) && !empty($game['pgn'])) {
      $game['eco_code'] = pgn_eco_code((string)$game['pgn']);
    }
    if (empty($game['opening_name']) && !empty($game['pgn'])) {
      $game['opening_name'] = pgn_opening_name((string)$game['pgn']);
    }
    if (empty($game['eco_url']) && !empty($game['pgn'])) {
      $game['eco_url'] = pgn_eco_url((string)$game['pgn']);
    }
    $labels = eco_catalog_resolve($game['eco_code'] ?? null, $game['opening_name'] ?? null);
    $game['eco_code'] = $labels['eco_code'];
    $game['opening_name'] = $labels['opening_name'];
    $game['opening_variation_name'] = $labels['variation_name'];
    $game['opening_family_key'] = $labels['family_key'];
    $game['opening_family_name'] = $labels['family_name'];
    $game['opening_label_source'] = $labels['label_source'];
    unset($game['pgn']);
  }
  unset($game);
}

function game_list_filter_sql(int $userId, string $username): array {
  $where = ['g.user_id=?'];
  $params = [$userId];

  $color = $_GET['color'] ?? '';
  if ($color === 'white') {
    $where[] = 'LOWER(COALESCE(g.white_player,""))=LOWER(?)';
    $params[] = $username;
  } elseif ($color === 'black') {
    $where[] = 'LOWER(COALESCE(g.black_player,""))=LOWER(?)';
    $params[] = $username;
  }

  $result = $_GET['result'] ?? '';
  if (in_array($result, ['win', 'loss', 'draw'], true)) {
    $where[] = 'g.user_result=?';
    $params[] = $result;
  }

  $openingKey = trim((string)($_GET['opening_key'] ?? ''));
  if ($openingKey !== '') {
    $where[] = 'EXISTS (
      SELECT 1
      FROM game_opening_profiles op
      WHERE op.game_id=g.id
        AND op.user_id=?
        AND op.opening_key=?
    )';
    $params[] = $userId;
    $params[] = $openingKey;
  }

  $tag = trim((string)($_GET['tag'] ?? ''));
  if ($tag !== '') {
    $where[] = '(
      EXISTS (
        SELECT 1
        FROM game_tags gt
        WHERE gt.game_id=g.id
          AND gt.user_id=?
          AND gt.tag_code=?
          AND gt.analysis_id=(SELECT id FROM game_analysis WHERE game_id=g.id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
      )
      OR EXISTS (
        SELECT 1
        FROM move_tags mt
        WHERE mt.game_id=g.id
          AND mt.user_id=?
          AND mt.tag_code=?
          AND mt.analysis_id=(SELECT id FROM game_analysis WHERE game_id=g.id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
      )
    )';
    $params[] = $userId;
    $params[] = $tag;
    $params[] = $userId;
    $params[] = $userId;
    $params[] = $tag;
    $params[] = $userId;
  }

  return [implode(' AND ', $where), $params];
}

function accuracy_from_acpl(float $acpl): float {
  if ($acpl <= 0) return 100.0;
  return round(max(0, min(100, 100 * exp(-$acpl / 220))), 1);
}

function analysis_accuracy_stats_for_user(int $userId): array {
  $sql = 'SELECT a.id AS analysis_id, AVG(LEAST(GREATEST(COALESCE(m.centipawn_loss, 0), 0), 1000)) AS acpl
          FROM game_analysis a
          JOIN game_move_analysis m ON m.analysis_id=a.id
          WHERE a.user_id=?
            AND a.status="done"
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=a.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
          GROUP BY a.id';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);

  $totalAccuracy = 0.0;
  $totalAnalyzed = 0;
  foreach ($st->fetchAll() as $row) {
    $totalAccuracy += accuracy_from_acpl((float)($row['acpl'] ?? 0));
    $totalAnalyzed++;
  }

  return [
    'average' => $totalAnalyzed ? round($totalAccuracy / $totalAnalyzed, 1) : null,
    'analyzed_games' => $totalAnalyzed,
  ];
}

function attach_smart_tags_to_games(array &$games, int $userId): void {
  $analysisIds = [];
  foreach ($games as &$game) {
    $game['smart_tags'] = [];
    if (!empty($game['analysis_id']) && ($game['analysis_status'] ?? '') === 'done') {
      $analysisIds[] = (int)$game['analysis_id'];
    }
  }
  unset($game);
  if (!$analysisIds) return;

  $placeholders = implode(',', array_fill(0, count($analysisIds), '?'));
  $params = array_merge([$userId], $analysisIds);
  $sql = "SELECT gt.game_id, gt.tag_code, gt.confidence, gt.evidence_count, gt.primary_ply, d.label, d.category, d.severity
          FROM game_tags gt
          JOIN smart_tag_definitions d ON d.code=gt.tag_code
          WHERE gt.user_id=? AND gt.analysis_id IN ($placeholders)
          ORDER BY FIELD(d.severity,'critical','high','medium','low','info'), gt.evidence_count DESC, d.label ASC";
  $st = db()->prepare($sql);
  $st->execute($params);
  $byGame = [];
  foreach ($st->fetchAll() as $tag) {
    $byGame[(int)$tag['game_id']][] = $tag;
  }
  foreach ($games as &$game) {
    $game['smart_tags'] = $byGame[(int)$game['id']] ?? [];
  }
  unset($game);
}

function smart_tag_summary_for_user(int $userId): array {
  $sql = 'SELECT gt.tag_code, d.label, d.category, d.severity, COUNT(*) AS total
          FROM game_tags gt
          JOIN smart_tag_definitions d ON d.code=gt.tag_code
          JOIN game_analysis a ON a.id=gt.analysis_id
          WHERE gt.user_id=?
            AND a.id=(SELECT id FROM game_analysis WHERE game_id=gt.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
          GROUP BY gt.tag_code, d.label, d.category, d.severity
          ORDER BY total DESC, FIELD(d.severity,"critical","high","medium","low","info"), d.label ASC
          LIMIT 5';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId]);
  return $st->fetchAll();
}

function smart_tag_options_for_user(int $userId): array {
  $sql = 'SELECT tag_code, label, category, severity, SUM(total) AS total
          FROM (
            SELECT gt.tag_code, d.label, d.category, d.severity, COUNT(*) AS total
            FROM game_tags gt
            JOIN smart_tag_definitions d ON d.code=gt.tag_code
            JOIN game_analysis a ON a.id=gt.analysis_id
            WHERE gt.user_id=?
              AND a.id=(SELECT id FROM game_analysis WHERE game_id=gt.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            GROUP BY gt.tag_code, d.label, d.category, d.severity
            UNION ALL
            SELECT mt.tag_code, d.label, d.category, mt.severity, COUNT(DISTINCT mt.game_id) AS total
            FROM move_tags mt
            JOIN smart_tag_definitions d ON d.code=mt.tag_code
            JOIN game_analysis a ON a.id=mt.analysis_id
            WHERE mt.user_id=?
              AND a.id=(SELECT id FROM game_analysis WHERE game_id=mt.game_id AND user_id=? AND status="done" ORDER BY id DESC LIMIT 1)
            GROUP BY mt.tag_code, d.label, d.category, mt.severity
          ) tag_options
          GROUP BY tag_code, label, category, severity
          ORDER BY label ASC';
  $st = db()->prepare($sql);
  $st->execute([$userId, $userId, $userId, $userId]);
  return $st->fetchAll();
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
  $body=request_json_body();
  $pgns=split_pgns($body['pgn']??''); $added=0; $skipped=0;
  foreach($pgns as $pgn){
    $uid=hash('sha256',$pgn);
    $dt=pgn_date(pgn_tag($pgn,'Date'));
    $data=[pgn_tag($pgn,'White'),pgn_tag($pgn,'Black'),pgn_tag($pgn,'Result'),result_for_user($pgn,$u['username']),$dt,pgn_tag($pgn,'Event'),pgn_tag($pgn,'Site'),pgn_eco_code($pgn),pgn_opening_name($pgn),pgn_eco_url($pgn),$pgn,$u['id'],$uid,'manual'];
    try{
      $st=db()->prepare('INSERT INTO games (white_player,black_player,result_raw,user_result,played_at,event_name,site,eco_code,opening_name,eco_url,pgn,user_id,game_uid,source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
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
