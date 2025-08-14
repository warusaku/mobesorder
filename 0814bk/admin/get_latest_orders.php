<?php
/**
 * 最新の注文データを取得するAPI
 * 
 * AJAXでポーリングするために使用され、新規注文や統計情報の変更を検出します。
 */

// エラー表示設定（デバッグ用、本番環境では無効化推奨）
ini_set('display_errors', 0);
error_reporting(0);

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// レスポンスをJSONに設定
header('Content-Type: application/json');

// ユーザー認証情報を確認
$userAuthFile = $rootPath . '/admin/user.json';
$users = [];

if (file_exists($userAuthFile)) {
    $jsonContent = file_get_contents($userAuthFile);
    $authData = json_decode($jsonContent, true);
    if (isset($authData['user'])) {
        $users = $authData['user'];
    }
}

// 認証チェック
$isLoggedIn = false;
if (isset($_SESSION['auth_user']) && array_key_exists($_SESSION['auth_user'], $users)) {
    $isLoggedIn = true;
} else {
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    exit;
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    // 最新の注文データを取得
    $query = "
        SELECT o.id, o.square_order_id, o.room_number, o.guest_name, o.order_status, 
               o.total_amount, o.order_datetime
        FROM orders o
        WHERE o.order_status != 'CANCELED'
        ORDER BY o.id DESC
        LIMIT 20
    ";
    
    $orders = $db->select($query);
    
    // 統計情報を取得
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE order_status != 'CANCELED') AS order_count,
            (SELECT SUM(total_amount) FROM orders WHERE order_status != 'CANCELED') AS total_amount,
            (SELECT COUNT(*) FROM line_room_links WHERE is_active = 1) AS active_rooms
    ";
    
    $statsResult = $db->selectOne($statsQuery);
    $stats = [
        'orderCount' => (int)($statsResult['order_count'] ?? 0),
        'totalAmount' => (float)($statsResult['total_amount'] ?? 0),
        'activeRooms' => (int)($statsResult['active_rooms'] ?? 0)
    ];
    
    // レスポンスを返す
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'エラーが発生しました: ' . $e->getMessage()
    ]);
} 