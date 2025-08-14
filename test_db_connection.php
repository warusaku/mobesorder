<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込む
require_once 'api/config/config.php';

echo '<html><head><title>データベース接続テスト</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';
echo '</head><body>';
echo '<h1>データベース接続テスト</h1>';

// 設定情報を表示
echo '<h2>現在の設定:</h2>';
echo '<div class="info">';
echo '<p>ホスト: ' . DB_HOST . '</p>';
echo '<p>データベース名: ' . DB_NAME . '</p>';
echo '<p>ユーザー名: ' . DB_USER . '</p>';
echo '<p>パスワード: ' . str_repeat('*', strlen(DB_PASS)) . '</p>';
echo '</div>';

// 標準PDOを使用して接続テスト
echo '<h2>PDOによる接続テスト:</h2>';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    echo '<p>DSN: ' . $dsn . '</p>';
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo '<p class="success">データベース接続成功！</p>';
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<h3>データベース内のテーブル:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
    // テスト書き込み
    if (in_array('system_logs', $tables)) {
        echo '<h3>テスト書き込み:</h3>';
        $stmt = $pdo->prepare("INSERT INTO system_logs (log_level, log_source, message) VALUES (?, ?, ?)");
        $result = $stmt->execute(['INFO', 'ConnectionTest', 'データベース接続テスト - ' . date('Y-m-d H:i:s')]);
        
        if ($result) {
            echo '<p class="success">テストログの書き込みに成功しました。</p>';
        } else {
            echo '<p class="error">テストログの書き込みに失敗しました。</p>';
        }
    }
    
} catch (PDOException $e) {
    echo '<p class="error">データベース接続エラー: ' . $e->getMessage() . '</p>';
    
    // エラーの詳細情報
    echo '<h3>エラーの詳細:</h3>';
    echo '<div class="info">';
    echo '<p>エラーコード: ' . $e->getCode() . '</p>';
    echo '<p>エラー情報:<br>' . nl2br($e->getTraceAsString()) . '</p>';
    echo '</div>';
    
    // 代替接続方法のテスト
    echo '<h2>代替接続方法のテスト:</h2>';
    
    try {
        // IPv4アドレスを使用してみる
        $hostIp = gethostbyname(DB_HOST);
        echo '<p>ホスト名をIPアドレスに解決: ' . $hostIp . '</p>';
        
        $dsn = "mysql:host=" . $hostIp . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        echo '<p>新しいDSN: ' . $dsn . '</p>';
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        echo '<p class="success">IPアドレスを使用した接続に成功しました！</p>';
    } catch (PDOException $e2) {
        echo '<p class="error">IPアドレスを使用した接続にも失敗しました: ' . $e2->getMessage() . '</p>';
    }
}

echo '</body></html>'; 