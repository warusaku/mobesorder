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

// ---- Custom order execute log ----
$rootDir = realpath(__DIR__ . '/../../../');
$logDir  = $rootDir . '/logs';
$execLog = $logDir . '/order_ececute_api.log';
$maxSize = 307200; // 300KB
if (!is_dir($logDir)) {@mkdir($logDir,0755,true);} 
function execLog($msg,$level='INFO'){
    global $execLog,$maxSize;
    // rotation
    if (file_exists($execLog) && filesize($execLog) > $maxSize){
        $content = file_get_contents($execLog);
        $keep    = intval($maxSize*0.2);
        $content = substr($content,-$keep);
        file_put_contents($execLog,"[".date('Y-m-d H:i:s')."] [INFO] log rotated\n".$content);
    }
    $line = "[".date('Y-m-d H:i:s')."] [$level] $msg\n";
    file_put_contents($execLog,$line,FILE_APPEND);
}
execLog('--- Request START '.($_SERVER['REQUEST_METHOD']).' '.$_SERVER['REQUEST_URI'].' ---');

// Square設定の open_ticket フラグ取得
$openTicketFlag = \OrderService::isSquareOpenTicketEnabled();

try {
    // POST リクエストのみ受け付ける
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッド', 405);
    }
    
    // リクエスト情報をログに記録
    execLog('Orders API: リクエスト受信 - '.date('Y-m-d H:i:s'));
    execLog('リクエストURI: '.$_SERVER['REQUEST_URI']);
    
    // 認証
    $auth = new Auth();
    $roomInfo = $auth->authenticateRequest();
    
    if (!$roomInfo) {
        throw new Exception('認証に失敗しました', 401);
    }
    
    execLog('認証成功 - 部屋番号: '.$roomInfo['room_number']);
    
    // JSON リクエストボディを取得
    $requestBody = file_get_contents('php://input');
    execLog('リクエストボディ: '.$requestBody);
    
    // LINE User IDの取得（リクエストヘッダーから）
    $lineUserId = isset($_SERVER['HTTP_X_LINE_USER_ID']) ? $_SERVER['HTTP_X_LINE_USER_ID'] : null;
    if ($lineUserId) {
        execLog('LINE User ID: '.$lineUserId);
    } else {
        execLog('LINE User ID が見つかりません');
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
    execLog('使用する部屋番号: '.$roomNumber);
    
    if($openTicketFlag){
        // ===== OPEN TICKET モード =====
        $ticketService = new RoomTicketService();
        
        // 既存の保留伝票を確認
        execLog('既存の保留伝票を確認中...');
        $existingTicket = $ticketService->getRoomTicketByRoomNumber($roomNumber);
        
        if (!$existingTicket) {
            execLog('既存の保留伝票なし。新規作成します。');
            $createdTicket = $ticketService->createRoomTicket($roomNumber, '', $lineUserId);
            $existingTicket = $ticketService->getRoomTicketByRoomNumber($roomNumber);
        }
        $result = $ticketService->addItemToRoomTicket($roomNumber,$requestData['items']);
        if(!$result){ throw new Exception('商品の追加に失敗しました',500);}    

        http_response_code(200);
        $resp=['success'=>true,'mode'=>'open_ticket','ticket_id'=>$existingTicket['id']];
        execLog('レスポンス: '.json_encode($resp));
        echo json_encode($resp);
    } else {
        // ===== PRODUCT モード =====
        $orderSvc = new OrderService();
        $orderRes = $orderSvc->createOrder($roomNumber,$requestData['items'],'',$requestData['notes']??'',$lineUserId);
        if(!$orderRes){ throw new Exception('注文作成に失敗しました',500);}        
        http_response_code(200);
        $resp=['success'=>true,'mode'=>'product','order_id'=>$orderRes['id'],'session_id'=>$orderRes['order_session_id']];
        execLog('レスポンス: '.json_encode($resp));
        echo json_encode($resp);
    }
    
} catch (Exception $e) {
    // エラーログに詳細を記録
    $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    $errorMessage = $e->getMessage();
    
    execLog('Orders API Error ('.$statusCode.'): '.$errorMessage,'ERROR');
    execLog('詳細: '.$e->getTraceAsString(),'ERROR');
    
    // リクエスト情報も記録
    if (isset($requestBody)) {
        execLog('失敗したリクエスト: '.$requestBody);
    }
    
    // エラー発生時は関連ログを確認
    $additionalInfo = '';
    foreach (['RoomTicketService.log', 'SquareService.log', 'OrderService.log'] as $logFile) {
        $logPath = __DIR__ . '/../../../logs/' . $logFile;
        if (file_exists($logPath)) {
            $lastLines = shell_exec('tail -20 ' . escapeshellarg($logPath));
            if ($lastLines) {
                execLog($logFile.' の最新ログ: '.$lastLines);
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
    execLog('--- Request END '.($_SERVER['REQUEST_METHOD']).' '.$_SERVER['REQUEST_URI'].' ---');
}); 