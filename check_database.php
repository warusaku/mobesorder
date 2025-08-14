<?php
/**
 * データベース接続テストスクリプト
 * 
 * このスクリプトはデータベース接続とテーブル構造を確認するためのものです。
 * config.phpを読み込み、データベースに接続し、テーブルが存在するかどうかと
 * それぞれのテーブルの構造を表示します。
 */

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込む
require_once 'api/config/config.php';

echo '<html><head><title>データベース確認</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2, h3 { color: #333; }
    .success { color: green; }
    .error { color: red; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
</style>';
echo '</head><body>';
echo '<h1>データベース接続確認</h1>';

// データベース接続を試行
try {
    echo '<h2>接続情報</h2>';
    echo '<p>ホスト: ' . DB_HOST . '</p>';
    echo '<p>データベース名: ' . DB_NAME . '</p>';
    echo '<p>ユーザー名: ' . DB_USER . '</p>';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $startTime = microtime(true);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $endTime = microtime(true);
    
    echo '<p class="success">✓ データベース接続成功 (' . round(($endTime - $startTime) * 1000, 2) . 'ms)</p>';
    
    // テーブル一覧を取得
    echo '<h2>テーブル一覧</h2>';
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo '<p class="error">✗ テーブルが存在しません</p>';
    } else {
        echo '<p class="success">✓ ' . count($tables) . '個のテーブルが見つかりました</p>';
        echo '<ul>';
        foreach ($tables as $table) {
            echo '<li>' . htmlspecialchars($table) . '</li>';
        }
        echo '</ul>';
        
        // 各テーブルの構造を確認
        echo '<h2>テーブル構造</h2>';
        
        foreach ($tables as $table) {
            echo '<h3>' . htmlspecialchars($table) . '</h3>';
            
            // テーブル構造を取得
            $columns = $pdo->query("DESCRIBE `$table`")->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            
            foreach ($columns as $column) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                echo '<td>' . (is_null($column['Default']) ? 'NULL' : htmlspecialchars($column['Default'])) . '</td>';
                echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // レコード数を取得
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo '<p>レコード数: ' . $count . '件</p>';
            
            // 最初の5件のデータを表示
            if ($count > 0) {
                echo '<h4>サンプルデータ (最大5件)</h4>';
                $rows = $pdo->query("SELECT * FROM `$table` LIMIT 5")->fetchAll();
                
                if (!empty($rows)) {
                    echo '<table>';
                    
                    // ヘッダー行
                    echo '<tr>';
                    foreach (array_keys($rows[0]) as $key) {
                        echo '<th>' . htmlspecialchars($key) . '</th>';
                    }
                    echo '</tr>';
                    
                    // データ行
                    foreach ($rows as $row) {
                        echo '<tr>';
                        foreach ($row as $value) {
                            echo '<td>';
                            if (is_null($value)) {
                                echo 'NULL';
                            } else {
                                // 長すぎる値は省略
                                $str = (string)$value;
                                echo htmlspecialchars(strlen($str) > 100 ? substr($str, 0, 100) . '...' : $str);
                            }
                            echo '</td>';
                        }
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                }
            }
        }
    }
    
} catch (PDOException $e) {
    echo '<p class="error">✗ データベース接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</body></html>'; 