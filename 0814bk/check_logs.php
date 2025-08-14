<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込む
require_once 'api/config/config.php';
require_once 'api/lib/Database.php';

echo '<html><head><title>システムログの確認</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .debug { color: gray; }
</style>';
echo '</head><body>';
echo '<h1>システムログの確認</h1>';

try {
    // データベース接続
    $db = Database::getInstance();
    echo '<p>データベース接続成功</p>';
    
    // system_logsテーブルの存在確認
    $tableExists = $db->query("SHOW TABLES LIKE 'system_logs'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo '<p class="error">system_logsテーブルが存在しません。</p>';
        exit;
    }
    
    // 最新のログを取得（最大100件）
    $logs = $db->select("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 100");
    
    if (count($logs) === 0) {
        echo '<p>ログレコードが見つかりません。</p>';
        exit;
    }
    
    // ログを表示
    echo '<h2>最新のログ（直近100件）</h2>';
    echo '<table>';
    echo '<tr><th>ID</th><th>レベル</th><th>ソース</th><th>メッセージ</th><th>コンテキスト</th><th>日時</th></tr>';
    
    foreach ($logs as $log) {
        $levelClass = strtolower($log['log_level']);
        echo '<tr class="' . $levelClass . '">';
        echo '<td>' . $log['id'] . '</td>';
        echo '<td>' . $log['log_level'] . '</td>';
        echo '<td>' . $log['log_source'] . '</td>';
        echo '<td>' . htmlspecialchars($log['message']) . '</td>';
        echo '<td>' . (isset($log['context']) ? htmlspecialchars($log['context']) : '') . '</td>';
        echo '<td>' . $log['created_at'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    // テスト用ログを作成
    echo '<h2>テストログの作成</h2>';
    echo '<p>テスト用のログレコードを作成します。</p>';
    
    $result = $db->execute(
        "INSERT INTO system_logs (log_level, log_source, message) VALUES (?, ?, ?)",
        ['INFO', 'TestScript', 'システムログのテスト書き込み - ' . date('Y-m-d H:i:s')]
    );
    
    if ($result) {
        echo '<p class="info">テストログを作成しました。</p>';
    } else {
        echo '<p class="error">テストログの作成に失敗しました。</p>';
    }
    
} catch (Exception $e) {
    echo '<p class="error">エラー: ' . $e->getMessage() . '</p>';
}

echo '</body></html>'; 