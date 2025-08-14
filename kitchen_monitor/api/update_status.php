<?php
/**
 * Update Order Status API for Kitchen Monitor
 * 
 * Updates the status of an order detail record
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    // Validate required fields
    $requiredFields = ['order_detail_id', 'new_status'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
            exit();
        }
    }

    // Validate CSRF token if provided
    if (isset($input['csrf_token'])) {
        if (!$kitchen->validateCSRFToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit();
        }
    }

    // Validate order_detail_id
    $orderDetailId = filter_var($input['order_detail_id'], FILTER_VALIDATE_INT);
    if (!$orderDetailId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid order detail ID']);
        exit();
    }

    // Validate new_status
    $allowedStatuses = ['ordered', 'ready', 'delivered', 'cancelled'];
    $newStatus = $input['new_status'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }

    // Get optional parameters
    $updatedBy = $input['updated_by'] ?? 'kitchen_monitor';
    $note = $input['note'] ?? '';
    $bulkUpdate = $input['bulk_update'] ?? false;

    // Get order information for webhook before update
    $orderInfo = null;
    if (!$bulkUpdate || !isset($input['order_session_id'])) {
        // Get order details for single update webhook
        try {
            $orderQuery = "SELECT od.id, od.product_name, od.status as current_status, o.room_number 
                          FROM order_details od 
                          JOIN orders o ON od.order_id = o.id 
                          WHERE od.id = ? LIMIT 1";
            $db = Database::getInstance();
            $orderInfo = $db->selectOne($orderQuery, [$orderDetailId]);
        } catch (Exception $e) {
            // Continue with update even if webhook prep fails
            error_log("Failed to get order info for webhook: " . $e->getMessage());
        }
    }

    // Handle bulk update if requested
    if ($bulkUpdate && isset($input['order_session_id'])) {
        // Update all items in the same session
        $response = $kitchen->bulkUpdateOrderStatus(
            $input['order_session_id'],
            $newStatus,
            $updatedBy,
            $note
        );
    } else {
        // Update single order
        $response = $kitchen->updateOrderStatus(
            $orderDetailId,
            $newStatus,
            $updatedBy,
            $note
        );
        
        // Send Discord webhook for single order update
        if ($response['success'] && $orderInfo) {
            try {
                $kitchen->sendDiscordWebhook(
                    $orderDetailId,
                    $orderInfo['current_status'] ?? 'ordered',
                    $newStatus,
                    $orderInfo['product_name'],
                    $orderInfo['room_number']
                );
            } catch (Exception $webhookError) {
                // Don't fail the update if webhook fails
                error_log("Discord webhook failed: " . $webhookError->getMessage());
            }
        }
    }

    // Return response
    if ($response['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
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