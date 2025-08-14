<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/LineService.php';

// Webhookからのリクエストを処理
$requestBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

// 署名を検証
$lineService = new LineService();
$isValid = $lineService->validateSignature($signature, $requestBody);

if (!$isValid) {
    Utils::log("Invalid LINE webhook signature", 'WARNING', 'LineWebhook');
    http_response_code(401);
    exit;
}

// Webhookデータを解析
$data = json_decode($requestBody, true);

if (!$data || !isset($data['events']) || !is_array($data['events'])) {
    Utils::log("Invalid webhook data", 'WARNING', 'LineWebhook');
    http_response_code(400);
    exit;
}

// イベントを処理
foreach ($data['events'] as $event) {
    $eventType = $event['type'] ?? '';
    $userId = $event['source']['userId'] ?? '';
    
    if (empty($eventType) || empty($userId)) {
        continue;
    }
    
    Utils::log("Received LINE event: $eventType from $userId", 'INFO', 'LineWebhook');
    
    switch ($eventType) {
        case 'message':
            handleMessageEvent($event, $lineService);
            break;
            
        case 'follow':
            handleFollowEvent($event, $lineService);
            break;
            
        case 'unfollow':
            handleUnfollowEvent($event, $lineService);
            break;
            
        default:
            // その他のイベントは無視
            break;
    }
}

// 成功レスポンスを返す
http_response_code(200);
echo json_encode(['success' => true]);
exit;

/**
 * メッセージイベントを処理
 */
function handleMessageEvent($event, $lineService) {
    $userId = $event['source']['userId'];
    $messageType = $event['message']['type'] ?? '';
    
    if ($messageType !== 'text') {
        return;
    }
    
    $text = $event['message']['text'] ?? '';
    
    // 部屋番号の入力を検出（例: "部屋101"）
    if (preg_match('/部屋(\d+)/i', $text, $matches)) {
        $roomNumber = $matches[1];
        
        // 部屋トークンを生成
        $auth = new Auth();
        $token = $auth->generateRoomToken($roomNumber, '', null, null);
        
        // LINEユーザーと部屋を紐付け
        $lineService->linkUserToRoom($userId, $roomNumber, $token);
        
        // 注文リンクを送信
        $lineService->sendOrderLink($userId, $token);
        
        Utils::log("Linked LINE user $userId to room $roomNumber", 'INFO', 'LineWebhook');
    } else {
        // 簡易的な応答
        $lineService->sendTextMessage($userId, "ルームサービスをご利用いただくには、「部屋〇〇〇」と入力してください。");
    }
}

/**
 * フォローイベントを処理
 */
function handleFollowEvent($event, $lineService) {
    $userId = $event['source']['userId'];
    
    // 歓迎メッセージを送信
    $message = "ご登録ありがとうございます。ルームサービスをご利用いただくには、「部屋〇〇〇」と入力してください。";
    $lineService->sendTextMessage($userId, $message);
    
    Utils::log("New LINE follower: $userId", 'INFO', 'LineWebhook');
}

/**
 * アンフォローイベントを処理
 */
function handleUnfollowEvent($event, $lineService) {
    $userId = $event['source']['userId'];
    
    // LINE紐付けを無効化
    $db = Database::getInstance();
    $db->execute(
        "UPDATE line_room_links SET is_active = 0 WHERE line_user_id = ?",
        [$userId]
    );
    
    Utils::log("LINE user unfollowed: $userId", 'INFO', 'LineWebhook');
} 