<?php
/**
 * LINE公式アカウントの情報を取得するスクリプト
 * 
 * @package Lumos
 * @subpackage API
 */

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 設定ファイルの読み込み
require_once __DIR__ . '/../config/lumos_config.php';

$channel_access_token = LUMOS_LINE_CHANNEL_ACCESS_TOKEN;
$api_url = 'https://api.line.me/v2/bot/info';

/**
 * LINE公式アカウントの情報を取得する関数
 * 
 * @return array アカウント情報
 */
function getLineAccountInfo() {
    global $channel_access_token, $api_url;
    
    $headers = [
        'Authorization: Bearer ' . $channel_access_token
    ];
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

// APIエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = getLineAccountInfo();
    
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 