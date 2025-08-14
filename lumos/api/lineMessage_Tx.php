<?php
/**
 * LINE Messaging APIを使用してメッセージを送信するスクリプト
 * 
 * @package Lumos
 * @subpackage API
 */

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 設定ファイルの読み込み
require_once __DIR__ . '/../config/lumos_config.php';

// LINE Messaging APIの設定
$channel_access_token = LUMOS_LINE_CHANNEL_ACCESS_TOKEN;
$push_api_url = 'https://api.line.me/v2/bot/message/push';
$broadcast_api_url = 'https://api.line.me/v2/bot/message/broadcast';

/**
 * LINEメッセージを送信する関数
 * 
 * @param string $to 送信先ユーザーID
 * @param string $message 送信するメッセージ
 * @return array レスポンス情報
 */
function sendLineMessage($to, $message) {
    global $channel_access_token, $push_api_url, $broadcast_api_url;
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channel_access_token
    ];
    
    if ($to) {
        // 個別送信
        $post_data = [
            'to' => $to,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ];
        $url = $push_api_url;
    } else {
        // 一斉送信
        $post_data = [
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ];
        $url = $broadcast_api_url;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => $response
    ];
}

// テスト用のエンドポイント
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['message'])) {
        $to = $input['to'] ?? null;
        $result = sendLineMessage($to, $input['message']);
        // DB保存処理を追加
        if ($to && isset($input['message'])) {
            try {
                require_once __DIR__ . '/../config/lumos_config.php';
                $pdo = new PDO(
                    'mysql:host=' . LUMOS_DB_HOST . ';dbname=' . LUMOS_DB_NAME . ';charset=utf8mb4',
                    LUMOS_DB_USER,
                    LUMOS_DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                // room_number取得
                $stmt = $pdo->prepare("SELECT room_number FROM line_room_links WHERE line_user_id = ?");
                $stmt->execute([$to]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $room_number = $row ? $row['room_number'] : null;
                if ($room_number) {
                    $sql = "INSERT INTO messages (
                        room_number, user_id, sender_type, platform, message_type, message, status, created_at
                    ) VALUES (?, ?, 'staff', 'WEB', 'text', ?, 'sent', NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $room_number,
                        $to,
                        $input['message']
                    ]);
                }
            } catch (Exception $e) {
                // ログ出力など必要ならここに
            }
        }
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request parameters']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 