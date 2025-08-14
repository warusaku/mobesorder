<?php
/**
 * Webhook ユーティリティ関数
 * 
 * webhook_events テーブルにイベントを登録するためのユーティリティ関数を提供します
 */

/**
 * Webhookイベントを登録する
 * 
 * @param PDO $db データベース接続
 * @param string $eventType イベントタイプ (例: 'order_notification', 'inventory_alert', 'system_alert')
 * @param array $payload イベントデータ（JSONに変換されます）
 * @param string $eventId イベントID（省略可）- 省略時は自動生成
 * @return int|bool 成功時はイベントID、失敗時はfalse
 */
function registerWebhookEvent($db, $eventType, $payload, $eventId = null) {
    try {
        // イベントIDが指定されていない場合は自動生成
        if ($eventId === null) {
            $eventId = uniqid('evt_', true);
        }
        
        // ペイロードがJSON形式ではない場合はJSONに変換
        $payloadJson = is_string($payload) ? $payload : json_encode($payload);
        
        // webhook_events テーブルに挿入
        $stmt = $db->prepare("
            INSERT INTO webhook_events (event_id, event_type, payload, created_at)
            VALUES (:event_id, :event_type, :payload, NOW())
        ");
        
        $stmt->bindParam(':event_id', $eventId);
        $stmt->bindParam(':event_type', $eventType);
        $stmt->bindParam(':payload', $payloadJson);
        $result = $stmt->execute();
        
        if ($result) {
            return $db->lastInsertId();
        } else {
            error_log("Webhookイベント登録失敗: " . implode(', ', $stmt->errorInfo()));
            return false;
        }
    } catch (Exception $e) {
        error_log("Webhookイベント登録エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * 注文通知のWebhookイベントを登録
 * 
 * @param PDO $db データベース接続
 * @param array $orderData 注文データ
 * @return int|bool 成功時はイベントID、失敗時はfalse
 */
function registerOrderNotification($db, $orderData) {
    return registerWebhookEvent($db, 'order_notification', $orderData);
}

/**
 * 在庫アラートのWebhookイベントを登録
 * 
 * @param PDO $db データベース接続
 * @param string $productName 商品名
 * @param int $stockQuantity 在庫数
 * @param string $squareItemId Square商品ID（省略可）
 * @return int|bool 成功時はイベントID、失敗時はfalse
 */
function registerInventoryAlert($db, $productName, $stockQuantity, $squareItemId = null) {
    $payload = [
        'product_name' => $productName,
        'stock_quantity' => $stockQuantity,
        'timestamp' => date('c')
    ];
    
    if ($squareItemId !== null) {
        $payload['square_item_id'] = $squareItemId;
    }
    
    return registerWebhookEvent($db, 'inventory_alert', $payload);
}

/**
 * システムアラートのWebhookイベントを登録
 * 
 * @param PDO $db データベース接続
 * @param string $message アラートメッセージ
 * @param string $level アラートレベル (INFO, WARNING, ERROR, CRITICAL)
 * @param array $details 追加詳細（省略可）
 * @return int|bool 成功時はイベントID、失敗時はfalse
 */
function registerSystemAlert($db, $message, $level = 'WARNING', $details = []) {
    $payload = [
        'message' => $message,
        'level' => $level,
        'timestamp' => date('c')
    ];
    
    if (!empty($details)) {
        $payload['details'] = $details;
    }
    
    return registerWebhookEvent($db, 'system_alert', $payload);
}

/**
 * カスタムWebhookイベントを登録
 * 
 * @param PDO $db データベース接続
 * @param string $eventType カスタムイベントタイプ
 * @param array $data イベントデータ
 * @return int|bool 成功時はイベントID、失敗時はfalse
 */
function registerCustomEvent($db, $eventType, $data) {
    return registerWebhookEvent($db, $eventType, $data);
}

/**
 * webhook_eventsテーブルが存在するか確認し、存在しない場合は作成する
 * 
 * @param PDO $db データベース接続
 * @return bool 成功フラグ
 */
function ensureWebhookEventsTable($db) {
    try {
        // テーブルの存在チェック
        $stmt = $db->prepare("
            SELECT 1 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'webhook_events'
        ");
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            // テーブルが存在しない場合は作成
            $createTableSQL = "
                CREATE TABLE webhook_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id VARCHAR(100) NOT NULL,
                    event_type VARCHAR(50) NOT NULL,
                    payload TEXT,
                    processed TINYINT(1) NOT NULL DEFAULT 0,
                    processed_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY (event_id),
                    INDEX (event_type),
                    INDEX (processed, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $result = $db->exec($createTableSQL);
            
            if ($result !== false) {
                error_log("webhook_events テーブルを作成しました");
                return true;
            } else {
                error_log("webhook_events テーブル作成失敗: " . implode(', ', $db->errorInfo()));
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("テーブル確認/作成エラー: " . $e->getMessage());
        return false;
    }
} 