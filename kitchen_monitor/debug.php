<?php
/**
 * Kitchen Monitor Debug Script
 * HTTP 500エラーの原因を調査するためのデバッグスクリプト
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Kitchen Monitor Debug</h1>";

echo "<h2>1. PHP Version Check</h2>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>2. File Existence Check</h2>";
$files = [
    'includes/config.php',
    'includes/functions.php',
    'api/get_orders.php',
    'css/monitor.css',
    'js/monitor.js'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "$file: " . (file_exists($path) ? "✓ EXISTS" : "✗ MISSING") . "<br>";
}

echo "<h2>3. Database Connection Test</h2>";
try {
    $configPath = __DIR__ . '/../../api/config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        echo "Main config loaded: ✓<br>";
        
        // Test database connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        echo "Database connection: ✓<br>";
        
        // Check if status column exists
        $stmt = $pdo->query("DESCRIBE order_details");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Status column exists: " . (in_array('status', $columns) ? "✓" : "✗") . "<br>";
        
    } else {
        echo "Main config file not found: $configPath<br>";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Class Loading Test</h2>";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "functions.php loaded: ✓<br>";
    
    if (class_exists('KitchenMonitorFunctions')) {
        echo "KitchenMonitorFunctions class: ✓<br>";
        
        $kitchen = new KitchenMonitorFunctions();
        echo "Class instantiation: ✓<br>";
    } else {
        echo "KitchenMonitorFunctions class: ✗<br>";
    }
} catch (Exception $e) {
    echo "Class loading error: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Include Path Test</h2>";
$includePath = __DIR__ . '/includes/config.php';
echo "Config path: $includePath<br>";
echo "Config exists: " . (file_exists($includePath) ? "✓" : "✗") . "<br>";

if (file_exists($includePath)) {
    try {
        $config = include $includePath;
        echo "Config loaded: ✓<br>";
        echo "Config type: " . gettype($config) . "<br>";
    } catch (Exception $e) {
        echo "Config load error: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>6. Memory and Error Check</h2>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";
echo "Error log: " . ini_get('error_log') . "<br>";

echo "<h2>7. Required Extensions</h2>";
$extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "✓" : "✗") . "<br>";
}

echo "<h2>8. Directory Permissions</h2>";
echo "Kitchen monitor dir writable: " . (is_writable(__DIR__) ? "✓" : "✗") . "<br>";
echo "Includes dir readable: " . (is_readable(__DIR__ . '/includes') ? "✓" : "✗") . "<br>";

echo "<h2>9. Last PHP Errors</h2>";
if (function_exists('error_get_last')) {
    $lastError = error_get_last();
    if ($lastError) {
        echo "Last error: " . $lastError['message'] . " in " . $lastError['file'] . " on line " . $lastError['line'] . "<br>";
    } else {
        echo "No recent errors<br>";
    }
}

echo "<h2>Debug Complete</h2>";
echo "If you still see HTTP 500, check your web server error logs for more details.";
?>