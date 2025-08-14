<?php
/**
 * 注文履歴を取得するAPIエンドポイント
 * 
 * 引数:
 * - room_number: 部屋番号
 * - limit: 取得する最大件数 (デフォルト10)
 */

require_once '../../config/database.php';
require_once './lib/Logger.php';

// ログファイル名
$logFile = 'Order_api_history.log';

// パラメータ取得
$roomNumber = isset($_GET['room_number']) ? $_GET['room_number'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// リクエストログ
Logger::logRequest($logFile);

// エラーレスポンス関数
function sendErrorResponse($message, $code = 400) {
    global $logFile;
    Logger::error("エラーレスポンス: $message", $logFile);
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// パラメータチェック
if (!$roomNumber) {
    sendErrorResponse('Room number is required');
}

// レスポンスヘッダー設定
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // データベース接続
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
        DB_USER, 
        DB_PASSWORD
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 注文データを取得
    $query = "
        SELECT o.id, o.square_order_id, o.room_number, o.guest_name, o.order_status, 
               o.total_amount, o.note, o.order_datetime, o.checkout_datetime
        FROM orders o
        WHERE o.room_number = :room_number
        ORDER BY o.order_datetime DESC
        LIMIT :limit
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':room_number', $roomNumber);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 各注文の詳細情報を取得
    foreach ($orders as &$order) {
        $queryDetails = "
            SELECT od.id, od.order_id, od.square_item_id, od.product_name, 
                   od.unit_price, od.quantity, od.subtotal, od.note
            FROM order_details od
            WHERE od.order_id = :order_id
        ";
        
        $stmtDetails = $conn->prepare($queryDetails);
        $stmtDetails->bindParam(':order_id', $order['id']);
        $stmtDetails->execute();
        
        $orderDetails = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
        $order['items'] = $orderDetails;
    }
    
    // 日付を調整
    foreach ($orders as &$order) {
        // 必要に応じてフォーマット調整
        if (isset($order['order_datetime'])) {
            $order['order_datetime'] = date('Y-m-d H:i:s', strtotime($order['order_datetime']));
        }
        if (isset($order['checkout_datetime']) && $order['checkout_datetime']) {
            $order['checkout_datetime'] = date('Y-m-d H:i:s', strtotime($order['checkout_datetime']));
        }
    }
    
    // レスポンス作成
    $response = [
        'success' => true,
        'data' => $orders
    ];
    
    // レスポンスログ
    Logger::logResponse(['success' => true, 'count' => count($orders)], $logFile);
    
    // レスポンス送信
    echo json_encode($response);
    
} catch (PDOException $e) {
    // エラーログ
    Logger::error("データベースエラー: " . $e->getMessage(), $logFile);
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    // 一般エラーログ
    Logger::error("一般エラー: " . $e->getMessage(), $logFile);
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
} 