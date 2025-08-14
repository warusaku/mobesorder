<?php
// File: test_rtsp_fetch.php
// Description: RTSP接続確認用（ffmpegで1フレーム取得）

// ログ設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [RTSP_TEST] $msg" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// セキュリティコード設定（簡易的な保護）
$securecode = "rtsp_test";  // コードを変更して不正アクセスを防止

// パラメータ取得
$rtsp_url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
$security_key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
$display_mode = filter_input(INPUT_GET, 'mode', FILTER_SANITIZE_STRING) ?: 'image';

// 一時ファイルパス
$tmp_dir = sys_get_temp_dir() ?: '/tmp';
$tmp_file = $tmp_dir . '/test_rtsp_' . uniqid() . '.jpg';

// セキュリティチェック
$security_passed = (!$securecode || $security_key === $securecode);

// URLチェック
if (!$rtsp_url) {
    // URLが指定されていない場合はフォームを表示
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>RTSP接続テスト</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h2 { color: #333; }
            form { background: #f5f5f5; padding: 15px; border-radius: 5px; }
            label { display: block; margin: 10px 0 5px; }
            input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
            select { padding: 8px; }
            input[type="submit"] { background: #4CAF50; color: white; border: none; padding: 10px 15px; margin-top: 15px; cursor: pointer; }
            .note { color: #666; font-size: 0.8em; margin-top: 5px; }
        </style>
    </head>
    <body>
        <h2>RTSP接続テスト</h2>
        <form method="get">
            <label for="url">RTSP URL:</label>
            <input type="text" id="url" name="url" placeholder="rtsp://username:password@ip:port/stream" required>
            <p class="note">例: rtsp://admin:pass@192.168.1.100:554/stream1</p>
            
            <?php if ($securecode): ?>
            <label for="key">セキュリティコード:</label>
            <input type="text" id="key" name="key" placeholder="アクセスキーを入力">
            <?php endif; ?>
            
            <label for="mode">表示モード:</label>
            <select id="mode" name="mode">
                <option value="image">画像表示</option>
                <option value="json">JSON応答</option>
            </select>
            
            <input type="submit" value="テスト実行">
        </form>
    </body>
    </html>
    <?php
    exit;
}

// セキュリティチェック失敗
if (!$security_passed) {
    http_response_code(403);
    if ($display_mode === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'セキュリティコードが不正です'
        ]);
    } else {
        echo "<h2>エラー: セキュリティコードが必要です</h2>";
    }
    log_message("セキュリティコード不正: $rtsp_url");
    exit;
}

// ログ出力
log_message("RTSPテスト実行: $rtsp_url");

// 開始時間記録
$start_time = microtime(true);

// FFMPEGコマンド構築
$rtsp_escaped = escapeshellarg($rtsp_url);
$tmp_escaped = escapeshellarg($tmp_file);
$cmd = "ffmpeg -rtsp_transport tcp -i $rtsp_escaped -frames:v 1 -y $tmp_escaped 2>&1";

// コマンド実行
$output = shell_exec($cmd);
$execution_time = round((microtime(true) - $start_time) * 1000, 2);

// 結果確認
$success = file_exists($tmp_file);
$filesize = $success ? filesize($tmp_file) : 0;

// 結果をログに記録
if ($success) {
    log_message("RTSPフレーム取得成功: $rtsp_url (${filesize}バイト, ${execution_time}ms)");
} else {
    log_message("RTSPフレーム取得失敗: $rtsp_url - $output");
}

// 応答形式に応じて出力
if ($display_mode === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $success ? 'success' : 'error',
        'message' => $success ? 'フレーム取得成功' : 'フレーム取得失敗',
        'rtsp_url' => $rtsp_url,
        'execution_time_ms' => $execution_time,
        'filesize_bytes' => $filesize,
        'output' => $success ? '' : $output
    ]);
} else {
    if ($success) {
        header('Content-Type: image/jpeg');
        readfile($tmp_file);
    } else {
        echo "<h2>RTSPストリーム取得失敗</h2>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
}

// 一時ファイル削除
if (file_exists($tmp_file)) {
    unlink($tmp_file);
}
?> 