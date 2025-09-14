<?php
require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notifier.php';

header('Content-Type: application/json; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function ok($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function html($s){ header('Content-Type: text/html; charset=utf-8'); echo $s; exit; }

if ($path === '/webhook' && $method === 'POST') {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  if (!$j) { http_response_code(400); ok(['ok'=>false,'err'=>'invalid json']); }
  $env = $j['env'] ?? 'A1';
  $text= $j['text'] ?? '';
  $msgTime = isset($j['time']) ? strtotime($j['time']) : time();
  $now = time();

  $pdo = Db::pdo();
  $st = $pdo->prepare("INSERT INTO requests(env,text,msg_time,received_at) VALUES(?,?,?,?)");
  $st->execute([$env,$text,$msgTime,$now]);

  // 檢查「7秒內有收到訊息」持續 5 分鐘（300s）
  checkAndNotify($env, $text);

  ok(['ok'=>true]);
}

if ($path === '/requests' && $method === 'GET') {
  $pdo = Db::pdo();
  $rows = $pdo->query("SELECT env,text,datetime(msg_time,'unixepoch','localtime') as msg_time,
                              datetime(received_at,'unixepoch','localtime') as received_at
                       FROM requests ORDER BY msg_time DESC, id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
  // 簡單 HTML 查詢頁
  $html = "<html><head><meta charset='utf-8'><title>Requests</title>
  <style>table{border-collapse:collapse}td,th{border:1px solid #999;padding:6px 8px;font-family:monospace}</style></head><body>";
  $html.= "<h3>Last ".count($rows)." requests</h3><table><tr><th>env</th><th>text</th><th>msg_time</th><th>received_at</th></tr>";
  foreach($rows as $r){
    $html.= "<tr><td>{$r['env']}</td><td>{$r['text']}</td><td>{$r['msg_time']}</td><td>{$r['received_at']}</td></tr>";
  }
  $html.= "</table></body></html>";
  html($html);
}
//單筆插隊
if ($path === '/inject' && $method === 'POST') {
  $j = json_decode(file_get_contents('php://input'), true);
  if (!$j) { http_response_code(400); ok(['ok'=>false,'err'=>'invalid json']); }
  $env  = $j['env']  ?? 'A1';
  $text = $j['text'] ?? 'Something is wrong';
  $msgTime = isset($j['time']) ? strtotime($j['time']) : time();
  $now = time();
  $pdo = Db::pdo();
  $st = $pdo->prepare("INSERT INTO requests(env,text,msg_time,received_at) VALUES(?,?,?,?)");
  $st->execute([$env,$text,$msgTime,$now]);
  checkAndNotify($env, $text);
  ok(['ok'=>true,'inserted'=>['env'=>$env,'text'=>$text,'msg_time'=>$msgTime]]);
}
//批次插隊
if ($path === '/inject-batch' && $method === 'POST') {
  $j = json_decode(file_get_contents('php://input'), true);
  if (!$j) { http_response_code(400); ok(['ok'=>false,'err'=>'invalid json']); }
  $env   = $j['env']   ?? 'A1';
  $text  = $j['text']  ?? 'Something is wrong';
  $gap   = isset($j['gap'])   ? max(1,(int)$j['gap'])   : 7;   // 每筆間隔秒
  $count = isset($j['count']) ? max(1,(int)$j['count']) : 44;  // 插入筆數
  $endTs = isset($j['end_time']) ? strtotime($j['end_time']) : time(); // 結束時刻（默認現在）

  $pdo = Db::pdo();
  $pdo->beginTransaction();
  $st = $pdo->prepare("INSERT INTO requests(env,text,msg_time,received_at) VALUES(?,?,?,?)");
  for ($i=$count-1; $i>=0; $i--) {
    $msgTime = $endTs - $i*$gap;
    $st->execute([$env,$text,$msgTime,time()]);
  }
  $pdo->commit();
  // 最後再檢查一次，會觸發通知
  checkAndNotify($env, $text);
  ok(['ok'=>true,'env'=>$env,'count'=>$count,'gap'=>$gap,'end_time'=>$endTs]);
}


if ($path === '/health') { ok(['ok'=>true]); }

http_response_code(404); ok(['ok'=>false,'err'=>'not found']);

/**
 * 規則：
 * 以 env 為單位，若「任一連續 5 分鐘區間」內，
 * 每 7 秒內都有收到 {text:'Something is wrong', env:X}，則觸發通知
 * - 插隊 time：以 msg_time 排序檢查
 * - 觸發後做去重（例如同 env 1 分鐘內不重複推送）
 */
function checkAndNotify($env, $text){
  if ($text !== 'Something is wrong') return;
  $pdo = Db::pdo();

  // 取近 10 分鐘內此 env 的訊息，按 msg_time 排序（含插隊）
  /*
  $st = $pdo->prepare("SELECT msg_time FROM requests WHERE env=? AND text='Something is wrong'
                       AND msg_time >= ? ORDER BY msg_time ASC");
  $st->execute([$env, time()-600]);
  */
  //取全部的訊息
  $st = $pdo->prepare("SELECT msg_time FROM requests
                     WHERE env=? AND text='Something is wrong'
                     ORDER BY msg_time ASC");
  $st->execute([$env]);

  $times = $st->fetchAll(PDO::FETCH_COLUMN, 0);
  if (count($times) < 2) return;


  // 找是否存在一段長度 >=300s 的子序列，且相鄰差值 <=7s
  $winStart = 0; $bestLen = 0; $bestStartTs = null; $bestEndTs = null;
  for ($i=1; $i<count($times); $i++) {
    if ($times[$i] - $times[$i-1] <= 7) {
      // 延伸
    } else {
      // 斷裂，計算上一段
      $len = $times[$i-1] - $times[$winStart];
      if ($len >= 300) { $bestLen = $len; $bestStartTs=$times[$winStart]; $bestEndTs=$times[$i-1]; break; }
      $winStart = $i;
    }
    // 最後一筆也檢查
    if ($i === count($times)-1) {
      $len = $times[$i] - $times[$winStart];
      if ($len >= 300) { $bestLen=$len; $bestStartTs=$times[$winStart]; $bestEndTs=$times[$i]; }
    }
  }

  if ($bestLen >= 300) {
    // 去重：同 env 60s 內不重複
    /*
    $row = $pdo->query("SELECT last_notified_at FROM notify_state WHERE env=".$pdo->quote($env))->fetch(PDO::FETCH_ASSOC);
    $now = time();
    if ($row && $now - (int)$row['last_notified_at'] < 60) return;
    */
    $msg = sprintf("【警告】%s\nEnv: %s\nText: %s\n區間: %s ~ %s",
      date('Y-m-d H:i:s'),
      $env, 'Something is wrong',
      date('Y-m-d H:i:s', $bestStartTs), date('Y-m-d H:i:s', $bestEndTs)
    );
    Notifier::tg($msg);

    if ($row) {
      $st = $pdo->prepare("UPDATE notify_state SET last_notified_at=? WHERE env=?");
      $st->execute([$now, $env]);
    } else {
      $st = $pdo->prepare("INSERT INTO notify_state(env,last_notified_at) VALUES(?,?)");
      $st->execute([$env,$now]);
    }
  }
}
