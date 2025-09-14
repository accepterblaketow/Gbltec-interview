<?php
// 簡易路由 + 設定儲存
header('Content-Type: application/json; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$dataDir = __DIR__ . '/../data';
@mkdir($dataDir, 0777, true);
$configFile = $dataDir . '/config.json';
if (!file_exists($configFile)) file_put_contents($configFile, json_encode(['webhook'=>null,'testing'=>false,'stagger'=>[0,1,2]]));

function loadConfig($f){ return json_decode(@file_get_contents($f), true) ?: []; }
function saveConfig($f,$a){ file_put_contents($f, json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }

if ($path === '/setWebhook') {
  $url = $_GET['url'] ?? '';
  if (!filter_var($url, FILTER_VALIDATE_URL)) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'invalid url']); exit; }
  $cfg = loadConfig($configFile);
  $cfg['webhook'] = $url;
  saveConfig($configFile, $cfg);
  echo json_encode(['ok'=>true,'webhook'=>$url]); exit;
}

if ($path === '/setTesting') {
  $test = ($_GET['test'] ?? '') === 'true';
  $cfg = loadConfig($configFile);
  $cfg['testing'] = $test;
  // 測試模式：固定 7 秒，但給每個 env 不同相位（0,1,2 秒偏移）避免「頻率相同」顯示衝突
  $cfg['stagger'] = [0,1,2];
  saveConfig($configFile, $cfg);
  echo json_encode(['ok'=>true,'testing'=>$test]); exit;
}

if ($path === '/health') { echo json_encode(['ok'=>true]); exit; }

http_response_code(404);
echo json_encode(['ok'=>false,'err'=>'not found']);
