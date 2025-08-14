<?php
// db_connect.php
// Version: 1.0.0
// ------------------------------------------------------------
// データベース接続設定
// ------------------------------------------------------------

// エラーログ設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ログ関数
function writeLog($message, $level = 'ERROR') {
    $scriptName = basename(__FILE__, '.php');
    $logDir = realpath(__DIR__ . '/../../logs') ?: (__DIR__ . '/../../logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . "/{$scriptName}.log";

    // ローテーション：300KB を超えたら 20% 残して圧縮（バックアップなし）
    if (file_exists($logFile) && filesize($logFile) > 307200) { // 300KB
        $content = file_get_contents($logFile);
        $retainSize = (int)(307200 * 0.2); // 約 60KB
        $content = substr($content, -$retainSize);
        file_put_contents($logFile, $content, LOCK_EX);
    }

    $date = date('Y-m-d H:i:s');
    $line = "[$date][$level] $message" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// データベース接続情報
$dbHost = 'localhost';
$dbName = 'fabula';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    writeLog('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
} 