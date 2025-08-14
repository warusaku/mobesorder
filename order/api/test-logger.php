<?php
/**
 * ログ機能テスト用スクリプト（Loggerクラスを使わないバージョン）
 */

// 現在の時刻を取得
$now = date('Y-m-d H:i:s');

// ログファイルパス
$logDir = dirname(dirname(dirname(__FILE__))) . '/logs';
$logFile = $logDir . '/order-logger_php.log';

// 実行環境情報
$serverInfo = [
    'PHP_VERSION' => PHP_VERSION,
    'OS' => PHP_OS,
    'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
];

// サーバー情報をJSON形式に変換
$serverInfoJson = json_encode($serverInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * ログローテーション処理
 * @param string $logFile ログファイルのパス
 * @param int $maxSize 最大サイズ (バイト)
 */
function rotateLog($logFile, $maxSize = 307200) {
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        // サイズ超過の場合は単純に削除（バックアップなし）
        unlink($logFile);
    }
}

/**
 * ログを記録
 * @param string $message ログメッセージ
 * @param string $level ログレベル
 * @param string $logFile ログファイル
 * @return bool 成功したかどうか
 */
function writeLog($message, $level = 'INFO', $logFile) {
    // ログディレクトリが存在するか確認
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("ログディレクトリを作成できませんでした: $logDir");
            return false;
        }
    }
    
    // ログローテーション処理
    rotateLog($logFile);
    
    // タイムスタンプ
    $timestamp = date('Y-m-d H:i:s');
    
    // ログメッセージ作成
    $logEntry = "[$timestamp] [$level] $message\n";
    
    // ログ書き込み
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($result === false) {
        error_log("ログの書き込みに失敗しました: $logFile");
        return false;
    }
    
    return true;
}

// リクエスト情報作成
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$params = [];

if ($method === 'GET') {
    $params = $_GET;
} elseif ($method === 'POST') {
    $params = $_POST;
}

$paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
$requestInfo = "リクエスト: $method $uri | IP: $ip | UA: $userAgent | パラメータ: $paramsJson";

// 各種ログ記録のテスト
$logResult = writeLog("ログテスト実行: $now", 'INFO', $logFile);
$errorResult = writeLog("エラーログテスト: $now", 'ERROR', $logFile);
$requestResult = writeLog($requestInfo, 'INFO', $logFile);

// ディレクトリ情報
$dirExists = file_exists($logDir) ? 'はい' : 'いいえ';
$dirWritable = is_writable($logDir) ? 'はい' : 'いいえ';

// ログファイル情報
$fileExists = file_exists($logFile) ? 'はい' : 'いいえ';
$fileWritable = is_writable(dirname($logFile)) ? 'はい' : 'いいえ';
$fileSize = file_exists($logFile) ? filesize($logFile) . ' バイト' : 'ファイルなし';

// 結果をHTMLで表示
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>直接ログ出力テスト結果</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>直接ログ出力テスト結果</h1>
    
    <h2>テスト結果</h2>
    <p>通常ログ: <span class="<?php echo $logResult ? 'success' : 'error'; ?>">
        <?php echo $logResult ? '成功' : '失敗'; ?></span></p>
    <p>エラーログ: <span class="<?php echo $errorResult ? 'success' : 'error'; ?>">
        <?php echo $errorResult ? '成功' : '失敗'; ?></span></p>
    <p>リクエストログ: <span class="<?php echo $requestResult ? 'success' : 'error'; ?>">
        <?php echo $requestResult ? '成功' : '失敗'; ?></span></p>
    
    <h2>ログディレクトリ情報</h2>
    <p>パス: <?php echo $logDir; ?></p>
    <p>存在: <?php echo $dirExists; ?></p>
    <p>書き込み可能: <?php echo $dirWritable; ?></p>
    
    <h2>ログファイル情報</h2>
    <p>パス: <?php echo $logFile; ?></p>
    <p>存在: <?php echo $fileExists; ?></p>
    <p>親ディレクトリ書き込み可能: <?php echo $fileWritable; ?></p>
    <p>ファイルサイズ: <?php echo $fileSize; ?></p>
    
    <h2>サーバー情報</h2>
    <pre><?php echo $serverInfoJson; ?></pre>
    
    <h2>使用方法</h2>
    <p>このスクリプトはLoggerクラスを使わずに直接ファイル出力を行います。</p>
    <p>ログファイルパス:</p>
    <code><?php echo $logFile; ?></code>
    
    <h2>PHP標準エラーログ</h2>
    <p>エラーが発生している場合は、PHPのエラーログを確認してください。通常は以下のいずれかのパスにあります:</p>
    <ul>
        <li>/var/log/apache2/error.log (Apache)</li>
        <li>/var/log/nginx/error.log (Nginx)</li>
        <li>/var/log/php-fpm/error.log (PHP-FPM)</li>
        <li><?php echo ini_get('error_log'); ?> (PHP設定)</li>
    </ul>
</body>
</html> 