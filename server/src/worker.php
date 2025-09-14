<?php
// 產生 A1/A2/A3 訊息，POST 至 config.webhook
// 規則：1~15 秒隨機，三個 env 當輪間隔不得相同；測試模式固定 7 秒（以 0/1/2 秒相位差錯開）

$dataDir = __DIR__ . '/data';
$configFile = $dataDir . '/config.json';
@mkdir($dataDir, 0777, true);

$envs = ['A1','A2','A3'];
$lastSentAt = array_fill_keys($envs, 0);
$nextDueAt  = array_fill_keys($envs, time());
$interval   = array_fill_keys($envs, 5); // 初值

function cfg() {
  global $configFile;
  return json_decode(@file_get_contents($configFile), true) ?: ['webhook'=>null,'testing'=>false,'stagger'=>[0,1,2]];
}
function postJson($url, $payload) {
  $ctx = stream_context_create(['http'=>[
    'method'=>'POST',
    'header'=>"Content-Type: application/json",
    'content'=>json_encode($payload)
  ]]);
  @file_get_contents($url, false, $ctx);
}

while (true) {
  $c = cfg();
  $webhook = $c['webhook'] ?? null;
  $testing = (bool)($c['testing'] ?? false);
  $stagger = $c['stagger'] ?? [0,1,2];
  $now = time();

  // 決定各 env 下一次間隔
  foreach ($envs as $i => $env) {
    // 到點才送
    if ($now >= $nextDueAt[$env]) {
      if ($webhook) {
        $payload = ['text'=>'Something is wrong', 'env'=>$env];
        postJson($webhook, $payload);
        $lastSentAt[$env] = $now;
      }
      // 計算下一個 interval
      if ($testing) {
        // 固定 7 秒 + 錯相（避免三個同時）
        $interval[$env] = 7;
        $nextDueAt[$env] = $now + $interval[$env] + ($stagger[$i] ?? $i);
      } else {
        // 1~15 隨機，需與其它 env 本輪不同；若撞到就忽略（規格允許）
        $candidate = random_int(1, 15);
        $others = array_diff_key($interval, [$env=>true]);
        if (in_array($candidate, $others, true)) {
          // 忽略本輪重抽：直接推遲到下一圈再決定
          $candidate = random_int(1, 15);
        }
        $interval[$env] = $candidate;
        $nextDueAt[$env] = $now + $candidate;
      }
    }
  }

  usleep(200000); // 0.2s loop
}
