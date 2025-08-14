<?php
// 出力バッファリングを開始
ob_start();

// デバッグ用にすべてのエラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 正しいパスを確認するために現在のファイルの絶対パスを表示
$currentFilePath = __FILE__;
$configFilePath = realpath(dirname(__FILE__) . '/../../config/REGISTER_LIFF_config.php');

// 設定ファイルの存在確認
if (!file_exists($configFilePath)) {
    // バッファをクリアしてヘッダーを設定
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'REGISTER_LIFF_config.phpファイルが見つかりません',
        'debug' => [
            'current_file' => $currentFilePath,
            'config_path' => $configFilePath,
            'directory_exists' => is_dir(dirname($configFilePath)),
            'directory_contents' => scandir(dirname($configFilePath))
        ]
    ]);
    exit;
}

// 必要なライブラリを読み込み
try {
    require_once $configFilePath;
    
    // バッファをクリアしてヘッダーを設定
    ob_end_clean();
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // 設定情報を返す
    echo json_encode([
        'success' => true,
        'liffId' => defined('REGISTER_LIFF_ID') ? REGISTER_LIFF_ID : null,
        'apiUrl' => defined('API_BASE_URL') ? API_BASE_URL : null,
        'debug' => defined('REGISTER_LIFF_DEBUG_MODE') ? REGISTER_LIFF_DEBUG_MODE : null,
        'config_path' => $configFilePath
    ]);
} catch (Exception $e) {
    // バッファをクリアしてヘッダーを設定
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 