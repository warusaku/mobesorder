<?php
/**
 * message_Transmission.php
 * ------------------------------------------------------------
 * Lumos Lite Console ↔ LUMOS モジュール API ブリッジ
 * 部屋一覧・メッセージ履歴取得・メッセージ送信を受け持つ。
 * Version: v20240526
 * Author : FG Dev Team
 * ------------------------------------------------------------
 * ルーティング
 *   GET  action=rooms                       : アクティブ部屋＋直近メッセージ(6件まで)
 *   GET  action=archived_rooms               : アーカイブ部屋一覧
 *   GET  action=messages&room_number={R}    : 指定部屋メッセージ全件
 *   GET  action=templates                    : テンプレート一覧取得
 *   POST action=send  (JSON)               : {room_number,text,type=text}
 *
 * 依存:
 *   - api/config/config.php
 *   - api/lib/Database.php
 *   - admin/adminsetting_registrer.php (lumos_console 設定取得)
 *
 * TODO: LUMOS モジュール確定後、sendToLumos() / fetchMessagesFromLumos() を
 *       実装に置き換える。
 */

// ------------------------------------------------------------
// 初期化
// ------------------------------------------------------------
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';

// adminsetting.json 読み込み (内部呼び出しフラグを立てる)
if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
    define('ADMIN_SETTING_INTERNAL_CALL', true);
}
require_once __DIR__ . '/adminsetting_registrer.php';

session_start();

// ------------------------------------------------------------
// ログ関数
// ------------------------------------------------------------
$logDir = $rootPath . '/logs';
$logFile = $logDir . '/message_Transmission.log';
$maxLogSize = 307200; // 300KB
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

function mtLog(string $msg, string $level = 'INFO'): void
{
    global $logFile, $maxLogSize;

    // ローテーション
    if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
        $content = file_get_contents($logFile);
        $retain  = (int)($maxLogSize * 0.2);
        $content = substr($content, -$retain);
        file_put_contents($logFile, $content, LOCK_EX);
    }

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp][$level] $msg" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ------------------------------------------------------------
// レスポンスヘルパ
// ------------------------------------------------------------
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------
// 認証チェック
// ------------------------------------------------------------
if (!isset($_SESSION['auth_user'])) {
    mtLog('Unauthenticated access', 'WARNING');
    jsonResponse(['success' => false, 'message' => '認証が必要です'], 401);
}

// ------------------------------------------------------------
// 設定取得
// ------------------------------------------------------------
$settings        = loadSettings();
$consoleSettings = $settings['lumos_console'] ?? [];
$lumosEndpoint   = $consoleSettings['lumos_endpoint'] ?? '';

// ------------------------------------------------------------
// ルーティング
// ------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $action  = $payload['action'] ?? $action;
}

switch ($action) {
    case 'rooms':
        handleRooms();
        break;
    case 'archived_rooms':
        handleArchivedRooms();
        break;
    case 'messages':
        $room = $_GET['room_number'] ?? '';
        $line_user_id = $_GET['line_user_id'] ?? '';
        handleMessages($room, $line_user_id);
        break;
    case 'templates':
        handleTemplates();
        break;
    case 'send':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'POST メソッドを使用してください'], 405);
        handleSend($payload ?? []);
        break;
    case 'send_all':
        if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'POST メソッドを使用してください'], 405);
        handleSendAll($payload ?? []);
        break;
    case 'send_single':
        if ($method !== 'POST') jsonResponse(['success'=>false,'message'=>'POST required'],405);
        handleSendSingle($payload??[]);
        break;
    case 'schedule_send':
        if ($method !== 'POST') jsonResponse(['success'=>false,'message'=>'POST required'],405);
        handleScheduleSend($payload??[]);
        break;
    case 'mock_receive':
        handleMockReceive();
        break;
    case 'order_details':
        $room_number = $_GET['room_number'] ?? '';
        handleOrderDetails($room_number);
        break;
    default:
        jsonResponse(['success' => false, 'message' => '無効なアクションです'], 400);
}

// ------------------------------------------------------------
// ハンドラ
// ------------------------------------------------------------
function handleRooms(): void
{
    mtLog('handleRooms invoked');
    try {
        $db = Database::getInstance();
        // 部屋・宿泊者一覧取得（is_active=1）
        $roomRows = $db->select(
            "SELECT room_number,
                    GROUP_CONCAT(DISTINCT user_name SEPARATOR ', ')      AS users,
                    GROUP_CONCAT(DISTINCT line_user_id SEPARATOR ',')   AS line_user_ids,
                    MIN(check_in_date)                                   AS check_in_date,
                    MAX(check_out_date)                                  AS check_out_date,
                    COUNT(*)                                             AS user_count
             FROM line_room_links
             WHERE is_active = 1
             GROUP BY room_number
             ORDER BY room_number"
        );

        $rooms = [];
        foreach ($roomRows as $row) {
            $roomNumber = $row['room_number'] ?? '';
            $users = array_map('trim', explode(',', $row['users'] ?? ''));
            $line_user_ids = array_map('trim', explode(',', $row['line_user_ids'] ?? ''));
            $user_latest_messages = [];
            $user_order_totals = [];
            
            // 部屋単位での注文合計を取得
            $roomOrderTotal = getRoomOrderTotal($roomNumber);
            
            foreach ($line_user_ids as $idx => $uid) {
                $user_latest_messages[$idx] = fetchLatestMessagesForUser($roomNumber, $uid, 3);
                $user_order_totals[$idx] = $roomOrderTotal; // 全員同じ部屋の合計金額
            }
            
            $rooms[]    = [
                'room_number'     => $roomNumber,
                'users'           => $users,
                'line_user_ids'   => $line_user_ids,
                'check_in_date'   => $row['check_in_date'] ?? '',
                'check_out_date'  => $row['check_out_date'] ?? '',
                'user_count'      => (int)$row['user_count'],
                'latest_messages' => $user_latest_messages,
                'order_totals'    => $user_order_totals,
            ];
        }
        jsonResponse(['success' => true, 'rooms' => $rooms]);
    } catch (Exception $e) {
        mtLog('DB error in handleRooms: ' . $e->getMessage(), 'ERROR');
        jsonResponse(['success' => false, 'message' => 'エラーが発生しました'], 500);
    }
}

function handleArchivedRooms(): void
{
    mtLog('handleArchivedRooms invoked');
    try {
        $db = Database::getInstance();
        // アーカイブ部屋・宿泊者一覧取得（is_active=0）
        $roomRows = $db->select(
            "SELECT room_number,
                    GROUP_CONCAT(DISTINCT user_name SEPARATOR ', ')      AS users,
                    GROUP_CONCAT(DISTINCT line_user_id SEPARATOR ',')   AS line_user_ids,
                    MIN(check_in_date)                                   AS check_in_date,
                    MAX(check_out_date)                                  AS check_out_date,
                    COUNT(*)                                             AS user_count
             FROM line_room_links
             WHERE is_active = 0
             GROUP BY room_number
             ORDER BY room_number DESC"
        );

        $rooms = [];
        foreach ($roomRows as $row) {
            $roomNumber = $row['room_number'] ?? '';
            $users = array_map('trim', explode(',', $row['users'] ?? ''));
            $line_user_ids = array_map('trim', explode(',', $row['line_user_ids'] ?? ''));
            $user_latest_messages = [];
            $user_order_totals = [];
            
            // 部屋単位での注文合計を取得
            $roomOrderTotal = getRoomOrderTotal($roomNumber);
            
            foreach ($line_user_ids as $idx => $uid) {
                $user_latest_messages[$idx] = fetchLatestMessagesForUser($roomNumber, $uid, 3);
                $user_order_totals[$idx] = $roomOrderTotal; // 全員同じ部屋の合計金額
            }
            
            $rooms[]    = [
                'room_number'     => $roomNumber,
                'users'           => $users,
                'line_user_ids'   => $line_user_ids,
                'check_in_date'   => $row['check_in_date'] ?? '',
                'check_out_date'  => $row['check_out_date'] ?? '',
                'user_count'      => (int)$row['user_count'],
                'latest_messages' => $user_latest_messages,
                'order_totals'    => $user_order_totals,
            ];
        }
        jsonResponse(['success' => true, 'rooms' => $rooms]);
    } catch (Exception $e) {
        mtLog('DB error in handleArchivedRooms: ' . $e->getMessage(), 'ERROR');
        jsonResponse(['success' => false, 'message' => 'エラーが発生しました'], 500);
    }
}

function handleMessages(string $roomNumber, string $lineUserId = ''): void
{
    if ($roomNumber === '') jsonResponse(['success' => false, 'message' => 'room_number が必要です'], 400);
    mtLog("handleMessages room={$roomNumber} line_user_id={$lineUserId}");
    $messages = fetchAllMessages($roomNumber, $lineUserId);
    jsonResponse(['success' => true, 'messages' => $messages]);
}

function handleTemplates(): void
{
    try {
        $db = Database::getInstance();
        $rows = $db->select("SELECT id, title, message_type, content FROM message_templates ORDER BY created_at DESC");
        jsonResponse(['success' => true, 'templates' => $rows]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'テンプレート取得に失敗しました'], 500);
    }
}

function handleSend(array $payload): void
{
    $roomNumber = $payload['room_number'] ?? '';
    $text       = trim($payload['text'] ?? '');
    $type       = $payload['type'] ?? 'text';

    if ($roomNumber === '' || $text === '') {
        jsonResponse(['success' => false, 'message' => 'room_number と text は必須です'], 400);
    }

    mtLog("handleSend → room={$roomNumber}, text=" . mb_substr($text, 0, 20));
    $result = sendToLumos([
        'room_number' => $roomNumber,
        'text'        => $text,
        'type'        => $type,
        'staff_id'    => $_SESSION['auth_user'] ?? 'staff',
    ]);

    if ($result['success'] ?? false) {
        jsonResponse(['success' => true]);
    }
    jsonResponse(['success' => false, 'message' => $result['message'] ?? '送信に失敗しました'], 500);
}

function handleSendAll(array $payload): void
{
    $text = trim($payload['text'] ?? '');
    $type = $payload['type'] ?? 'text';
    if ($text === '') {
        jsonResponse(['success' => false, 'message' => 'text は必須です'], 400);
    }
    mtLog("handleSendAll broadcast text=" . mb_substr($text,0,20));
    // TODO: LUMOS 実装後に全ユーザーブロードキャスト処理を実装
    // 現在は UI テスト用モック
    jsonResponse(['success'=>true,'mock'=>'broadcast']);
}

function handleSendSingle(array $payload):void{
    $lineId = $payload['line_user_id']??'';
    $text   = trim($payload['text']??'');
    if($lineId===''||$text==='') jsonResponse(['success'=>false,'message'=>'line_user_id and text required'],400);
    mtLog("send_single mock → {$lineId} : ".$text);
    jsonResponse(['success'=>true]);
}

function handleScheduleSend(array $payload):void{
    // timer send mock
    mtLog('schedule_send mock '.json_encode($payload));
    jsonResponse(['success'=>true]);
}

function handleMockReceive():void{
    // return dummy message for testing polling
    $rooms = ['101','102','103'];
    $msg   = ['こんにちは','清掃に伺います','タオルお願いします'];
    jsonResponse([
        'success'=>true,
        'room_number'=>$rooms[array_rand($rooms)],
        'text'=>$msg[array_rand($msg)],
    ]);
}

// ------------------------------------------------------------
// LUMOS 通信用スタブ関数
// ------------------------------------------------------------
function sendToLumos(array $payload): array
{
    global $lumosEndpoint;

    if ($lumosEndpoint === '') {
        mtLog('lumosEndpoint 未設定のためスタブ成功を返却', 'WARNING');
        return ['success' => true, 'message' => 'stub'];
    }

    // 例: POST /send endpoint
    try {
        $ch = curl_init($lumosEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST       => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT    => 5,
        ]);
        $response = curl_exec($ch);
        if ($response === false) throw new RuntimeException(curl_error($ch));
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        mtLog('sendToLumos HTTP ' . $code . ' -> ' . $response);
        $resArr = json_decode($response, true);
        return $resArr ?: ['success' => false, 'message' => 'invalid response'];
    } catch (Exception $e) {
        mtLog('sendToLumos error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function fetchLatestMessagesForUser(string $roomNumber, string $lineUserId, int $limit = 3) {
    $db = Database::getInstance();
    $rows = $db->select(
        "SELECT id, user_id, sender_type, message_type, message, created_at
         FROM messages
         WHERE room_number = :room AND user_id = :user
         ORDER BY created_at DESC
         LIMIT :limit",
        ['room' => $roomNumber, 'user' => $lineUserId, 'limit' => $limit]
    );
    return array_map(function($row){
        return [
            'id'          => $row['id'],
            'user_id'     => $row['user_id'],
            'sender'      => $row['sender_type'],
            'type'        => $row['message_type'],
            'text'        => $row['message'],
            'created_at'  => $row['created_at']
        ];
    }, $rows);
}

function fetchLatestMessages(string $roomNumber, int $limit = 6): array
{
    $db = Database::getInstance();
    $rows = $db->select(
        "SELECT id, user_id, sender_type, message_type, message, created_at
         FROM messages
         WHERE room_number = :room
         ORDER BY created_at DESC
         LIMIT :limit",
        ['room' => $roomNumber, 'limit' => $limit]
    );
    // array_reverseは不要。返り値の[0]が最新メッセージになる
    return array_map(function($row){
        return [
            'id'          => $row['id'],
            'user_id'     => $row['user_id'],
            'sender'      => $row['sender_type'],
            'type'        => $row['message_type'],
            'text'        => $row['message'],
            'created_at'  => $row['created_at']
        ];
    }, $rows);
}

function fetchAllMessages(string $roomNumber, string $lineUserId = ''): array
{
    $db = Database::getInstance();
    if ($lineUserId) {
        $rows = $db->select(
            "SELECT id, user_id, sender_type, message_type, message, created_at
             FROM messages
             WHERE room_number = :room AND user_id = :user
             ORDER BY created_at ASC",
            ['room' => $roomNumber, 'user' => $lineUserId]
        );
    } else {
        $rows = $db->select(
            "SELECT id, user_id, sender_type, message_type, message, created_at
             FROM messages
             WHERE room_number = :room
             ORDER BY created_at ASC",
            ['room' => $roomNumber]
        );
    }
    return array_map(function($row){
        return [
            'id'          => $row['id'],
            'user_id'     => $row['user_id'],
            'sender'      => $row['sender_type'],
            'type'        => $row['message_type'],
            'text'        => $row['message'],
            'created_at'  => $row['created_at']
        ];
    }, $rows);
}

function getUserOrderTotal(string $lineUserId): array
{
    if (empty($lineUserId)) {
        return ['total' => 0, 'count' => 0, 'formatted' => '¥0'];
    }
    
    try {
        $db = Database::getInstance();
        $result = $db->select(
            "SELECT 
                COALESCE(SUM(total_amount), 0) as total_amount,
                COUNT(*) as order_count
             FROM orders 
             WHERE line_user_id = :line_user_id 
             AND order_status = 'OPEN'",
            ['line_user_id' => $lineUserId]
        );
        
        $total = $result[0]['total_amount'] ?? 0;
        $count = $result[0]['order_count'] ?? 0;
        
        return [
            'total' => (float)$total,
            'count' => (int)$count,
            'formatted' => '¥' . number_format($total, 0)
        ];
    } catch (Exception $e) {
        mtLog('Error in getUserOrderTotal for user ' . $lineUserId . ': ' . $e->getMessage(), 'ERROR');
        return ['total' => 0, 'count' => 0, 'formatted' => '¥0'];
    }
}

function getRoomOrderTotal(string $roomNumber): array
{
    if (empty($roomNumber)) {
        return ['total' => 0, 'count' => 0, 'formatted' => '¥0'];
    }
    
    try {
        $db = Database::getInstance();
        // 部屋番号から該当するすべてのLINE User IDを取得し、それらの注文合計を計算
        $result = $db->select(
            "SELECT 
                COALESCE(SUM(o.total_amount), 0) as total_amount,
                COUNT(o.id) as order_count
             FROM orders o
             INNER JOIN line_room_links lrl ON o.line_user_id = lrl.line_user_id
             WHERE lrl.room_number = :room_number 
             AND o.order_status = 'OPEN'",
            ['room_number' => $roomNumber]
        );
        
        $total = $result[0]['total_amount'] ?? 0;
        $count = $result[0]['order_count'] ?? 0;
        
        return [
            'total' => (float)$total,
            'count' => (int)$count,
            'formatted' => '¥' . number_format($total, 0)
        ];
    } catch (Exception $e) {
        mtLog('Error in getRoomOrderTotal for room ' . $roomNumber . ': ' . $e->getMessage(), 'ERROR');
        return ['total' => 0, 'count' => 0, 'formatted' => '¥0'];
    }
}

function handleOrderDetails(string $roomNumber): void
{
    if (empty($roomNumber)) {
        jsonResponse(['success' => false, 'message' => 'room_number が必要です'], 400);
    }
    
    mtLog("handleOrderDetails room={$roomNumber}");
    
    try {
        $db = Database::getInstance();
        // 部屋番号に紐づく注文詳細を取得
        $orderDetails = $db->select(
            "SELECT 
                od.id,
                od.order_id,
                od.product_name,
                od.unit_price,
                od.quantity,
                od.subtotal,
                od.note,
                od.status,
                od.status_updated_at,
                od.status_updated_by,
                od.created_at,
                o.created_at as order_date,
                o.line_user_id,
                lrl.user_name
             FROM order_details od
             INNER JOIN orders o ON od.order_id = o.id
             INNER JOIN line_room_links lrl ON o.line_user_id = lrl.line_user_id
             WHERE lrl.room_number = :room_number 
             AND o.order_status = 'OPEN'
             ORDER BY od.created_at DESC, od.status ASC",
            ['room_number' => $roomNumber]
        );
        
        // ステータス別に分類
        $statusGroups = [
            'ordered' => [],
            'ready' => [],
            'delivered' => [],
            'cancelled' => []
        ];
        
        $totalAmount = 0;
        $totalItems = 0;
        
        foreach ($orderDetails as $detail) {
            $status = $detail['status'];
            $statusGroups[$status][] = [
                'id' => $detail['id'],
                'order_id' => $detail['order_id'],
                'product_name' => $detail['product_name'],
                'unit_price' => (float)$detail['unit_price'],
                'quantity' => (int)$detail['quantity'],
                'subtotal' => (float)$detail['subtotal'],
                'note' => $detail['note'] ?? '',
                'status' => $status,
                'status_updated_at' => $detail['status_updated_at'],
                'status_updated_by' => $detail['status_updated_by'],
                'created_at' => $detail['created_at'],
                'order_date' => $detail['order_date'],
                'user_name' => $detail['user_name'],
                'formatted_unit_price' => '¥' . number_format($detail['unit_price'], 0),
                'formatted_subtotal' => '¥' . number_format($detail['subtotal'], 0)
            ];
            
            // キャンセル以外は合計に含める
            if ($status !== 'cancelled') {
                $totalAmount += (float)$detail['subtotal'];
                $totalItems += (int)$detail['quantity'];
            }
        }
        
        $response = [
            'success' => true,
            'room_number' => $roomNumber,
            'order_details' => $statusGroups,
            'summary' => [
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
                'formatted_total' => '¥' . number_format($totalAmount, 0),
                'ordered_count' => count($statusGroups['ordered']),
                'ready_count' => count($statusGroups['ready']),
                'delivered_count' => count($statusGroups['delivered']),
                'cancelled_count' => count($statusGroups['cancelled'])
            ]
        ];
        
        jsonResponse($response);
        
    } catch (Exception $e) {
        mtLog('DB error in handleOrderDetails for room ' . $roomNumber . ': ' . $e->getMessage(), 'ERROR');
        mtLog('Stack trace: ' . $e->getTraceAsString(), 'ERROR');
        jsonResponse(['success' => false, 'message' => 'エラーが発生しました: ' . $e->getMessage()], 500);
    }
}
?>
