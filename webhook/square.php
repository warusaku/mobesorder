<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/SquareService.php';
require_once __DIR__ . '/../lib/ProductService.php';
require_once __DIR__ . '/../lib/RoomTicketService.php';

// Webhookからのリクエストを処理
$requestBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? '';

// 署名を検証
$squareService = new SquareService();
$isValid = $squareService->validateWebhookSignature($signatureHeader, $requestBody);

if (!$isValid) {
    Utils::log("Invalid Square webhook signature", 'WARNING', 'SquareWebhook');
    http_response_code(401);
    exit;
}

// Webhookデータを解析
$data = json_decode($requestBody, true);

if (!$data || !isset($data['type'])) {
    Utils::log("Invalid webhook data", 'WARNING', 'SquareWebhook');
    http_response_code(400);
    exit;
}

// イベントタイプに基づいて処理
$eventType = $data['type'];
Utils::log("Received Square webhook: $eventType", 'INFO', 'SquareWebhook');

switch ($eventType) {
    case 'inventory.count.updated':
        handleInventoryUpdate($data);
        break;
        
    case 'catalog.version.updated':
        handleCatalogUpdate($data);
        break;
        
    case 'order.created':
        handleOrderCreated($data);
        break;
        
    case 'order.updated':
        handleOrderUpdated($data);
        break;
        
    default:
        // その他のイベントは無視
        break;
}

// 成功レスポンスを返す
http_response_code(200);
echo json_encode(['success' => true]);
exit;

/**
 * 在庫更新イベントを処理
 */
function handleInventoryUpdate($data) {
    try {
        $inventoryData = $data['data']['object']['inventory_count'] ?? null;
        
        if (!$inventoryData) {
            Utils::log("Missing inventory data in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        $catalogObjectId = $inventoryData['catalog_object_id'] ?? '';
        $quantity = $inventoryData['quantity'] ?? 0;
        
        if (empty($catalogObjectId)) {
            Utils::log("Missing catalog object ID in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        // 在庫を更新
        $productService = new ProductService();
        $result = $productService->updateStock($catalogObjectId, $quantity);
        
        if ($result) {
            Utils::log("Updated inventory for item $catalogObjectId to $quantity", 'INFO', 'SquareWebhook');
        } else {
            Utils::log("Failed to update inventory for item $catalogObjectId", 'WARNING', 'SquareWebhook');
        }
    } catch (Exception $e) {
        Utils::log("Error processing inventory update: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * カタログ更新イベントを処理
 */
function handleCatalogUpdate($data) {
    try {
        // カタログ全体を同期
        $productService = new ProductService();
        $result = $productService->syncProductsFromSquare();
        
        Utils::log("Catalog sync result: " . json_encode($result), 'INFO', 'SquareWebhook');
    } catch (Exception $e) {
        Utils::log("Error processing catalog update: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * 注文作成イベントを処理
 */
function handleOrderCreated($data) {
    try {
        $orderData = $data['data']['object']['order'] ?? null;
        
        if (!$orderData) {
            Utils::log("Missing order data in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        $orderId = $orderData['id'] ?? '';
        $metadata = $orderData['metadata'] ?? [];
        
        if (empty($orderId)) {
            Utils::log("Missing order ID in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        // 部屋番号付きの注文のみ処理
        if (!isset($metadata['room_number']) || empty($metadata['room_number'])) {
            Utils::log("Order $orderId is not associated with a room", 'INFO', 'SquareWebhook');
            return;
        }
        
        $roomNumber = $metadata['room_number'];
        $isRoomTicket = isset($metadata['is_room_ticket']) && $metadata['is_room_ticket'] === 'true';
        
        // room_ticketsテーブルを確認
        $db = Database::getInstance();
        $query = "SELECT * FROM room_tickets WHERE square_order_id = ?";
        $existingTicket = $db->fetchOne($query, [$orderId]);
        
        // データベースに存在しない場合は追加
        if (!$existingTicket) {
            $query = "INSERT INTO room_tickets (room_number, square_order_id, status) VALUES (?, ?, ?)";
            $db->execute($query, [
                $roomNumber,
                $orderId,
                $orderData['state'] ?? 'OPEN'
            ]);
            
            Utils::log("Added new room ticket for room $roomNumber from webhook (Order ID: $orderId)", 'INFO', 'SquareWebhook');
        }
    } catch (Exception $e) {
        Utils::log("Error processing order created: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * 注文更新イベントを処理
 */
function handleOrderUpdated($data) {
    try {
        $orderData = $data['data']['object']['order'] ?? null;
        
        if (!$orderData) {
            Utils::log("Missing order data in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        $orderId = $orderData['id'] ?? '';
        $state = $orderData['state'] ?? '';
        
        if (empty($orderId)) {
            Utils::log("Missing order ID in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        // room_ticketsテーブルを更新
        $db = Database::getInstance();
        $query = "UPDATE room_tickets SET status = ? WHERE square_order_id = ?";
        $result = $db->execute($query, [$state, $orderId]);
        
        if ($result) {
            Utils::log("Updated room ticket status for order $orderId to $state", 'INFO', 'SquareWebhook');
        } else {
            // データベースにない場合は、注文作成イベントと同じ処理を実行
            $metadata = $orderData['metadata'] ?? [];
            
            if (isset($metadata['room_number']) && !empty($metadata['room_number'])) {
                $roomNumber = $metadata['room_number'];
                
                $query = "INSERT INTO room_tickets (room_number, square_order_id, status) VALUES (?, ?, ?)";
                $db->execute($query, [
                    $roomNumber,
                    $orderId,
                    $state
                ]);
                
                Utils::log("Added new room ticket for room $roomNumber from update webhook (Order ID: $orderId)", 'INFO', 'SquareWebhook');
            }
        }
    } catch (Exception $e) {
        Utils::log("Error processing order updated: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
} 