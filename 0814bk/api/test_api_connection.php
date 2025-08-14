<?php
// 設定読み込み
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/Utils.php';

// ログ関数
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    echo $logMessage;
}

// Square APIの接続テスト (証明書あり)
function testWithCertBundle() {
    logMessage("証明書バンドル使用でのテスト開始", 'INFO');
    
    // cURLセッション初期化
    $ch = curl_init('https://connect.squareup.com/v2/locations');
    
    // SSL検証有効、証明書バンドル使用
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/certificates/cacert.pem');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Square-Version: 2023-09-25',
        'Authorization: Bearer ' . SQUARE_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // デバッグモード有効化
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // リクエスト実行
    logMessage("リクエスト送信中...", 'INFO');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // デバッグ情報を取得
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    logMessage("詳細ログ: " . $verboseLog, 'DEBUG');
    
    // エラーチェック
    if ($response === false) {
        $error = curl_error($ch);
        logMessage("cURLエラー: " . $error, 'ERROR');
        curl_close($ch);
        return "失敗: " . $error;
    }
    
    curl_close($ch);
    
    logMessage("HTTPステータスコード: " . $httpCode, 'INFO');
    logMessage("レスポンス: " . substr($response, 0, 200) . '...', 'INFO');
    return "成功: HTTPコード " . $httpCode;
}

// Square APIの接続テスト (SSL検証無効)
function testWithSSLDisabled() {
    logMessage("SSL検証無効でのテスト開始", 'INFO');
    
    // cURLセッション初期化
    $ch = curl_init('https://connect.squareup.com/v2/locations');
    
    // SSL検証無効
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Square-Version: 2023-09-25',
        'Authorization: Bearer ' . SQUARE_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // リクエスト実行
    logMessage("リクエスト送信中...", 'INFO');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // エラーチェック
    if ($response === false) {
        $error = curl_error($ch);
        logMessage("cURLエラー: " . $error, 'ERROR');
        curl_close($ch);
        return "失敗: " . $error;
    }
    
    curl_close($ch);
    
    logMessage("HTTPステータスコード: " . $httpCode, 'INFO');
    logMessage("レスポンス: " . substr($response, 0, 200) . '...', 'INFO');
    return "成功: HTTPコード " . $httpCode;
}

// PHP環境情報
logMessage("PHP バージョン: " . phpversion(), 'INFO');
logMessage("cURL バージョン: " . curl_version()['version'], 'INFO');
logMessage("SSL バージョン: " . curl_version()['ssl_version'], 'INFO');

// 証明書ファイルの確認
$certPath = __DIR__ . '/certificates/cacert.pem';
if (file_exists($certPath)) {
    $certSize = filesize($certPath);
    logMessage("証明書ファイル存在: " . $certPath . " (サイズ: " . $certSize . " バイト)", 'INFO');
} else {
    logMessage("証明書ファイルが見つかりません: " . $certPath, 'ERROR');
}

// 設定情報の確認
logMessage("Square 設定:", 'INFO');
logMessage("  Environment: " . SQUARE_ENVIRONMENT, 'INFO');
logMessage("  Location ID: " . SQUARE_LOCATION_ID, 'INFO');
logMessage("  Access Token: " . substr(SQUARE_ACCESS_TOKEN, 0, 5) . "...", 'INFO');

// HTML出力開始
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Square API接続テスト</title>
    <style>
        body { font-family: sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        .container { max-width: 800px; margin: 0 auto; }
        .test-result { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        pre { background: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 5px;
              font-family: monospace; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Square API接続テスト</h1>
        
        <h2>PHP環境情報</h2>
        <pre>
PHP バージョン: <?php echo phpversion(); ?>
cURL バージョン: <?php echo curl_version()['version']; ?>
SSL バージョン: <?php echo curl_version()['ssl_version']; ?>
証明書パス: <?php echo $certPath; ?> (<?php echo file_exists($certPath) ? 'ファイル存在' : '存在しない'; ?>)
        </pre>
        
        <h2>テスト1: 証明書バンドル使用（SSL検証あり）</h2>
        <div class="test-result">
            <?php $result1 = testWithCertBundle(); ?>
            <p class="<?php echo strpos($result1, '成功') !== false ? 'success' : 'error'; ?>">
                <strong>結果:</strong> <?php echo $result1; ?>
            </p>
        </div>
        
        <h2>テスト2: SSL検証無効</h2>
        <div class="test-result">
            <?php $result2 = testWithSSLDisabled(); ?>
            <p class="<?php echo strpos($result2, '成功') !== false ? 'success' : 'error'; ?>">
                <strong>結果:</strong> <?php echo $result2; ?>
            </p>
        </div>
    </div>
</body>
</html> 