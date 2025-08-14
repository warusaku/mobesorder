<?php
/**
 * ロリポップサーバー対応チェック
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>ロリポップサーバー対応チェック</h1>";

echo "<h2>1. PHP環境確認</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "<br>";

echo "<h2>2. PHP設定確認</h2>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "<br>";
echo "allow_url_include: " . (ini_get('allow_url_include') ? 'ON' : 'OFF') . "<br>";

echo "<h2>3. 必要な拡張モジュール確認</h2>";
$extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring', 'curl'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "✓ 利用可能" : "✗ 利用不可") . "<br>";
}

echo "<h2>4. ファイルパス確認</h2>";
$currentDir = __DIR__;
echo "Current Directory: $currentDir<br>";

$paths = [
    'Main Config' => $currentDir . '/../../api/config/config.php',
    'Database Class' => $currentDir . '/../../api/lib/Database.php',
    'Kitchen Config' => $currentDir . '/includes/config.php',
    'Kitchen Functions' => $currentDir . '/includes/functions.php'
];

foreach ($paths as $name => $path) {
    echo "$name: " . (file_exists($path) ? "✓ 存在" : "✗ 不存在") . " ($path)<br>";
}

echo "<h2>5. 権限確認</h2>";
echo "Current directory writable: " . (is_writable($currentDir) ? "✓" : "✗") . "<br>";
echo "Includes directory readable: " . (is_readable($currentDir . '/includes') ? "✓" : "✗") . "<br>";

echo "<h2>6. 設定ファイル読み込みテスト</h2>";
try {
    $mainConfigPath = $currentDir . '/../../api/config/config.php';
    if (file_exists($mainConfigPath)) {
        // ロリポップでは相対パスの問題が発生しやすい
        $oldCwd = getcwd();
        chdir(dirname($mainConfigPath));
        
        require_once $mainConfigPath;
        echo "Main config loaded: ✓<br>";
        
        // 定数確認
        $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($constants as $const) {
            echo "$const: " . (defined($const) ? "✓ 定義済み" : "✗ 未定義") . "<br>";
        }
        
        chdir($oldCwd);
    } else {
        echo "Main config not found<br>";
    }
} catch (Exception $e) {
    echo "Config loading error: " . $e->getMessage() . "<br>";
}

echo "<h2>7. MySQL接続テスト（ロリポップ用）</h2>";
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        // ロリポップのMySQLは特殊な設定が必要な場合がある
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        echo "Attempting connection to: " . DB_HOST . "/" . DB_NAME . "<br>";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        echo "MySQL connection: ✓ 成功<br>";
        
        // テーブル存在確認
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Available tables: " . implode(', ', $tables) . "<br>";
        
        // order_details確認
        if (in_array('order_details', $tables)) {
            echo "order_details table: ✓ 存在<br>";
            
            $stmt = $pdo->query("DESCRIBE order_details");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "status column: " . (in_array('status', $columns) ? "✓ 存在" : "✗ 不存在") . "<br>";
            
            // レコード数確認
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_details");
            $result = $stmt->fetch();
            echo "Total records: " . $result['count'] . "<br>";
        } else {
            echo "order_details table: ✗ 不存在<br>";
        }
        
        // orders確認
        if (in_array('orders', $tables)) {
            echo "orders table: ✓ 存在<br>";
        } else {
            echo "orders table: ✗ 不存在<br>";
        }
        
    } else {
        echo "Database constants not defined<br>";
    }
} catch (Exception $e) {
    echo "MySQL connection error: " . $e->getMessage() . "<br>";
    echo "Error code: " . $e->getCode() . "<br>";
}

echo "<h2>8. ロリポップ特有の制限確認</h2>";

// セッション確認
echo "Session support: " . (function_exists('session_start') ? "✓" : "✗") . "<br>";

// 出力バッファリング確認
echo "Output buffering: " . (ob_get_level() > 0 ? "有効 (Level: " . ob_get_level() . ")" : "無効") . "<br>";

// タイムゾーン確認
echo "Default timezone: " . date_default_timezone_get() . "<br>";

// エラーログ確認
echo "Error log location: " . ini_get('error_log') . "<br>";

echo "<h2>9. 簡易動作テスト</h2>";

// 基本的なPHP機能テスト
echo "JSON encode/decode: ";
$testData = ['test' => 'data'];
$json = json_encode($testData);
$decoded = json_decode($json, true);
echo ($decoded['test'] === 'data' ? "✓" : "✗") . "<br>";

// 日付機能テスト
echo "Date functions: ";
$date = date('Y-m-d H:i:s');
echo "✓ ($date)<br>";

// ファイル操作テスト
echo "File operations: ";
$testFile = __DIR__ . '/test_write.txt';
if (file_put_contents($testFile, 'test') !== false) {
    if (file_get_contents($testFile) === 'test') {
        unlink($testFile);
        echo "✓<br>";
    } else {
        echo "✗ (Read failed)<br>";
    }
} else {
    echo "✗ (Write failed)<br>";
}

echo "<h2>10. 推奨される修正</h2>";
echo "<ul>";
echo "<li>ロリポップでは相対パスに問題がある場合があります</li>";
echo "<li>PHPのバージョンが古い場合があります（PHP 8.0+ 推奨）</li>";
echo "<li>メモリ制限が厳しい場合があります</li>";
echo "<li>実行時間制限が厳しい場合があります</li>";
echo "<li>ファイル権限の問題がある場合があります</li>";
echo "</ul>";

echo "<h2>チェック完了</h2>";
echo "このページの結果をもとに問題を特定してください。";
?>