<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<html><head><title>SQLiteデータベース接続テスト</title>';
echo '<meta charset="UTF-8">';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';
echo '</head><body>';
echo '<h1>SQLiteデータベース接続テスト</h1>';

// SQLiteを使用して接続テスト
echo '<h2>SQLiteによる接続テスト:</h2>';

try {
    // SQLiteデータベースファイルのパス
    $dbPath = 'test_db.sqlite';
    
    // PDO接続（SQLite）
    $dsn = "sqlite:{$dbPath}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p class="success">SQLiteデータベース接続成功！</p>';
    
    // room_ticketsテーブルが存在するか確認
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='room_tickets'");
    $tableExists = ($stmt->fetchColumn() !== false);
    
    if (!$tableExists) {
        echo '<p>room_ticketsテーブルが存在しないため、作成します...</p>';
        
        // テーブル作成
        $sql = "CREATE TABLE room_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_number TEXT NOT NULL UNIQUE,
            square_order_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'OPEN',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        echo '<p class="success">room_ticketsテーブルを作成しました。</p>';
    } else {
        echo '<p>room_ticketsテーブルは既に存在します。</p>';
    }
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<h3>データベース内のテーブル:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
    // テスト書き込み
    echo '<h3>テスト書き込み:</h3>';
    
    // 既存のレコードを削除（テスト用）
    $pdo->exec("DELETE FROM room_tickets WHERE room_number = 'TEST001'");
    
    // 新しいレコードを挿入
    $stmt = $pdo->prepare("INSERT INTO room_tickets (room_number, square_order_id, status) VALUES (?, ?, ?)");
    $result = $stmt->execute(['TEST001', 'SQUARE_ORDER_' . time(), 'OPEN']);
    
    if ($result) {
        echo '<p class="success">テストレコードの書き込みに成功しました。</p>';
        
        // 挿入されたレコードを確認
        $stmt = $pdo->query("SELECT * FROM room_tickets WHERE room_number = 'TEST001'");
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo '<h3>挿入されたレコード:</h3>';
        echo '<div class="info">';
        echo '<pre>';
        print_r($record);
        echo '</pre>';
        echo '</div>';
    } else {
        echo '<p class="error">テストレコードの書き込みに失敗しました。</p>';
    }
    
} catch (PDOException $e) {
    echo '<p class="error">SQLiteデータベース接続エラー: ' . $e->getMessage() . '</p>';
    
    // エラーの詳細情報
    echo '<h3>エラーの詳細:</h3>';
    echo '<div class="info">';
    echo '<p>エラーコード: ' . $e->getCode() . '</p>';
    echo '<p>エラー情報:<br>' . nl2br($e->getTraceAsString()) . '</p>';
    echo '</div>';
}

echo '<p>この例では、ローカルのSQLiteデータベースを使用して、データベース操作のテストを行いました。</p>';
echo '<p>ロリポップのサーバー環境では、MySQLの拡張機能が正しく設定されているはずなので、本番環境では正常に動作する可能性が高いです。</p>';

echo '</body></html>'; 