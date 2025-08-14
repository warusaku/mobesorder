<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込む
require_once 'api/config/config.php';

echo '<html><head><title>デバッグログビューア</title>';
echo '<meta charset="UTF-8">';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .debug { color: gray; }
    pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; max-height: 500px; }
    .log-entry { margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
    .controls { margin-bottom: 20px; }
    button, select { padding: 8px; margin-right: 10px; }
</style>';
echo '</head><body>';
echo '<h1>デバッグログビューア</h1>';

// ログファイルのパスを設定
$logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/api/logs/app.log';
$fallbackLogFile = __DIR__ . '/api/logs/fallback.log';

// ロギング情報
echo '<div class="controls">';
echo '<p>現在のログ設定:</p>';
echo '<ul>';
echo '<li>デバッグモード: ' . (defined('DEBUG_MODE') && DEBUG_MODE ? '有効' : '無効') . '</li>';
echo '<li>ログレベル: ' . (defined('LOG_LEVEL') ? LOG_LEVEL : 'N/A') . '</li>';
echo '<li>ログファイル: ' . $logFile . '</li>';
echo '</ul>';

// ログレベルフィルター
$levels = ['ALL', 'DEBUG', 'INFO', 'WARNING', 'ERROR'];
$selectedLevel = isset($_GET['level']) ? $_GET['level'] : 'ALL';

echo '<form method="get">';
echo '<label for="level">ログレベルフィルター: </label>';
echo '<select id="level" name="level">';
foreach ($levels as $level) {
    $selected = ($level === $selectedLevel) ? 'selected' : '';
    echo "<option value=\"{$level}\" {$selected}>{$level}</option>";
}
echo '</select>';
echo '<button type="submit">フィルター適用</button>';
echo '</form>';
echo '</div>';

// メインログファイルの内容を表示
echo '<h2>アプリケーションログ</h2>';
if (file_exists($logFile)) {
    $log = file_get_contents($logFile);
    if (!empty($log)) {
        echo '<div class="log-container">';
        
        // ログを行ごとに分割
        $lines = explode("\n", $log);
        $filteredLines = [];
        
        foreach ($lines as $line) {
            // 空行はスキップ
            if (empty(trim($line))) continue;
            
            // ログレベルでフィルタリング
            if ($selectedLevel !== 'ALL') {
                if (strpos($line, "[$selectedLevel]") === false) {
                    continue;
                }
            }
            
            $class = 'debug';
            if (strpos($line, '[ERROR]') !== false) $class = 'error';
            else if (strpos($line, '[WARNING]') !== false) $class = 'warning';
            else if (strpos($line, '[INFO]') !== false) $class = 'info';
            
            echo '<div class="log-entry ' . $class . '">';
            echo htmlspecialchars($line);
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<p>ログは空です。</p>';
    }
} else {
    echo '<p class="error">ログファイルが見つかりません: ' . htmlspecialchars($logFile) . '</p>';
}

// フォールバックログファイルの内容を表示
echo '<h2>フォールバックログ</h2>';
if (file_exists($fallbackLogFile)) {
    $log = file_get_contents($fallbackLogFile);
    if (!empty($log)) {
        echo '<pre>' . htmlspecialchars($log) . '</pre>';
    } else {
        echo '<p>フォールバックログは空です。</p>';
    }
} else {
    echo '<p>フォールバックログファイルが見つかりません。</p>';
}

// PHPのシステムログも表示（可能であれば）
echo '<h2>PHPエラーログ</h2>';
$errorLogPath = ini_get('error_log');
if ($errorLogPath && file_exists($errorLogPath)) {
    // ファイルが大きすぎる場合は末尾だけを表示
    $errorLog = file_get_contents($errorLogPath, false, null, -50000); // 最後の50KBのみ
    echo '<pre>' . htmlspecialchars($errorLog) . '</pre>';
} else {
    echo '<p>PHPエラーログファイルにアクセスできません。</p>';
}

echo '<p><a href="test_dashboard.php">テストダッシュボードに戻る</a></p>';
echo '</body></html>'; 