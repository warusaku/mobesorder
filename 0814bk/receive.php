<?php
// File: receive.php
// Description: RTSP仮想IoTカメラからのPOST受信・MySQL書き込み

// ログファイル設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " [RECEIVE] " . $msg . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// リクエストメソッド確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_message("405 Method Not Allowed");
    exit;
}

// POSTデータ取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// データバリデーション
if (!$data || !isset($data['LacisID']) || !isset($data['readings'])) {
    http_response_code(400);
    log_message("400 Bad Request: Invalid JSON");
    exit;
}

// DB設定読み込み
require_once '../dbconfig.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// DB接続エラー処理
if ($mysqli->connect_error) {
    log_message("DB Connection Failed: " . $mysqli->connect_error);
    http_response_code(500);
    exit;
}

// 異常検知のキーワード
$alertKeywords = ["ERR", "999", "FAIL", "E01", "E02", "E03"];

// レコード挿入
try {
    // トランザクション開始
    $mysqli->begin_transaction();
    
    // PreparedStatement準備
    $stmt = $mysqli->prepare("INSERT INTO device_readings (lacis_id, display_id, value, converted_value, timestamp) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("PreparedStatement作成エラー: " . $mysqli->error);
    }
    
    // タイムスタンプ
    $timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
    
    // 各測定値を挿入
    foreach ($data['readings'] as $reading) {
        $display_id = $reading['display_id'];
        $value = $reading['value'];
        $converted_value = isset($reading['converted_value']) ? $reading['converted_value'] : $value;
        
        $stmt->bind_param("sssss", 
            $data['LacisID'], 
            $display_id, 
            $value, 
            $converted_value,
            $timestamp
        );
        
        if (!$stmt->execute()) {
            throw new Exception("レコード挿入エラー: " . $stmt->error);
        }
        
        // 異常検知の場合、通知ファイルを呼び出し
        foreach ($alertKeywords as $keyword) {
            if (stripos($value, $keyword) !== false) {
                // 別ファイルで通知処理
                $notifyData = json_encode([
                    'LacisID' => $data['LacisID'],
                    'readings' => [$reading]
                ]);
                
                file_get_contents(
                    'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/notify_trigger.php',
                    false,
                    stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => 'Content-Type: application/json',
                            'content' => $notifyData
                        ]
                    ])
                );
                
                log_message("異常検知通知: {$data['LacisID']} - {$display_id}: {$value}");
                break;
            }
        }
    }
    
    // トランザクションコミット
    $mysqli->commit();
    $stmt->close();
    
    log_message("Success: Received from {$data['LacisID']} - " . count($data['readings']) . " readings");
    
    // 成功レスポンス
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "ok", 
        "message" => "Data received successfully",
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // エラー時はロールバック
    $mysqli->rollback();
    log_message("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
} finally {
    // DB接続をクローズ
    $mysqli->close();
} 