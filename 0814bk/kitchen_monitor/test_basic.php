<?php
/**
 * 基本的な動作テスト - HTTP 500エラーの原因特定
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Kitchen Monitor Basic Test</h1>";

// 1. 基本的なPHP動作確認
echo "<h2>1. PHP Basic Test</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

// 2. ファイル存在確認
echo "<h2>2. File Existence Check</h2>";
$mainConfigPath = __DIR__ . '/../../api/config/config.php';
echo "Main config exists: " . (file_exists($mainConfigPath) ? "✓" : "✗") . " ($mainConfigPath)<br>";

$databasePath = __DIR__ . '/../../api/lib/Database.php';
echo "Database.php exists: " . (file_exists($databasePath) ? "✓" : "✗") . " ($databasePath)<br>";

$loggerPath = __DIR__ . '/../../api/lib/Logger.php';
echo "Logger.php exists: " . (file_exists($loggerPath) ? "✓" : "✗") . " ($loggerPath)<br>";

// 3. 設定ファイル読み込みテスト
echo "<h2>3. Config Loading Test</h2>";
try {
    if (file_exists($mainConfigPath)) {
        require_once $mainConfigPath;
        echo "Main config loaded: ✓<br>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "<br>";
        echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "<br>";
    } else {
        echo "Main config not found<br>";
    }
} catch (Exception $e) {
    echo "Config error: " . $e->getMessage() . "<br>";
}

// 4. データベース接続テスト
echo "<h2>4. Database Connection Test</h2>";
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "Database connection: ✓<br>";
        
        // テーブル存在確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'order_details'");
        echo "order_details table exists: " . ($stmt->rowCount() > 0 ? "✓" : "✗") . "<br>";
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
        echo "orders table exists: " . ($stmt->rowCount() > 0 ? "✓" : "✗") . "<br>";
        
        // statusカラム確認
        $stmt = $pdo->query("DESCRIBE order_details");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "status column exists: " . (in_array('status', $columns) ? "✓" : "✗") . "<br>";
        
    } else {
        echo "Database constants not defined<br>";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// 5. クラス読み込みテスト
echo "<h2>5. Class Loading Test</h2>";
try {
    if (file_exists($databasePath)) {
        require_once $databasePath;
        echo "Database class loaded: ✓<br>";
    }
    
    if (file_exists($loggerPath)) {
        require_once $loggerPath;
        echo "Logger class loaded: ✓<br>";
    }
    
    // 修正版functions.phpを試す
    $functionsPath = __DIR__ . '/includes/functions_fixed.php';
    if (file_exists($functionsPath)) {
        require_once $functionsPath;
        echo "functions_fixed.php loaded: ✓<br>";
        
        if (class_exists('KitchenMonitorFunctions')) {
            echo "KitchenMonitorFunctions class available: ✓<br>";
            
            $kitchen = new KitchenMonitorFunctions();
            echo "KitchenMonitorFunctions instantiated: ✓<br>";
        }
    }
} catch (Exception $e) {
    echo "Class loading error: " . $e->getMessage() . "<br>";
    echo "Error trace: " . $e->getTraceAsString() . "<br>";
}

echo "<h2>6. Simple Kitchen Monitor Test</h2>";
try {
    if (isset($kitchen)) {
        // 簡単な動作テスト
        $orders = $kitchen->getActiveOrders(false);
        echo "getActiveOrders() success: ✓ (returned " . count($orders) . " orders)<br>";
        
        $stats = $kitchen->getKitchenStats();
        echo "getKitchenStats() success: ✓<br>";
        echo "Pending orders: " . ($stats['pending_orders'] ?? 'N/A') . "<br>";
    } else {
        echo "Kitchen instance not available<br>";
    }
} catch (Exception $e) {
    echo "Kitchen test error: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";
echo "If all tests pass, try using functions_fixed.php instead of functions.php";
?>