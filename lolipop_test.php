<?php
// エラー表示を最大化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<html><head><title>ロリポップサーバーテスト</title>';
echo '<meta charset="UTF-8">';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';
echo '</head><body>';
echo '<h1>ロリポップサーバーデータベース接続テスト</h1>';

// PHPの情報表示
echo '<h2>PHP情報</h2>';
echo '<div class="info">';
echo 'PHPのバージョン: ' . phpversion() . '<br>';
echo 'PDO拡張機能: ' . (extension_loaded('pdo') ? '<span class="success">有効</span>' : '<span class="error">無効</span>') . '<br>';
echo 'PDO MySQL拡張機能: ' . (extension_loaded('pdo_mysql') ? '<span class="success">有効</span>' : '<span class="error">無効</span>') . '<br>';
echo 'MySQLi拡張機能: ' . (extension_loaded('mysqli') ? '<span class="success">有効</span>' : '<span class="error">無効</span>') . '<br>';
echo '</div>';

// データベース接続テスト（localhost）
echo '<h2>localhostでの接続テスト</h2>';
try {
    $host = 'localhost';
    $dbname = 'LAA1207717-fgsquare';
    $user = 'LAA1207717';
    $pass = 'fg12345';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    echo "接続情報: {$dsn}<br>";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p class="success">接続成功！</p>';
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<h3>データベース内のテーブル:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
    // room_ticketsテーブルの確認
    if (in_array('room_tickets', $tables)) {
        echo '<p class="success">room_ticketsテーブルが存在します。</p>';
        
        // レコード数を取得
        $count = $pdo->query("SELECT COUNT(*) FROM room_tickets")->fetchColumn();
        echo "<p>room_ticketsテーブルのレコード数: {$count}件</p>";
        
        // テストレコードの挿入
        try {
            $stmt = $pdo->prepare("INSERT INTO room_tickets (room_number, square_order_id, status) VALUES (?, ?, ?)");
            $testRoomNumber = 'TEST' . date('Ymd_His');
            $result = $stmt->execute([$testRoomNumber, 'SQUARE_ORDER_TEST_' . time(), 'OPEN']);
            
            if ($result) {
                echo '<p class="success">テストレコードの挿入に成功しました。</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">テストレコード挿入エラー: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<p class="error">room_ticketsテーブルが見つかりません。</p>';
    }
    
} catch (PDOException $e) {
    echo '<p class="error">localhostでの接続エラー: ' . $e->getMessage() . '</p>';
}

// データベース接続テスト（指定ホスト）
echo '<h2>指定ホストでの接続テスト</h2>';
try {
    $host = 'mysql320.phy.lolipop.lan';
    $dbname = 'LAA1207717-fgsquare';
    $user = 'LAA1207717';
    $pass = 'fg12345';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    echo "接続情報: {$dsn}<br>";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p class="success">接続成功！</p>';
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<h3>データベース内のテーブル:</h3>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
} catch (PDOException $e) {
    echo '<p class="error">指定ホストでの接続エラー: ' . $e->getMessage() . '</p>';
}

echo '</body></html>'; 