<?php
/**
 * Get Orders API for Kitchen Monitor
 * 
 * Returns active orders with filtering and pagination options
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/functions.php';

try {
    $kitchen = new KitchenMonitorFunctions();
    
    // Authenticate access
    if (!$kitchen->authenticateKitchenAccess()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }

    // Get parameters
    $showCompleted = isset($_GET['show_completed']) ? filter_var($_GET['show_completed'], FILTER_VALIDATE_BOOLEAN) : false;
    $lastUpdate = $_GET['last_update'] ?? null;

    // Get orders
    $orders = $kitchen->getActiveOrders($showCompleted);
    
    // Get statistics
    $stats = $kitchen->getKitchenStats();
    
    // Check for new orders if last_update is provided
    $newOrdersCount = 0;
    if ($lastUpdate) {
        $newOrdersCount = $kitchen->getNewOrders($lastUpdate);
    }

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'orders' => $orders,
            'stats' => [
                'total_pending' => (int)$stats['pending_orders'],
                'total_ready' => (int)$stats['ready_orders'],
                'total_delivered_today' => (int)$stats['delivered_today'],
                'total_cancelled_today' => (int)$stats['cancelled_today'],
                'avg_completion_time' => $stats['avg_completion_time'],
                'busiest_room' => $stats['busiest_room'],
                'last_order_time' => $stats['last_order_time']
            ],
            'new_orders_count' => $newOrdersCount,
            'last_update' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}