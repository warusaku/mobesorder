<?php
/**
 * データベース接続テスト
 * このスクリプトは、データベース接続が正常に行われるかをテストします
 */

// 設定ファイルの読み込み
require_once __DIR__ . '/../../api/config/config.php';

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');

// 結果格納用の配列
$result = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

/**
 * PDOを使用したデータベース接続テスト
 */
function testPDOConnection() {
    global $result;
    
    try {
        // 設定値の表示（パスワードは伏せる）
        $test = [
            'title' => 'PDO接続テスト',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER,
            'password' => '********',
            'success' => false,
            'error' => null,
            'tables' => []
        ];
        
        // PDO接続文字列
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
        
        // 接続オプション
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // 接続試行
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $test['success'] = true;
        
        // テーブル一覧の取得
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $test['tables'] = $tables;
        
        // line_room_linksテーブルの構造確認
        if (in_array('line_room_links', $tables)) {
            $stmt = $pdo->query('DESCRIBE line_room_links');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $test['line_room_links_structure'] = $columns;
            
            // テーブル内のデータ数を確認
            $stmt = $pdo->query('SELECT COUNT(*) FROM line_room_links');
            $count = $stmt->fetchColumn();
            $test['line_room_links_count'] = $count;
            
            // 直近のレコードを1件取得
            $stmt = $pdo->query('SELECT * FROM line_room_links ORDER BY id DESC LIMIT 1');
            $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($lastRecord) {
                // パスワードなど機密情報を隠す
                if (isset($lastRecord['password'])) {
                    $lastRecord['password'] = '********';
                }
                $test['line_room_links_last_record'] = $lastRecord;
            }
            
            // カラムの詳細情報
            $columnDetails = [];
            foreach ($columns as $column) {
                $columnDetails[$column['Field']] = [
                    'type' => $column['Type'],
                    'null' => $column['Null'],
                    'key' => $column['Key'],
                    'default' => $column['Default'],
                    'extra' => $column['Extra']
                ];
            }
            $test['line_room_links_columns'] = $columnDetails;
        }
        
        // roomdatasettingsテーブルの構造確認
        if (in_array('roomdatasettings', $tables)) {
            $stmt = $pdo->query('DESCRIBE roomdatasettings');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $test['roomdatasettings_structure'] = $columns;
            
            // テーブル内のデータ数を確認
            $stmt = $pdo->query('SELECT COUNT(*) FROM roomdatasettings');
            $count = $stmt->fetchColumn();
            $test['roomdatasettings_count'] = $count;
            
            // ランダムに数件取得
            $stmt = $pdo->query('SELECT * FROM roomdatasettings LIMIT 5');
            $roomSamples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $test['roomdatasettings_samples'] = $roomSamples;
        }
        
    } catch (PDOException $e) {
        $test['success'] = false;
        $test['error'] = $e->getMessage();
    }
    
    $result['tests'][] = $test;
    
    return $test['success'];
}

/**
 * テスト実行
 */
try {
    // PDO接続テスト
    $pdoSuccess = testPDOConnection();
    
    // 全体の成功判定
    $result['success'] = $pdoSuccess;
    
    // 結果を返す
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT);
} 