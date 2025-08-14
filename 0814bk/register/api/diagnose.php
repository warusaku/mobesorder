<?php
// 出力バッファリングを開始
ob_start();

// PHPのエラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーハンドラーを設定
function captureError($errno, $errstr, $errfile, $errline) {
    $errorLog = "Error [$errno] $errstr in $errfile on line $errline";
    error_log($errorLog);
    
    // ログディレクトリとファイルの存在確認
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/diagnose_error.log';
    
    // エラーをログファイルに記録
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $errorLog" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    return true;
}
set_error_handler('captureError');

try {
    // 設定ファイルとライブラリを読み込み
    require_once '../../api/config/config.php';
    require_once '../../api/lib/roomdata_register.php';
    
    // CORSヘッダー設定
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // 環境情報を収集
    $environment = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_time' => date('Y-m-d H:i:s'),
        'script_path' => __FILE__,
        'config_exists' => file_exists(__DIR__ . '/../../api/config/config.php'),
        'logs_dir_exists' => is_dir(__DIR__ . '/../../logs'),
        'database_lib_exists' => file_exists(__DIR__ . '/../../api/lib/Database.php'),
        'roomdata_register_lib_exists' => file_exists(__DIR__ . '/../../api/lib/roomdata_register.php')
    ];
    
    // データベース接続情報を確認（定数が定義されているか）
    $db_config = [
        'DB_HOST' => defined('DB_HOST') ? 'defined' : 'undefined',
        'DB_NAME' => defined('DB_NAME') ? 'defined' : 'undefined',
        'DB_USER' => defined('DB_USER') ? 'defined' : 'undefined',
        'DB_PASS' => defined('DB_PASS') ? 'defined' : 'undefined',
        'TOKEN_EXPIRY_DAYS' => defined('TOKEN_EXPIRY_DAYS') ? TOKEN_EXPIRY_DAYS : 'undefined',
        'ROOM_LINK_AUTO_CLEANUP' => defined('ROOM_LINK_AUTO_CLEANUP') ? (ROOM_LINK_AUTO_CLEANUP ? 'true' : 'false') : 'undefined'
    ];
    
    // RoomDataRegisterインスタンスを作成して診断
    $roomDataRegister = new RoomDataRegister();
    $diagnosis = $roomDataRegister->diagnose();
    
    // シンプルなテスト用レコードを作成して動作確認
    $testResult = [];
    try {
        $testUserId = 'test_' . time();
        $testRoomNumber = 'test_room_' . time();
        
        $roomDataRegister->log("diagnose.php - テスト用レコードを作成: userId=$testUserId, roomNumber=$testRoomNumber");
        
        // テスト用のレコードを作成
        $roomDataRegister->saveRoomLink(
            $testUserId,
            $testRoomNumber,
            'Test User',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 day'))
        );
        
        $testResult['saveRoomLink'] = 'success';
        
        // トークンを作成
        $token = $roomDataRegister->saveRoomToken($testRoomNumber, 1);
        $testResult['saveRoomToken'] = 'success';
        $testResult['token'] = $token;
        
        // 作成したレコードを取得
        $roomInfo = $roomDataRegister->getRoomInfoWithToken($testUserId);
        $testResult['getRoomInfoWithToken'] = $roomInfo ? 'success' : 'failed';
        
        // テスト用レコードをクリーンアップ
        $stmt = $roomDataRegister->pdo->prepare("DELETE FROM line_room_links WHERE line_user_id = :userId");
        $stmt->bindParam(':userId', $testUserId);
        $stmt->execute();
        
        $stmt = $roomDataRegister->pdo->prepare("DELETE FROM room_tokens WHERE room_number = :roomNumber");
        $stmt->bindParam(':roomNumber', $testRoomNumber);
        $stmt->execute();
        
        $testResult['cleanup'] = 'success';
        
    } catch (Exception $e) {
        $testResult['error'] = $e->getMessage();
        $roomDataRegister->log("diagnose.php - テスト中にエラーが発生: " . $e->getMessage(), 'ERROR');
    }
    
    // バッファをクリアしてJSONレスポンスを返す
    ob_end_clean();
    
    // 診断結果を返す
    echo json_encode([
        'success' => true,
        'message' => '診断完了',
        'environment' => $environment,
        'database_config' => $db_config,
        'diagnosis' => $diagnosis,
        'test_result' => $testResult
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // バッファをクリアしてJSONエラーレスポンスを返す
    ob_end_clean();
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベース診断エラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
} 