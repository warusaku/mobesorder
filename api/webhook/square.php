<?php
// --- Force log destination before any library is loaded ---
if(!defined('LOG_LEVEL')) define('LOG_LEVEL','DEBUG');
if(!defined('LOG_FILE'))  define('LOG_FILE', __DIR__.'/../../logs/SquareWebhook.log');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/SquareService.php';
require_once __DIR__ . '/../lib/ProductService.php';
require_once __DIR__ . '/../lib/RoomTicketService.php';

// Webhookからのリクエストを処理
$requestBody = file_get_contents('php://input');
$rawHeaders  = function_exists('getallheaders') ? getallheaders() : $_SERVER;
writeSquareWebhookLog($rawHeaders, $requestBody);

$signatureHeader = $_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? '';

// 署名検証開始ログ
$sigTypes = [];
if(isset($_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'])) $sigTypes[]='HMAC256';
if(isset($_SERVER['HTTP_X_SQUARE_SIGNATURE']))            $sigTypes[]='SHA1';
Utils::log('署名検証開始 headerTypes='.implode('|',$sigTypes).' key='.substr(SQUARE_WEBHOOK_SIGNATURE_KEY,0,6).'...',
          'INFO','SquareWebhook');

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
Utils::log("Webhook dispatch event={$eventType}", 'INFO', 'SquareWebhook');
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
        
    case 'payment.created':
        handlePaymentCreated($data);
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
    Utils::log('enter handleInventoryUpdate','INFO','SquareWebhook');
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

        // ログ保存
        saveWebhookPayload('inventory.count.updated',null,$data);
    } catch (Exception $e) {
        Utils::log("Error processing inventory update: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * カタログ更新イベントを処理
 */
function handleCatalogUpdate($data) {
    Utils::log('enter handleCatalogUpdate','INFO','SquareWebhook');
    // --- レートリミット緩和ウェイト (最低3秒間隔) ---
    static $lastCatalogSyncTs = 0.0;
    $now = microtime(true);
    $elapsed = $now - $lastCatalogSyncTs;
    if($elapsed < 3.0){
        $sleepMicro = (int)((3.0 - $elapsed) * 1_000_000);
        Utils::log('catalog sync sleep '.round($sleepMicro/1000).'ms to avoid rate-limit','INFO','SquareWebhook');
        usleep($sleepMicro);
    }
    $lastCatalogSyncTs = microtime(true);

    try {
        // カタログ全体を同期
        $productService = new ProductService();
        $result = $productService->syncProductsFromSquare();
        
        Utils::log("Catalog sync result: " . json_encode($result), 'INFO', 'SquareWebhook');

        // ログ保存
        saveWebhookPayload('catalog.version.updated',null,$data);
    } catch (Exception $e) {
        Utils::log("Error processing catalog update: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * 注文作成イベントを処理
 */
function handleOrderCreated($data) {
    Utils::log('enter handleOrderCreated','INFO','SquareWebhook');
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
        
        // --- room_number / order_session_id 補完 ----------------------
        $sessionIdMeta = $metadata['order_session_id'] ?? null;
        $roomNumber    = $metadata['room_number']     ?? null;

        // line_items 先頭の catalog_object_id を取得（無い場合は空）
        $squareItemId = '';
        if(isset($orderData['line_items'][0]['catalog_object_id'])){
            $squareItemId = $orderData['line_items'][0]['catalog_object_id'];
        }

        // room_number または sessionId が欠けている場合は order_sessions から逆引き
        if(!$roomNumber || !$sessionIdMeta){
            try{
                $db = Database::getInstance();
                // square_item_id で検索
                if($squareItemId){
                    $row = $db->selectOne("SELECT id AS session_id, room_number FROM order_sessions WHERE square_item_id = ? LIMIT 1",[$squareItemId]);
                    if($row){
                        if(!$sessionIdMeta) $sessionIdMeta = $row['session_id'];
                        if(!$roomNumber)     $roomNumber   = $row['room_number'];
                    }
                }
                // session_id で検索
                if(!$roomNumber && $sessionIdMeta){
                    $row2 = $db->selectOne("SELECT room_number FROM order_sessions WHERE id = ? LIMIT 1",[$sessionIdMeta]);
                    if($row2){ $roomNumber = $row2['room_number']; }
                }
            }catch(Exception $lookupEx){
                Utils::log('reverse lookup error: '.$lookupEx->getMessage(),'WARNING','SquareWebhook');
            }
        }

        if(!$roomNumber){
            Utils::log("Order $orderId: room_number could not be resolved", 'WARNING', 'SquareWebhook');
        }

        $isRoomTicket = isset($metadata['is_room_ticket']) && $metadata['is_room_ticket'] === 'true';

        // === 取引ログ保存 (square_transactions) ===
        saveWebhookPayload('order.created',$orderData,$orderData);

        // === order_sessions 更新 (square_order_id 設定) ===
        if($sessionIdMeta && preg_match('/^\d{21}$/',$sessionIdMeta)){
            $db = Database::getInstance();
            try{
                $db->execute("UPDATE order_sessions SET square_order_id = ? WHERE id = ?",[$orderId,$sessionIdMeta]);
            }catch(Exception $upEx){
                Utils::log('update order_sessions.square_order_id error: '.$upEx->getMessage(),'ERROR','SquareWebhook');
            }
        }

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

        /* --------------------------------------------------------
         * 会計用商品の order.created 到着時点で
         *   → セッションを自動クローズ (決済完了扱い)
         * -----------------------------------------------------*/
        try {
            if (!empty($squareItemId)) {
                $db = Database::getInstance();
                $row = $db->selectOne(
                    "SELECT id, square_item_id FROM order_sessions WHERE square_item_id = ? AND is_active = 1 LIMIT 1",
                    [$squareItemId]
                );
                if ($row) {
                    $sessionId = $row['id'];

                    // DB 更新: セッション / orders / line_room_links
                    $db->execute("UPDATE order_sessions SET is_active = 0, session_status = 'Completed', closed_at = NOW(), square_order_id = ? WHERE id = ?", [$orderId, $sessionId]);
                    $db->execute("UPDATE orders SET order_status = 'COMPLETED' WHERE order_session_id = ? AND order_status = 'OPEN'", [$sessionId]);
                    $db->execute("UPDATE line_room_links SET is_active = 0 WHERE order_session_id = ?", [$sessionId]);

                    // Square 商品を非公開化
                    require_once __DIR__ . '/../lib/SquareService.php';
                    $svc = new SquareService();
                    $svc->disableSessionProduct($row['square_item_id']);

                    // クローズ通知 Webhook
                    require_once __DIR__ . '/../lib/OrderWebhook.php';
                    $ow = new OrderWebhook();
                    $ow->sendSessionCloseWebhook($sessionId, 'Completed');

                    Utils::log("Session closed via order.created webhook: {$sessionId}", 'INFO', 'SquareWebhook');
                }
            }
        } catch (Exception $ocEx) {
            Utils::log('Session close in order.created error: ' . $ocEx->getMessage(), 'ERROR', 'SquareWebhook');
        }
    } catch (Exception $e) {
        Utils::log("Error processing order created: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}

/**
 * 注文更新イベントを処理
 */
function handleOrderUpdated($data) {
    Utils::log('enter handleOrderUpdated','INFO','SquareWebhook');
    try {
        // Square 2024-xx API では object 内に {order_updated:{...}} が入る場合がある
        $orderData = $data['data']['object']['order'] ?? null;
        if(!$orderData && isset($data['data']['object'])){
            // object の最初の要素が order_updated かどうか確認
            foreach($data['data']['object'] as $k=>$v){
                if(strpos($k,'order_')===0 && is_array($v)){
                    $orderData = $v;
                    break;
                }
            }
        }
        
        if (!$orderData) {
            Utils::log("Missing order data in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        // Square 2024‑04 以降の Webhook では id フィールドが order_id / orderId に変わる場合がある
        $orderId = $orderData['id']
                 ?? $orderData['order_id']
                 ?? $orderData['orderId']
                 ?? '';
        // state もフィールド名が揺れるケースに備えてフォールバック
        $state = $orderData['state']
               ?? $orderData['order_state']
               ?? $orderData['orderState']
               ?? '';
        
        if (empty($orderId)) {
            Utils::log("Missing order ID in webhook", 'WARNING', 'SquareWebhook');
            return;
        }
        
        // DB インスタンスを取得
        $db = Database::getInstance();

        // === Webhook ログ保存 (square_webhooks) ===
        saveWebhookPayload('order.updated',$orderData,$orderData);

        // === state COMPLETED 時は支払い完了として square_transactions へ保存 ===
        if(strtoupper($state)==='COMPLETED'){
            try{
                // フルオーダーデータ取得
                require_once __DIR__.'/../lib/SquareService.php';
                $svc = new SquareService();
                $orderResp = $svc->getSquareClient()->getOrdersApi()->retrieveOrder($orderId);
                if($orderResp->isSuccess()){
                    $orderObj = $orderResp->getResult()->getOrder();
                    $orderArr = json_decode(json_encode($orderObj), true);
                    // square_transactions へ保存
                    saveWebhookPayload('payment.completed', $orderArr, $orderArr);
                }else{
                    Utils::log('retrieveOrder failed in COMPLETED hook: '.json_encode($orderResp->getErrors()),'WARNING','SquareWebhook');
                }
            }catch(Exception $pcEx){
                Utils::log('payment.completed processing error: '.$pcEx->getMessage(),'ERROR','SquareWebhook');
            }
        }

        // === セッション完了処理 (products-type のみ) ===
        if(strtoupper($state)==='COMPLETED'){
            try{
                // line_items -> catalog_object_id
                $lineItems = $orderData['line_items'] ?? [];
                $catalogIds = array_column($lineItems,'catalog_object_id');
                if($catalogIds){
                    // order_sessions 取得
                    $inClause = implode(',', array_fill(0,count($catalogIds),'?'));
                    $sql = "SELECT id, square_item_id FROM order_sessions WHERE square_item_id IN ($inClause) AND is_active=1 LIMIT 1";
                    $row = $db->selectOne($sql,$catalogIds);
                    if($row){
                        $sessionId = $row['id'];
                        // 更新
                        $db->execute("UPDATE order_sessions SET is_active=0, session_status='Completed', closed_at=NOW() WHERE id = ?",[$sessionId]);
                        $db->execute("UPDATE orders SET order_status='COMPLETED' WHERE order_session_id = ? AND order_status='OPEN'",[$sessionId]);
                        $db->execute("UPDATE line_room_links SET is_active=0 WHERE order_session_id = ?",[$sessionId]);

                        // Square 商品を非公開
                        require_once __DIR__.'/../lib/SquareService.php';
                        $svc = new SquareService();
                        $svc->disableSessionProduct($row['square_item_id']);

                        Utils::log("Session closed via webhook: {$sessionId}", 'INFO', 'SquareWebhook');
                    }
                }
            }catch(Exception $sEx){
                Utils::log('Session close in webhook error: '.$sEx->getMessage(),'ERROR','SquareWebhook');
            }
        }

        // === room_ticketsを更新 (従来処理) ===
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

/**
 * 支払い作成イベントを処理 (payment.created)
 */
function handlePaymentCreated($data) {
    Utils::log('enter handlePaymentCreated','INFO','SquareWebhook');
    try {
        $paymentData = $data['data']['object']['payment'] ?? null;
        if(!$paymentData){
            Utils::log('payment data missing in payment.created','WARNING','SquareWebhook');
            return;
        }
        $orderId = $paymentData['order_id'] ?? $paymentData['orderId'] ?? '';
        if(!$orderId){
            Utils::log('order_id missing in payment.created','WARNING','SquareWebhook');
        }

        // Square API でフルオーダーデータを取得（メタデータ確保 & セッション紐付け判定）
        $fullOrderArr = null;
        try{
            require_once __DIR__.'/../lib/SquareService.php';
            $svc = new SquareService();
            $resp = $svc->getSquareClient()->getOrdersApi()->retrieveOrder($orderId, true);
            if($resp->isSuccess()){
                $fullOrderArr = json_decode(json_encode($resp->getResult()->getOrder()), true);
            }else{
                Utils::log('retrieveOrder in payment.created failed: '.json_encode($resp->getErrors()),'WARNING','SquareWebhook');
            }
        }catch(Exception $rex){
            Utils::log('retrieveOrder exception: '.$rex->getMessage(),'ERROR','SquareWebhook');
        }

        // 取引ログ保存 (square_transactions)
        saveWebhookPayload('payment.created', $fullOrderArr ?? $paymentData, $paymentData);

        // === セッション自動クローズ (catalog-type) ===
        if ($fullOrderArr) {
            $sessionId = resolveSessionIdFromSquareOrder($fullOrderArr);

            if ($sessionId) {
                $db = Database::getInstance();
                $sessionRow = $db->selectOne(
                    "SELECT id, square_item_id FROM order_sessions WHERE id = ? AND is_active = 1",
                    [$sessionId]
                );

                if ($sessionRow) {
                    try {
                        // DB 更新
                        $db->execute("UPDATE order_sessions SET is_active=0, session_status='Completed', closed_at=NOW() WHERE id = ?", [$sessionId]);
                        $db->execute("UPDATE orders SET order_status='COMPLETED' WHERE order_session_id = ? AND order_status='OPEN'", [$sessionId]);
                        $db->execute("UPDATE line_room_links SET is_active=0 WHERE order_session_id = ?", [$sessionId]);

                        // Square 商品を非公開
                        if (!empty($sessionRow['square_item_id'])) {
                            $svc->disableSessionProduct($sessionRow['square_item_id']);
                        }

                        // Webhook 通知
                        require_once __DIR__ . '/../lib/OrderWebhook.php';
                        $ow = new OrderWebhook();
                        $ow->sendSessionCloseWebhook($sessionId, 'Completed');

                        Utils::log("Session closed via payment.created webhook: {$sessionId}", 'INFO', 'SquareWebhook');
                    } catch (Exception $clEx) {
                        Utils::log('Session close in payment.created error: ' . $clEx->getMessage(), 'ERROR', 'SquareWebhook');
                    }
                } else {
                    Utils::log('Session id resolved but not active: ' . $sessionId, 'INFO', 'SquareWebhook');
                }
            } else {
                Utils::log('Unable to resolve session for payment.created; manual check required', 'WARNING', 'SquareWebhook');
            }
        }

    }catch(Exception $e){
        Utils::log('Error processing payment.created: '.$e->getMessage(),'ERROR','SquareWebhook');
    }
}

/* --------------------------------------------------------
 * 共通ヘルパー: webhook ペイロード保存
 *   - order.created            → square_transactions
 *   - それ以外のイベント      → square_webhooks
 * -----------------------------------------------------*/
function saveWebhookPayload($eventType,$orderData=null,$rawPayload=[]){
    try{
        Utils::log("saveWebhookPayload start event={$eventType}", 'INFO', 'SquareWebhook');
        $db = Database::getInstance();
        if($eventType==='order.created' || $eventType==='payment.completed' || $eventType==='payment.created'){
            // === square_transactions ===
            // メタデータ抽出（キー表記ゆれに対応）
            $meta = $orderData['metadata'] ?? ($orderData['metadata'] ?? []);
            $orderSessionId = $meta['order_session_id'] ?? $meta['orderSessionId'] ?? null;
            $roomNumber     = $meta['room_number']     ?? $meta['roomNumber']     ?? null;

            // line_items キー表記ゆれ対応
            $lineItems = $orderData['line_items'] ?? $orderData['lineItems'] ?? [];
            $firstLine = $lineItems[0] ?? [];
            $catalogId = $firstLine['catalog_object_id'] ?? $firstLine['catalogObjectId'] ?? '';

            // tender.payment_id 取得（tenders または tendersCamel）
            $tenders = $orderData['tenders'] ?? $orderData['tenders'] ?? [];
            $paymentId = $tenders[0]['payment_id'] ?? $tenders[0]['paymentId'] ?? ($orderData['id'] ?? uniqid('tx_'));

            // total_money 取得
            $totalMoney = $orderData['total_money'] ?? $orderData['totalMoney'] ?? [];
            $amountVal  = $totalMoney['amount'] ?? 0;
            $currency   = $totalMoney['currency'] ?? 'JPY';

            $insertData = [
                'square_transaction_id'=> $paymentId,
                'square_order_id'      => $orderData['id'] ?? '',
                'square_item_id'       => $catalogId,
                'location_id'          => $orderData['location_id'] ?? $orderData['locationId'] ?? '',
                'amount'               => $amountVal,
                'currency'             => $currency,
                'order_session_id'     => $orderSessionId,
                'room_number'          => $roomNumber,
                'payload'              => json_encode($rawPayload, JSON_UNESCAPED_UNICODE)
            ];
            Utils::log('insert square_transactions: '.json_encode($insertData, JSON_UNESCAPED_UNICODE),'INFO','SquareWebhook');
            $lastId = $db->insert('square_transactions',$insertData);
            Utils::log("square_transactions inserted id={$lastId}",'INFO','SquareWebhook');
        }else{
            // === square_webhooks ===
            $insertData = [
                'event_type'      => $eventType,
                'square_order_id' => $orderData['id'] ?? null,
                'location_id'     => $orderData['location_id'] ?? null,
                'payload'         => json_encode($rawPayload, JSON_UNESCAPED_UNICODE)
            ];
            Utils::log('insert square_webhooks: '.json_encode($insertData, JSON_UNESCAPED_UNICODE),'INFO','SquareWebhook');
            $lastId = $db->insert('square_webhooks',$insertData);
            Utils::log("square_webhooks inserted id={$lastId}",'INFO','SquareWebhook');
        }
    }catch(Exception $e){
        Utils::log('saveWebhookPayload error: '.$e->getMessage(),'ERROR','SquareWebhook');
        Utils::log('stack: '.$e->getTraceAsString(),'ERROR','SquareWebhook');
    }
}

/**
 * Square Order 配列から対応する order_session_id を多段フォールバックで取得
 *   A) metadata.order_session_id
 *   B) line_items.catalog_object_id -> order_sessions.square_item_id
 *   C) line_items.name から fg#<room>-<21桁> を正規表現で抜く
 *   D) reference_id (room_number) で is_active=1 のセッションが 1 件だけなら採用
 * 戻り値 : 見つかった 21 桁のセッション ID 文字列、または null
 */
function resolveSessionIdFromSquareOrder(array $order): ?string
{
    $db = Database::getInstance();

    // A. metadata
    $meta = $order['metadata'] ?? [];
    if (!empty($meta['order_session_id'])) {
        return $meta['order_session_id'];
    }
    if (!empty($meta['orderSessionId'])) {
        return $meta['orderSessionId'];
    }

    // B. catalog_object_id
    $lineItems = $order['line_items'] ?? $order['lineItems'] ?? [];
    if ($lineItems) {
        $catalogIds = array_column($lineItems, 'catalog_object_id');
        if ($catalogIds) {
            $inClause = implode(',', array_fill(0, count($catalogIds), '?'));
            $row = $db->selectOne(
                "SELECT id FROM order_sessions
                  WHERE (square_variation_id IN ($inClause) OR square_item_id IN ($inClause))
                    AND is_active = 1
                  LIMIT 1",
                array_merge($catalogIds, $catalogIds)
            );
            if ($row) return $row['id'];
        }
    }

    // C. 商品名 fg#<room>-<session>
    foreach ($lineItems as $li) {
        if (isset($li['name']) && preg_match('/^fg#\d{1,4}-(\d{21})$/', $li['name'], $m)) {
            return $m[1];
        }
    }

    // D. reference_id (=room_number) で唯一のセッション
    $room = $order['reference_id'] ?? $order['referenceId'] ?? '';
    if ($room) {
        $rows = $db->selectAll(
            "SELECT id FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 2", [$room]
        );
        if (count($rows) === 1) {
            return $rows[0]['id'];
        }
    }

    return null;
}

// === 追加関数: Webhook ログ出力 & ローテ ===
/**
 * Square Webhook 受信ペイロードをファイルへ出力（300KB ローテーション）
 * @param array  $headers 受信ヘッダ
 * @param string $body    受信ボディ
 */
function writeSquareWebhookLog(array $headers, string $body): void {
    $logDir  = __DIR__ . '/../../logs'; // fgsquare/logs
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    // 統一: SquareWebhook 専用ログ (Utils::log と同じファイル名)
    $logFile = $logDir . '/SquareWebhook.log';

    // JST タイムスタンプ
    $dt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
    $timestamp = $dt->format('Y-m-d H:i:s');

    $entry  = "[$timestamp] ----- Webhook Received -----\n";
    $entry .= 'Headers: ' . json_encode($headers, JSON_UNESCAPED_UNICODE) . "\n";
    $entry .= 'Body: ' . $body . "\n\n";

    @file_put_contents($logFile, $entry, FILE_APPEND);

    // ローテーション（300KB 超過で末尾 20% 残す）
    rotateSquareWebhookLog($logFile, 300 * 1024, 0.2);
}

/**
 * ログローテーション: 指定サイズを超過したら末尾 keepRatio (%) を残して truncate
 * @param string $filePath
 * @param int    $maxBytes   サイズ上限 (byte)
 * @param float  $keepRatio  残す割合 (0-1)
 */
function rotateSquareWebhookLog(string $filePath, int $maxBytes, float $keepRatio = 0.2): void {
    if (!is_file($filePath)) { return; }
    $size = filesize($filePath);
    if ($size <= $maxBytes) { return; }

    $keepBytes = (int)($maxBytes * $keepRatio);
    $fp = fopen($filePath, 'r+');
    if (!$fp) { return; }
    fseek($fp, -$keepBytes, SEEK_END);
    $data = fread($fp, $keepBytes);
    rewind($fp);
    fwrite($fp, $data);
    ftruncate($fp, strlen($data));
    fclose($fp);
} 