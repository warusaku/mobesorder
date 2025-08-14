<?php
/**
 * config.php - RTSPリーダーシステム用設定取得API
 * 
 * クライアントからのリクエストに応じて、デバイス設定を取得するAPIエンドポイント。
 * LacisIDに基づいて設定を返し、最終同期時間から変更があった場合のみ更新します。
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
    file_put_contents($log_file, "[$timestamp] [config.php] $message" . PHP_EOL, FILE_APPEND);
}

// リクエストパラメータを取得
$lacis_id = filter_input(INPUT_GET, 'lacis_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$last_sync = filter_input(INPUT_GET, 'last_sync', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// クライアント情報を取得
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

log_message("設定リクエスト受信: LacisID=$lacis_id, IP=$client_ip, UA=$user_agent");

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

// デバイス設定の取得
$sql = "SELECT config_json, updated_at FROM device_configs WHERE lacis_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $lacis_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    log_message("警告: LacisID {$lacis_id} の設定が見つかりません");
    
    // デフォルト設定を返す
    $default_config = [
        'status' => 'success',
        'lacis_id' => $lacis_id,
        'message' => 'Default configuration',
        'version' => '1.0.0',
        'last_modified' => date('Y-m-d H:i:s'),
        'settings' => [
            'rtsp_url' => '',
            'update_interval' => 60,
            'log_level' => 'info'
        ]
    ];
    
    // デフォルト設定を保存
    $config_json = json_encode($default_config);
    $insert_sql = "INSERT INTO device_configs (lacis_id, config_json, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
    $insert_stmt = $mysqli->prepare($insert_sql);
    $insert_stmt->bind_param("ss", $lacis_id, $config_json);
    $insert_stmt->execute();
    
    echo $config_json;
    exit;
}

// 設定を取得
$row = $result->fetch_assoc();
$config_json = $row['config_json'];
$updated_at = $row['updated_at'];

// 最終同期時間から変更があるか確認
if (!empty($last_sync) && $last_sync !== '0') {
    $last_sync_time = new DateTime($last_sync);
    $updated_time = new DateTime($updated_at);
    
    if ($last_sync_time >= $updated_time) {
        log_message("情報: LacisID {$lacis_id} の設定に変更はありません");
        http_response_code(304); // 変更なし
        exit;
    }
}

// 同期時間を記録
$update_sql = "INSERT INTO sync_status (lacis_id, last_sync, remote_ip) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_sync = NOW(), remote_ip = ?";
$update_stmt = $mysqli->prepare($update_sql);
$update_stmt->bind_param("sss", $lacis_id, $client_ip, $client_ip);
$update_stmt->execute();

// デバイス設定を返す
log_message("設定送信: LacisID {$lacis_id}");
header('Content-Type: application/json');
echo $config_json;

// データベース接続を閉じる
$mysqli->close(); 