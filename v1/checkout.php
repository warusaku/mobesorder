<?php
/**
 * チェックアウト処理API
 * 
 * 部屋のチェックアウト処理を行い、保留中の伝票を完了状態にします。
 * 
 * メソッド: POST
 * パラメータ:
 *   - room_number: チェックアウトする部屋番号（必須）
 *   - admin_key: 管理者認証キー（必須）
 * 
 * レスポンス:
 *   - success: 処理結果 (true/false)
 *   - message: 処理結果メッセージ
 *   - data: 処理されたチケット情報（処理成功時のみ）
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/RoomTicketService.php';
require_once __DIR__ . '/../lib/OrderService.php';

// CORSヘッダー設定
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONSリクエスト（プリフライトリクエスト）への対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POSTメソッド以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// リクエストボディの解析
$requestData = json_decode(file_get_contents('php://input'), true);

// POSTデータとリクエストボディを統合
$data = array_merge($_POST, $requestData ?? []);

// パラメータの検証
if (!isset($data['room_number']) || empty($data['room_number'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '部屋番号が指定されていません'
    ]);
    exit;
}

// 管理者認証
if (!isset($data['admin_key']) || $data['admin_key'] !== ADMIN_KEY) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '認証エラー: 管理者権限が必要です'
    ]);
    exit;
}

$roomNumber = $data['room_number'];

try {
    // RoomTicketServiceのインスタンスを作成
    $roomTicketService = new RoomTicketService();
    
    // 部屋の保留伝票を取得
    $roomTicket = $roomTicketService->getRoomTicketByRoomNumber($roomNumber);
    
    if (!$roomTicket) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "部屋 {$roomNumber} の保留伝票が見つかりません"
        ]);
        exit;
    }
    
    // OrderServiceのインスタンスを作成
    $orderService = new OrderService();
    
    // チェックアウト処理
    $result = $roomTicketService->checkoutRoomTicket($roomNumber);
    
    if (!$result) {
        throw new Exception("部屋 {$roomNumber} のチェックアウト処理に失敗しました");
    }
    
    // 注文データも更新
    $orderService->completeOrdersOnCheckout($roomNumber);
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => "部屋 {$roomNumber} のチェックアウト処理が完了しました",
        'data' => [
            'room_number' => $roomNumber,
            'square_order_id' => $roomTicket['square_order_id'],
            'checkout_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
    Utils::log("Checkout completed for room {$roomNumber}", 'INFO', 'CheckoutAPI');
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    Utils::log("Checkout error: " . $e->getMessage(), 'ERROR', 'CheckoutAPI');
} 