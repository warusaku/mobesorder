<?php
/**
 * LINE Webhookイベントを受信し、処理するスクリプト
 * 
 * @package Lumos
 * @subpackage API
 */

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 設定ファイルの読み込み
require_once __DIR__ . '/../config/lumos_config.php';

// LINE Webhookの設定
$channel_secret = LUMOS_LINE_CHANNEL_SECRET;

/**
 * 署名を検証する関数
 * 
 * @param string $signature リクエストヘッダーの署名
 * @param string $body リクエストボディ
 * @return bool 検証結果
 */
function verifySignature($signature, $body) {
    global $channel_secret;
    $hash = hash_hmac('sha256', $body, $channel_secret, true);
    $calculated_signature = base64_encode($hash);
    return $signature === $calculated_signature;
}

/**
 * 部屋番号を取得する関数
 * 
 * @param string $userId LINEユーザーID
 * @return string|null 部屋番号
 */
function getRoomNumber($userId) {
    try {
        $pdo = new PDO(
            "mysql:host=" . LUMOS_DB_HOST . ";dbname=" . LUMOS_DB_NAME . ";charset=utf8mb4",
            LUMOS_DB_USER,
            LUMOS_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $sql = "SELECT room_number FROM line_room_links WHERE line_user_id = ? AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['room_number'] : null;
    } catch (PDOException $e) {
        error_log('部屋番号取得エラー: ' . $e->getMessage());
        return null;
    }
}

/**
 * メッセージをデータベースに保存する関数
 * 
 * @param string $userId LINEユーザーID
 * @param string $message メッセージ内容
 * @return bool 保存結果
 */
function saveMessage($userId, $message) {
    try {
        $pdo = new PDO(
            "mysql:host=" . LUMOS_DB_HOST . ";dbname=" . LUMOS_DB_NAME . ";charset=utf8mb4",
            LUMOS_DB_USER,
            LUMOS_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // 部屋番号を取得
        $roomNumber = getRoomNumber($userId);
        if (!$roomNumber) {
            error_log('部屋番号が見つかりません: ' . $userId);
            return false;
        }

        // メッセージを保存
        $sql = "INSERT INTO messages (
                    room_number, user_id, sender_type, platform, 
                    message_type, message, status, created_at
                ) VALUES (?, ?, 'guest', 'LINE', 'text', ?, 'sent', NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$roomNumber, $userId, $message]);
    } catch (PDOException $e) {
        error_log('メッセージ保存エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 受信したメッセージをログに記録する関数
 * 
 * @param array $event イベントデータ
 */
function logMessage($event) {
    $log_dir = __DIR__ . '/../logs/line';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/webhook_' . date('Y-m-d') . '.log';
    $log_data = date('Y-m-d H:i:s') . ' - ' . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n";
    
    file_put_contents($log_file, $log_data, FILE_APPEND);
}

// Webhookリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $body = file_get_contents('php://input');
    
    // 署名の検証
    if (!verifySignature($signature, $body)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    
    // イベントデータの解析
    $events = json_decode($body, true)['events'] ?? [];
    
    foreach ($events as $event) {
        // メッセージイベントの場合
        if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
            // メッセージをログに記録
            logMessage($event);
            
            // メッセージをデータベースに保存
            $userId = $event['source']['userId'];
            $message = $event['message']['text'];
            saveMessage($userId, $message);
        }
    }
    
    // 成功レスポンス
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 