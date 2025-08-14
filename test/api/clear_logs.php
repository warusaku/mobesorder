<?php
/**
 * RTSP_Reader Test Framework - Clear Logs API (Lolipop)
 * 
 * ログクリアAPI
 */

// ヘッダー設定
header('Content-Type: application/json');

// インクルードパス設定
$includePath = dirname(__DIR__) . '/includes';
set_include_path(get_include_path() . PATH_SEPARATOR . $includePath);

// 必要なライブラリの読み込み
require_once 'test_logger.php';

try {
    // ロガーの初期化
    $logFile = dirname(__DIR__) . '/logs/test_' . date('Y-m-d') . '.log';
    $logger = new TestLogger($logFile);
    
    // ログをクリア
    $logger->clear();
    
    // ログファイルをクリア
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    
    // 成功レスポンス
    echo json_encode([
        'status' => 'success',
        'message' => 'ログがクリアされました'
    ]);
} catch (Exception $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ログクリア中にエラーが発生しました: ' . $e->getMessage()
    ]);
} 
 
 
 
 