<?php
// File: sync.php
// Description: クライアントからの設定取得リクエストに応答

// ログファイル設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " [SYNC] " . $msg . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// リクエストメソッド確認
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    log_message("405 Method Not Allowed - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// デバイスID取得
$device = $_GET['device'] ?? '';
if (empty($device)) {
    http_response_code(400);
    log_message("400 Bad Request - Missing device parameter");
    echo json_encode(["status" => "error", "message" => "Missing device parameter"]);
    exit;
}

// 設定ファイルパス
$configsDir = __DIR__ . "/../configs";
$configPath = $configsDir . "/{$device}.json";

// ディレクトリ存在確認
if (!is_dir($configsDir)) {
    // ディレクトリが存在しない場合は作成
    if (!mkdir($configsDir, 0755, true)) {
        http_response_code(500);
        log_message("500 Server Error - Cannot create configs directory");
        echo json_encode(["status" => "error", "message" => "Server configuration error"]);
        exit;
    }
}

// 設定ファイル存在確認
if (!file_exists($configPath)) {
    http_response_code(404);
    log_message("404 Config Not Found for {$device}");
    echo json_encode([
        "status" => "error", 
        "message" => "Config not found for device: {$device}"
    ]);
    exit;
}

// トークン検証（オプション）
$apiToken = $_GET['token'] ?? '';
$validToken = "12345@";  // 設定から取得するべき
if (!empty($validToken) && $apiToken !== $validToken) {
    http_response_code(401);
    log_message("401 Unauthorized Access - Invalid token for {$device}");
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

try {
    // JSONファイル読み込み
    $jsonContent = file_get_contents($configPath);
    
    // JSON形式チェック
    $config = json_decode($jsonContent);
    if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON format: " . json_last_error_msg());
    }
    
    // JSONレスポンス
    header('Content-Type: application/json');
    echo $jsonContent;
    
    log_message("Config synced for {$device}");
    
} catch (Exception $e) {
    http_response_code(500);
    log_message("Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Error reading config: " . $e->getMessage()
    ]);
} 