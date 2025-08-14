<?php
/**
 * Safe version test - Logger.phpを使わない版
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Kitchen Monitor Safe Test</h1>";

echo "<h2>1. Basic PHP Test</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

echo "<h2>2. Main Config Test</h2>";
try {
    $mainConfigPath = __DIR__ . '/../../api/config/config.php';
    if (file_exists($mainConfigPath)) {
        require_once $mainConfigPath;
        echo "Main config loaded: ✓<br>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "<br>";
        echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "<br>";
    } else {
        echo "Main config not found: $mainConfigPath<br>";
    }
} catch (Exception $e) {
    echo "Config error: " . $e->getMessage() . "<br>";
}

echo "<h2>3. Database Class Test</h2>";
try {
    $databasePath = __DIR__ . '/../../api/lib/Database.php';
    if (file_exists($databasePath)) {
        require_once $databasePath;
        echo "Database.php loaded: ✓<br>";
        
        if (defined('DB_HOST')) {
            $db = Database::getInstance();
            echo "Database instance created: ✓<br>";
        }
    } else {
        echo "Database.php not found: $databasePath<br>";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Safe Functions Test</h2>";
try {
    require_once __DIR__ . '/includes/functions_safe.php';
    echo "functions_safe.php loaded: ✓<br>";
    
    if (class_exists('KitchenMonitorFunctions')) {
        echo "KitchenMonitorFunctions class available: ✓<br>";
        
        $kitchen = new KitchenMonitorFunctions();
        echo "KitchenMonitorFunctions instantiated: ✓<br>";
        
        // Test methods
        $orders = $kitchen->getActiveOrders(false);
        echo "getActiveOrders() success: ✓ (returned " . count($orders) . " orders)<br>";
        
        $stats = $kitchen->getKitchenStats();
        echo "getKitchenStats() success: ✓<br>";
        echo "Pending orders: " . ($stats['pending_orders'] ?? 'N/A') . "<br>";
        
    } else {
        echo "KitchenMonitorFunctions class not found<br>";
    }
} catch (Exception $e) {
    echo "Safe functions error: " . $e->getMessage() . "<br>";
    echo "Error trace: " . $e->getTraceAsString() . "<br>";
}

echo "<h2>5. Sample Data Display</h2>";
if (isset($kitchen) && isset($orders)) {
    if (count($orders) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Product</th><th>Room</th><th>Status</th><th>Time</th></tr>";
        foreach (array_slice($orders, 0, 5) as $order) {
            echo "<tr>";
            echo "<td>" . $order['order_detail_id'] . "</td>";
            echo "<td>" . htmlspecialchars($order['product_name']) . "</td>";
            echo "<td>" . $order['room_number'] . "</td>";
            echo "<td>" . $order['status'] . "</td>";
            echo "<td>" . $order['time_ago'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No active orders found<br>";
    }
}

echo "<h2>Test Complete</h2>";
echo "If this test passes, you can use functions_safe.php instead of functions.php";
?>