<?php
/**
 * dashboard_data.php - ダッシュボードデータAPI
 * 
 * ダッシュボードに表示するデータをJSON形式で返します
 */

// セキュリティ対策: 直接アクセスを制限するためのセッションチェック
session_start();
if (!isset($_SESSION['auth_user'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// レスポンス準備
$response = [
    'success' => true,
    'product_count' => 0,
    'category_count' => 0,
    'last_product_sync' => null,
    'last_category_sync' => null,
    'sync_interval' => 30,
    'server_time' => date('Y-m-d H:i:s'),
    'error' => ''
];

try {
    // データベース接続
    $db = Database::getInstance();
    
    // 商品数を取得
    $productCount = $db->selectOne("SELECT COUNT(*) as count FROM products");
    if ($productCount) {
        $response['product_count'] = $productCount['count'];
    }
    
    // カテゴリ数を取得
    $categoryCount = $db->selectOne("SELECT COUNT(*) as count FROM category_descripter");
    if ($categoryCount) {
        $response['category_count'] = $categoryCount['count'];
    }
    
    // 最終商品同期日時
    $productSync = $db->selectOne(
        "SELECT last_sync_time FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
        ['square', 'products']
    );
    if ($productSync) {
        $response['last_product_sync'] = $productSync['last_sync_time'];
    }
    
    // 最終カテゴリ同期日時
    $categorySync = $db->selectOne(
        "SELECT last_sync_time FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
        ['square', 'category_descripter']
    );
    if ($categorySync) {
        $response['last_category_sync'] = $categorySync['last_sync_time'];
    }
    
    // 同期間隔を取得
    $syncInterval = $db->selectOne(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'product_sync_interval'"
    );
    if ($syncInterval) {
        $response['sync_interval'] = (int)$syncInterval['setting_value'];
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    Utils::log("ダッシュボードデータ取得エラー: " . $e->getMessage(), 'ERROR', 'Dashboard');
}

// レスポンスのJSONヘッダーを設定
header('Content-Type: application/json');
echo json_encode($response);
exit; 