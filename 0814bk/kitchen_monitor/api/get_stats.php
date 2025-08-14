<?php
/**
 * Get Statistics API for Kitchen Monitor
 * 
 * Returns kitchen performance statistics and metrics
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

    // Get basic statistics
    $stats = $kitchen->getKitchenStats();

    // Prepare detailed response
    $response = [
        'success' => true,
        'data' => [
            'pending_orders' => (int)$stats['pending_orders'],
            'ready_orders' => (int)$stats['ready_orders'],
            'completed_today' => (int)$stats['delivered_today'],
            'cancelled_today' => (int)$stats['cancelled_today'],
            'average_prep_time' => $stats['avg_completion_time'],
            'busiest_room' => $stats['busiest_room'],
            'last_order_time' => $stats['last_order_time'],
            'total_active' => (int)$stats['pending_orders'] + (int)$stats['ready_orders'],
            'completion_rate' => 0,
            'performance_score' => 'good'
        ]
    ];

    // Calculate completion rate
    $totalOrders = (int)$stats['pending_orders'] + (int)$stats['ready_orders'] + (int)$stats['delivered_today'];
    if ($totalOrders > 0) {
        $response['data']['completion_rate'] = round(((int)$stats['delivered_today'] / $totalOrders) * 100, 1);
    }

    // Determine performance score based on metrics
    $avgTime = $stats['avg_completion_time'];
    $pendingCount = (int)$stats['pending_orders'];
    
    if ($avgTime <= 15 && $pendingCount <= 5) {
        $response['data']['performance_score'] = 'excellent';
    } elseif ($avgTime <= 25 && $pendingCount <= 10) {
        $response['data']['performance_score'] = 'good';
    } elseif ($avgTime <= 35 && $pendingCount <= 15) {
        $response['data']['performance_score'] = 'fair';
    } else {
        $response['data']['performance_score'] = 'needs_attention';
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}