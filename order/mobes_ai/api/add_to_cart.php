<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: add_to_cart API エンドポイント雛形。
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/aicore/AiLogger.php';
require_once __DIR__ . '/../../../api/config/config.php';
require_once __DIR__ . '/../../../api/lib/Database.php';
require_once __DIR__ . '/../core/promptregistrer/PromptRegistrer.php';
require_once __DIR__ . '/middleware/RateLimiter.php';

use MobesAi\Core\AiCore\AiLogger;
use MobesAi\Core\PromptRegistrer\PromptRegistrer;
use MobesAi\Api\Middleware\RateLimiter;

$logger = new MobesAi\Core\AiCore\AiLogger();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['order_session_id'] ?? null;
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'order_session_id required']);
        exit;
    }
    if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'items array required']);
        exit;
    }

    $items = $input['items'];

    $db = \Database::getInstance();
    $pr = new PromptRegistrer();
    $useLock = $pr->isStockLockEnabled();

    $rl=new RateLimiter($sessionId,100);
    if(!$rl->check()){http_response_code(429);echo json_encode(['status'=>'error','message'=>'rate limit']);exit;}

    $db->beginTransaction();

    // 1. open order row
    $orderRow = $db->selectOne("SELECT id, total_amount FROM orders WHERE order_session_id = :sid AND order_status='OPEN' FOR UPDATE", [':sid' => $sessionId]);
    if (!$orderRow) {
        // room_number を order_sessions から取得
        $sessionInfo = $db->selectOne("SELECT room_number FROM order_sessions WHERE id=:sid AND is_active=1", [':sid' => $sessionId]);
        if (!$sessionInfo) {
            $db->rollback();
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'order_session not found']);
            exit;
        }
        $roomNumber = $sessionInfo['room_number'];
        $orderId = $db->insert('orders', [
            'order_session_id' => $sessionId,
            'room_number' => $roomNumber,
            'order_status' => 'OPEN',
            'total_amount' => 0
        ]);
        $currentTotal = 0;
    } else {
        $orderId = $orderRow['id'];
        $currentTotal = (float)$orderRow['total_amount'];
    }

    $addedCount = 0;

    // コンテキスト補足
    AiLogger::addContext(['order_session_id' => $sessionId]);

    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) {
            $db->rollback();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'invalid product_id or qty']);
            exit;
        }
        $product = $db->selectOne("SELECT id, name, price, stock_quantity, square_item_id FROM products WHERE id=:pid AND is_active=1 FOR UPDATE", [':pid' => $pid]);
        if (!$product) {
            $db->rollback();
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'product not found']);
            exit;
        }
        // stock lock
        if ($useLock) {
            if ((int)$product['stock_quantity'] < $qty) {
                $logger->warning('stock insufficient', ['pid' => $pid]);
                $db->rollback();
                http_response_code(409);
                echo json_encode(['status' => 'conflict', 'message' => 'stock insufficient', 'product_id' => $pid]);
                exit;
            }
            $db->execute("UPDATE products SET stock_quantity = stock_quantity - :q WHERE id=:pid", [':q' => $qty, ':pid' => $pid]);
        }
        $unitPrice = (float)$product['price'];
        $subtotal = $unitPrice * $qty;
        // insert order_details
        $db->insert('order_details', [
            'order_id' => $orderId,
            'order_session_id' => $sessionId,
            'square_item_id' => $product['square_item_id'],
            'product_name' => $product['name'],
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'subtotal' => $subtotal
        ]);
        $currentTotal += $subtotal;
        $addedCount += $qty;
    }

    // update order total
    $db->execute("UPDATE orders SET total_amount=:tot WHERE id=:oid", [':tot' => $currentTotal, ':oid' => $orderId]);

    $db->commit();

    echo json_encode(['status' => 'added', 'cart_count' => $addedCount, 'order_id' => $orderId]);
} catch (Throwable $e) {
    $logger->error('add_to_cart failed', ['exception' => $e]);
    if ($db ?? null) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'internal server error']);
} 