<?php
// order_logger.php
// 注文データをPOSTで受け取り、/logsに保存

// タイムスタンプ取得
$timestamp = date('Ymd_His');

// POSTデータ取得
$rawData = file_get_contents('php://input');

// ログディレクトリとファイル名
$logDir = __DIR__ . '/../logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . "order_{$timestamp}.log";

// ログ保存
file_put_contents($logFile, $rawData);

// レスポンス
http_response_code(200);
echo 'OK'; 