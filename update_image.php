<?php
/**
 * update_image.php - RTSP Readerシステム用画像更新API
 * 
 * 特定のデバイス(LacisID)の画像を強制的に更新するためのAPIエンドポイント。
 * ローカルサーバーに画像更新リクエストを送信します。
 */

// ログファイルの設定
$log_file = __DIR__ . '/../logs/php.log';

// 必要なディレクトリを作成
if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

// セキュリティキー（本番環境では強固なキーに変更してください）
$security_key = 'rtsp_test';

/**
 * ログにメッセージを記録する関数
 * 
 * @param string $message ログに記録するメッセージ
 * @return void
 */
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [update_image.php] $message" . PHP_EOL, FILE_APPEND);
}

// リクエストパラメータを取得
$lacis_id = filter_input(INPUT_GET, 'lacis_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// クライアント情報を取得
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

log_message("画像更新リクエスト受信: LacisID=$lacis_id, IP=$client_ip, UA=$user_agent");

// セキュリティチェック
if ($key !== $security_key) {
    log_message("エラー: セキュリティキーが無効です");
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid security key'
    ]);
    exit;
}

// LacisIDの検証
if (empty($lacis_id)) {
    log_message("エラー: LacisIDが指定されていません");
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'LacisID is required'
    ]);
    exit;
}

// データベース接続
require_once(__DIR__ . '/../dbconfig.php');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    log_message("エラー: データベース接続失敗: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

// デバイス情報を取得
$stmt = $mysqli->prepare("SELECT ip_address FROM sync_status WHERE lacis_id = ? LIMIT 1");
$stmt->bind_param("s", $lacis_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    log_message("エラー: デバイスが見つかりません: $lacis_id");
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Device not found'
    ]);
    $mysqli->close();
    exit;
}

$device = $result->fetch_assoc();
$device_ip = $device['ip_address'];

// 設定変更通知を作成
$notify_sql = "INSERT INTO config_change_notifications 
               (lacis_id, change_type, created_by, created_at, is_delivered, details) 
               VALUES (?, 'image_update', ?, NOW(), 0, ?)";
$user_id = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'web_interface';
$details = json_encode(['action' => 'update_image', 'requested_by' => $user_id, 'client_ip' => $client_ip]);

$notify_stmt = $mysqli->prepare($notify_sql);
$notify_stmt->bind_param("sss", $lacis_id, $user_id, $details);
$notify_stmt->execute();
$notification_id = $mysqli->insert_id;

log_message("画像更新通知を作成: ID=$notification_id, LacisID=$lacis_id");

$mysqli->close();

// 現在の画像の情報を取得
$image_path = __DIR__ . '/../latestimages/' . $lacis_id . '.jpg';
$timestamp_file = __DIR__ . '/../latestimages/' . $lacis_id . '.timestamp';

$image_exists = file_exists($image_path);
$last_update = file_exists($timestamp_file) ? date('Y-m-d H:i:s', file_get_contents($timestamp_file)) : 'never';
$image_size = $image_exists ? filesize($image_path) : 0;

// レスポンスを返す
echo json_encode([
    'status' => 'success',
    'message' => '画像更新リクエストを送信しました',
    'lacis_id' => $lacis_id,
    'notification_id' => $notification_id,
    'current_image' => [
        'exists' => $image_exists,
        'size' => $image_size,
        'last_update' => $last_update,
        'url' => $image_exists ? '/RTSP_reader/latestimages/' . $lacis_id . '.jpg?t=' . time() : null
    ],
    'timestamp' => date('Y-m-d H:i:s')
]); 