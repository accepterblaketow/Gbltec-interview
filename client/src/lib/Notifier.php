<?php
class Notifier {
  static function tg($text) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    $chat  = getenv('TELEGRAM_CHAT_ID');
    if (!$token || !$chat) return false;
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = ['chat_id'=>$chat, 'text'=>$text];
    $ctx = stream_context_create(['http'=>[
      'method'=>'POST','header'=>"Content-Type: application/json",
      'content'=>json_encode($payload, JSON_UNESCAPED_UNICODE)
    ]]);
    @file_get_contents($url, false, $ctx);
    return true;
  }
}
