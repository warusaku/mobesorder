<?php
/**
 * デバッグログを記録するためのエンドポイント
 * フロントエンドからのデバッグ情報をログファイルに記録する
 */

// CORSヘッダーを設定（クロスオリジンリクエストを許可）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエストの場合は、ここで終了
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// POSTリクエストのみ処理
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'POSTリクエストのみ許可されています']);
    exit;
}

// 入力データを取得
$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);

// JSONデータのデコードに失敗した場合
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'JSONデータのパースに失敗しました: ' . json_last_error_msg()]);
    exit;
}

// ログファイルのパスを設定
$log_dir = __DIR__ . '/../../../logs';
$log_file = $log_dir . '/debug_' . date('Y-m-d') . '.log';

// ログディレクトリが存在しない場合は作成
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// ログエントリの作成
$timestamp = date('Y-m-d H:i:s');
$type = isset($data['type']) ? $data['type'] : 'unknown';
$log_data = isset($data['data']) ? json_encode($data['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
$user_agent = isset($data['userAgent']) ? $data['userAgent'] : 'unknown';

// クライアントのIPアドレスを取得
$client_ip = $_SERVER['REMOTE_ADDR'];

// ログエントリの書式を整える
$log_entry = "[$timestamp] [$type] [$client_ip]\n";
$log_entry .= "User-Agent: $user_agent\n";

// roomInfoとuserProfileの情報があれば追加
if (isset($data['roomInfo'])) {
    $room_info = json_encode($data['roomInfo'], JSON_UNESCAPED_UNICODE);
    $log_entry .= "Room Info: $room_info\n";
}

if (isset($data['userProfile'])) {
    $user_profile = json_encode($data['userProfile'], JSON_UNESCAPED_UNICODE);
    $log_entry .= "User Profile: $user_profile\n";
}

$log_entry .= "Data:\n$log_data\n";
$log_entry .= "----------------------------------------\n";

// ログファイルに追記
$success = file_put_contents($log_file, $log_entry, FILE_APPEND);

// レスポンスを返す
if ($success !== false) {
    echo json_encode([
        'success' => true,
        'message' => 'ログが正常に記録されました',
        'file' => basename($log_file)
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'ログの記録に失敗しました'
    ]);
} 