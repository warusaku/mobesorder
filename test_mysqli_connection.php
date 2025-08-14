<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込む
require_once 'api/config/config.php';

echo '<html><head><title>データベース接続テスト (MySQLi)</title>';
echo '<meta charset="UTF-8">';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';
echo '</head><body>';
echo '<h1>データベース接続テスト (MySQLi)</h1>';

// 設定情報を表示
echo '<h2>現在の設定:</h2>';
echo '<div class="info">';
echo '<p>ホスト: ' . DB_HOST . '</p>';
echo '<p>データベース名: ' . DB_NAME . '</p>';
echo '<p>ユーザー名: ' . DB_USER . '</p>';
echo '<p>パスワード: ' . str_repeat('*', strlen(DB_PASS)) . '</p>';
echo '</div>';

// MySQLiを使用して接続テスト
echo '<h2>MySQLiによる接続テスト:</h2>';

try {
    // MySQLi接続
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 接続エラーチェック
    if ($mysqli->connect_errno) {
        throw new Exception("MySQLi接続エラー: " . $mysqli->connect_error);
    }
    
    echo '<p class="success">データベース接続成功！</p>';
    
    // 文字セットを設定
    $mysqli->set_charset("utf8mb4");
    
    // テーブル一覧を取得
    $result = $mysqli->query("SHOW TABLES");
    $tables = [];
    
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $tables[] = $row[0];
    }
    
    echo '<h3>データベース内のテーブル:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
    // テスト書き込み
    if (in_array('system_logs', $tables)) {
        echo '<h3>テスト書き込み:</h3>';
        
        $stmt = $mysqli->prepare("INSERT INTO system_logs (log_level, log_source, message) VALUES (?, ?, ?)");
        $logLevel = 'INFO';
        $logSource = 'ConnectionTest';
        $message = 'MySQLiによるデータベース接続テスト - ' . date('Y-m-d H:i:s');
        
        $stmt->bind_param("sss", $logLevel, $logSource, $message);
        $result = $stmt->execute();
        
        if ($result) {
            echo '<p class="success">テストログの書き込みに成功しました。</p>';
        } else {
            echo '<p class="error">テストログの書き込みに失敗しました: ' . $stmt->error . '</p>';
        }
        
        $stmt->close();
    }
    
    // 接続を閉じる
    $mysqli->close();
    
} catch (Exception $e) {
    echo '<p class="error">' . $e->getMessage() . '</p>';
    
    // エラーの詳細情報
    echo '<h3>エラーの詳細:</h3>';
    echo '<div class="info">';
    echo '<p>エラー情報:<br>' . nl2br($e->getTraceAsString()) . '</p>';
    echo '</div>';
    
    // 代替接続方法のテスト
    echo '<h2>代替接続方法のテスト:</h2>';
    
    try {
        // IPv4アドレスを使用してみる
        $hostIp = gethostbyname(DB_HOST);
        echo '<p>ホスト名をIPアドレスに解決: ' . $hostIp . '</p>';
        
        $mysqli = new mysqli($hostIp, DB_USER, DB_PASS, DB_NAME);
        
        if ($mysqli->connect_errno) {
            throw new Exception("IPアドレスを使用したMySQLi接続エラー: " . $mysqli->connect_error);
        }
        
        echo '<p class="success">IPアドレスを使用した接続に成功しました！</p>';
        $mysqli->close();
    } catch (Exception $e2) {
        echo '<p class="error">' . $e2->getMessage() . '</p>';
    }
}

echo '</body></html>'; 