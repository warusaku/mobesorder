<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込み
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/lib/Database.php';

// データベース接続テスト
try {
    echo '<h1>データベース接続テスト</h1>';
    $db = Database::getInstance();
    echo '<p>データベース接続成功</p>';
    
    // テーブル一覧を取得
    $tables = $db->select("SHOW TABLES");
    echo '<h2>テーブル一覧</h2>';
    echo '<pre>';
    print_r($tables);
    echo '</pre>';
    
    // テーブル構造を確認
    if (!empty($tables)) {
        foreach ($tables as $table) {
            $tableName = reset($table);
            echo '<h3>テーブル: ' . $tableName . '</h3>';
            
            $columns = $db->select("DESCRIBE " . $tableName);
            echo '<pre>';
            print_r($columns);
            echo '</pre>';
        }
    }
} catch (Exception $e) {
    echo '<h1>データベース接続エラー</h1>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '<h2>スタックトレース</h2>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

// 設定情報（機密情報は隠す）
echo '<h1>設定情報</h1>';
echo '<ul>';
echo '<li>BASE_URL: ' . BASE_URL . '</li>';
echo '<li>DB_HOST: ' . DB_HOST . '</li>';
echo '<li>DB_NAME: ' . DB_NAME . '</li>';
echo '</ul>';
?> 