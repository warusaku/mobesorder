<?php
// File: test_server_ping.php
// Description: サーバー単体応答確認

// サーバー情報取得
$server_info = [
    "status" => "ok",
    "server" => $_SERVER['SERVER_NAME'],
    "php_version" => PHP_VERSION,
    "message" => "サーバーは正常に応答しています。",
    "timestamp" => date("Y-m-d H:i:s"),
    "remote_addr" => $_SERVER['REMOTE_ADDR'],
    "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

// ログファイル設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " [TEST] " . $msg . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// HTTPリクエスト情報
$request_info = [
    "method" => $_SERVER['REQUEST_METHOD'],
    "uri" => $_SERVER['REQUEST_URI'],
    "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

// 使用可能なデータベースモジュール確認
$db_modules = [];
if (extension_loaded('mysqli')) {
    $db_modules[] = 'mysqli';
}
if (extension_loaded('pdo_mysql')) {
    $db_modules[] = 'pdo_mysql';
}
if (extension_loaded('sqlite3')) {
    $db_modules[] = 'sqlite3';
}

// 使用可能な機能確認
$server_capabilities = [
    "db_modules" => $db_modules,
    "gd" => extension_loaded('gd'),
    "curl" => extension_loaded('curl'),
    "json" => extension_loaded('json'),
    "ssl" => extension_loaded('openssl')
];

// レスポンスデータ構築
$response = [
    "app" => "RTSP_Reader Test Server",
    "info" => $server_info,
    "request" => $request_info,
    "capabilities" => $server_capabilities
];

// 最大メモリ使用量
$response["memory_usage"] = [
    "current" => formatBytes(memory_get_usage(true)),
    "peak" => formatBytes(memory_get_peak_usage(true))
];

// ディスク容量（もし取得できれば）
if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
    try {
        $disk_free = disk_free_space('.');
        $disk_total = disk_total_space('.');
        $response["disk_space"] = [
            "free" => formatBytes($disk_free),
            "total" => formatBytes($disk_total),
            "used_percent" => round(($disk_total - $disk_free) / $disk_total * 100, 2) . '%'
        ];
    } catch (Exception $e) {
        $response["disk_space"] = "Error: " . $e->getMessage();
    }
}

// ログに記録
log_message("Server ping test executed - " . $server_info['remote_addr']);

// バイト数をフォーマットする関数
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// JSONレスポンス
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
?> 