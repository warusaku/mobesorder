<?php
/**
 * 注文処理APIエンドポイント - エラーハンドリング強化版
 */

// エラーレポート設定
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/orders_api_error.log');

// リクエストヘッダ設定
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-LINE-USER-ID');

// OPTIONSリクエストの場合は早期リターン
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Utils.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/OrderService.php';
require_once __DIR__ . '/../../lib/RoomTicketService.php';
require_once __DIR__ . '/../../lib/SquareService.php';

try {
    // POST リクエストのみ受け付ける
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッド', 405);
    }
    
    // リクエスト情報をログに記録
    error_log('Orders API: リクエスト受信 - ' . date('Y-m-d H:i:s'));
    error_log('リクエストURI: ' . $_SERVER['REQUEST_URI']);
    
    // 認証
    $auth = new Auth();
    $roomInfo = $auth->authenticateRequest();
    
    if (!$roomInfo) {
        throw new Exception('認証に失敗しました', 401);
    }
    
    error_log('認証成功 - 部屋番号: ' . $roomInfo['room_number']);
    
    // JSON リクエストボディを取得
    $requestBody = file_get_contents('php://input');
    error_log('リクエストボディ: ' . $requestBody);
    
    // LINE User IDの取得（リクエストヘッダーから）
    $lineUserId = isset($_SERVER['HTTP_X_LINE_USER_ID']) ? $_SERVER['HTTP_X_LINE_USER_ID'] : null;
    if ($lineUserId) {
        error_log('LINE User ID が見つかりました: ' . $lineUserId);
    } else {
        error_log('LINE User ID が見つかりません');
    }
    
    $requestData = json_decode($requestBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSONの解析に失敗しました: ' . json_last_error_msg(), 400);
    }
    
    // 必須パラメータのチェック
    if (empty($requestData['items']) || !is_array($requestData['items'])) {
        throw new Exception('注文アイテムが指定されていないか、無効な形式です', 400);
    }
    
    $roomNumber = $roomInfo['room_number'];
    error_log('使用する部屋番号: ' . $roomNumber);
    
    // RoomTicketServiceを使用して注文を処理
    $ticketService = new RoomTicketService();
    
    // 既存の保留伝票を確認
    error_log('既存の保留伝票を確認中...');
    $existingTicket = $ticketService->getRoomTicketByRoomNumber($roomNumber);
    
    if (!$existingTicket) {
        error_log('既存の保留伝票なし。新規作成します。');
        // LINE User IDを渡して新規チケット作成
        $createdTicket = $ticketService->createRoomTicket($roomNumber, '', $lineUserId);
        if (!$createdTicket) {
            throw new Exception('保留伝票の作成に失敗しました', 500);
        }
        // 再度、作成された保留伝票を取得
        $existingTicket = $ticketService->getRoomTicketByRoomNumber($roomNumber);
        if (!$existingTicket) {
            throw new Exception('保留伝票の取得に失敗しました', 500);
        }
    } else {
        error_log('既存の保留伝票を使用します: ' . print_r($existingTicket, true));
    }
    
    // 商品アイテムを保留伝票に追加
    error_log('商品アイテムを追加中...');
    $items = $requestData['items'];
    $result = $ticketService->addItemToRoomTicket($roomNumber, $items);
    
    if (!$result) {
        throw new Exception('商品の追加に失敗しました', 500);
    }
    
    error_log('注文処理が完了しました');
    
    // 成功レスポンスを返す
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '注文が正常に処理されました',
        'ticket_id' => $existingTicket['id'],
        'order_status' => $result['status'] ?? 'OPEN'
    ]);
    
} catch (Exception $e) {
    // エラーログに詳細を記録
    $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    $errorMessage = $e->getMessage();
    
    error_log('Orders API Error (' . $statusCode . '): ' . $errorMessage);
    error_log('詳細: ' . $e->getTraceAsString());
    
    // リクエスト情報も記録
    if (isset($requestBody)) {
        error_log('失敗したリクエスト: ' . $requestBody);
    }
    
    // エラー発生時は関連ログを確認
    $additionalInfo = '';
    foreach (['RoomTicketService.log', 'SquareService.log', 'OrderService.log'] as $logFile) {
        $logPath = __DIR__ . '/../../../logs/' . $logFile;
        if (file_exists($logPath)) {
            $lastLines = shell_exec('tail -20 ' . escapeshellarg($logPath));
            if ($lastLines) {
                error_log($logFile . ' の最新ログ: ' . $lastLines);
                $additionalInfo .= substr($lastLines, 0, 100) . '... ';
            }
        }
    }
    
    // クライアントに返すエラーレスポンス
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'code' => $statusCode,
        'request_id' => uniqid('err_')
    ]);
}

// 特に時間のかかる追加のログ記録や後処理
register_shutdown_function(function() {
    error_log('Orders API リクエスト処理完了 - ' . date('Y-m-d H:i:s'));
}); 