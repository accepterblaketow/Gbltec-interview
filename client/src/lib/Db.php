<?php
class Db {
  private static $pdo;
  static function pdo() {
    if (!self::$pdo) {
      $dir = __DIR__ . '/../data';
      @mkdir($dir, 0777, true);
      self::$pdo = new PDO('sqlite:'.$dir.'/client.db');
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::migrate();
    }
    return self::$pdo;
  }
  private static function migrate() {
    $p = self::$pdo;
    $p->exec("CREATE TABLE IF NOT EXISTS requests(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      env TEXT, text TEXT,
      msg_time INTEGER,   -- 取 JSON 的 time（若無則收訊當下）
      received_at INTEGER -- 實際接收時間
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS notify_state(
      env TEXT PRIMARY KEY,
      last_notified_at INTEGER
    )");
  }
}
