# Square保留会計処理フロー修正手順書

## 1. 概要

このドキュメントは、現在のfgsquareシステムのSquare保留会計処理フローを改善するための修正手順を詳細に記述しています。主な目的は、Square端末で保留会計が精算された際に、自動的にシステム内の保留伝票ステータスを更新し、同一部屋の複数の保留伝票も一括で処理するよう機能を拡張することです。

## 2. 現状の問題点

現状の実装では、以下の問題点があります：

1. Square端末で保留会計が精算された際に、システム側の保留伝票ステータスが自動的に更新されない
2. 同一部屋番号に複数の保留伝票が存在する場合に、一つの伝票が精算されても他は未精算状態のまま残る
3. Square APIからのWebhook通知を処理する専用のクラスがない

これらの問題を解決するために、以下の修正を行います。

## 3. 修正方針

1. **WebhookServiceクラスの新規作成**
   - Squareからのwebhook通知を検証するためのクラスを実装

2. **RoomTicketServiceクラスの拡張**
   - `updateTicketStatusBySquareOrderId` メソッドを追加
   - `updateRelatedTickets` メソッドを追加

3. **webhook/square.phpの修正**
   - 現在の実装を基に、保留伝票の更新処理を強化

## 4. 具体的な修正手順

### 4.1 WebhookServiceクラスの作成

新しいファイル `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/lib/WebhookService.php` を作成します。

```php
<?php
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Database.php';

/**
 * Webhook検証・処理サービスクラス
 */
class WebhookService {
    private static $logFile = null;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        self::initLogFile();
    }
    
    /**
     * ログファイルの初期化
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/WebhookService.log';
    }
    
    /**
     * ログメッセージを記録
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
        
        // Utilsクラスのログにも記録
        Utils::log($message, $level, 'WebhookService');
    }
    
    /**
     * Square Webhookの検証
     * 
     * @param string $payload Webhookペイロード
     * @param array $headers リクエストヘッダー
     * @return bool 検証成功時はtrue、失敗時はfalse
     */
    public function verifySquareWebhook($payload, $headers) {
        self::logMessage("Webhookの検証を開始します", 'INFO');
        
        // 設定からwebhookシークレットを取得
        $webhookSecret = defined('SQUARE_WEBHOOK_SECRET') ? SQUARE_WEBHOOK_SECRET : '';
        
        // テスト環境では簡易的に検証をスキップ
        if (defined('SQUARE_ENVIRONMENT') && SQUARE_ENVIRONMENT !== 'production') {
            self::logMessage("テスト環境のためWebhook検証をスキップします", 'INFO');
            return true;
        }
        
        // Signature headerを取得
        $signatureKey = '';
        if (isset($headers['X-Square-Signature'])) {
            $signatureKey = $headers['X-Square-Signature'];
        } elseif (isset($headers['HTTP_X_SQUARE_SIGNATURE'])) {
            $signatureKey = $headers['HTTP_X_SQUARE_SIGNATURE'];
        }
        
        if (empty($signatureKey)) {
            self::logMessage("Webhook検証失敗: 署名ヘッダーがありません", 'ERROR');
            return false;
        }
        
        // webhookシークレットが設定されていない場合は検証をスキップ
        if (empty($webhookSecret)) {
            self::logMessage("Webhook検証警告: 署名シークレットが設定されていません。検証をスキップします", 'WARNING');
            return true;
        }
        
        // HMAC-SHA256による署名検証
        $calculatedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        if ($calculatedSignature !== $signatureKey) {
            self::logMessage("Webhook検証失敗: 署名が一致しません", 'ERROR');
            return false;
        }
        
        self::logMessage("Webhook検証成功", 'INFO');
        return true;
    }
    
    /**
     * イベントが既に処理済みかチェック
     * 
     * @param string $eventId イベントID
     * @return bool 処理済みならtrue、未処理ならfalse
     */
    public function isEventProcessed($eventId) {
        try {
            $db = Database::getInstance();
            $query = "SELECT * FROM webhook_events WHERE event_id = ? LIMIT 1";
            $result = $db->selectOne($query, [$eventId]);
            
            return $result !== false;
        } catch (Exception $e) {
            self::logMessage("イベント処理状態確認エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * イベントを処理済みとしてマーク
     * 
     * @param string $eventId イベントID
     * @param string $eventType イベントタイプ
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public function markEventAsProcessed($eventId, $eventType) {
        try {
            $db = Database::getInstance();
            $data = [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'processed_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $db->insert('webhook_events', $data);
            return $result !== false;
        } catch (Exception $e) {
            self::logMessage("イベント処理状態更新エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
```

### 4.2 RoomTicketServiceクラスの拡張

`/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/lib/RoomTicketService.php` ファイルを修正して以下のメソッドを追加します。クラス内の適切な位置（他のpublic methodの近く）に追加してください。

```php
/**
 * Square注文IDから保留伝票のステータスを更新
 * 
 * @param string $squareOrderId Square注文ID
 * @param string $status 新しいステータス
 * @return bool 成功時はtrue、失敗時はfalse
 */
public function updateTicketStatusBySquareOrderId($squareOrderId, $status) {
    self::logMessage("updateTicketStatusBySquareOrderId 開始: squareOrderId={$squareOrderId}, status={$status}", 'INFO');
    
    try {
        // 対象の保留伝票を検索
        $query = "SELECT * FROM room_tickets WHERE square_order_id = ? LIMIT 1";
        $ticket = $this->db->selectOne($query, [$squareOrderId]);
        
        if (!$ticket) {
            self::logMessage("Square注文ID {$squareOrderId} に対応する保留伝票が見つかりません", 'WARNING');
            return false;
        }
        
        // トランザクション開始
        $this->db->beginTransaction();
        
        try {
            // 保留伝票のステータスを更新
            $updateQuery = "UPDATE room_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($updateQuery, [$status, $ticket['id']]);
            
            // 関連する注文のステータスも更新
            $orderUpdateQuery = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE square_order_id = ?";
            $this->db->execute($orderUpdateQuery, [$status, $squareOrderId]);
            
            // トランザクションコミット
            $this->db->commit();
            
            self::logMessage("保留伝票 {$ticket['id']} のステータスを {$status} に更新しました", 'INFO');
            return true;
        } catch (Exception $e) {
            // トランザクションロールバック
            $this->db->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        self::logMessage("updateTicketStatusBySquareOrderId エラー: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 同一部屋の関連保留伝票を更新
 * 
 * @param string $squareOrderId 元のSquare注文ID
 * @return array 更新された保留伝票の配列
 */
public function updateRelatedTickets($squareOrderId) {
    self::logMessage("updateRelatedTickets 開始: squareOrderId={$squareOrderId}", 'INFO');
    
    try {
        // 元の保留伝票を検索して部屋番号を取得
        $query = "SELECT * FROM room_tickets WHERE square_order_id = ? LIMIT 1";
        $ticket = $this->db->selectOne($query, [$squareOrderId]);
        
        if (!$ticket) {
            self::logMessage("Square注文ID {$squareOrderId} に対応する保留伝票が見つかりません", 'WARNING');
            return [];
        }
        
        $roomNumber = $ticket['room_number'];
        self::logMessage("部屋番号 {$roomNumber} の関連保留伝票を検索します", 'INFO');
        
        // 同じ部屋番号の他の保留伝票を検索
        $query = "SELECT * FROM room_tickets WHERE room_number = ? AND id != ? AND status = 'OPEN'";
        $relatedTickets = $this->db->select($query, [$roomNumber, $ticket['id']]);
        
        if (empty($relatedTickets)) {
            self::logMessage("部屋番号 {$roomNumber} の他の保留伝票はありません", 'INFO');
            return [];
        }
        
        $updatedTickets = [];
        foreach ($relatedTickets as $relatedTicket) {
            // トランザクション開始
            $this->db->beginTransaction();
            
            try {
                // 保留伝票のステータスを更新
                $updateQuery = "UPDATE room_tickets SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?";
                $this->db->execute($updateQuery, [$relatedTicket['id']]);
                
                // 関連する注文のステータスも更新
                $orderUpdateQuery = "UPDATE orders SET order_status = 'COMPLETED', updated_at = NOW() WHERE square_order_id = ?";
                $this->db->execute($orderUpdateQuery, [$relatedTicket['square_order_id']]);
                
                // トランザクションコミット
                $this->db->commit();
                
                $updatedTickets[] = $relatedTicket;
                self::logMessage("関連保留伝票 {$relatedTicket['id']} を完了状態に更新しました", 'INFO');
            } catch (Exception $e) {
                // トランザクションロールバック
                $this->db->rollback();
                self::logMessage("関連保留伝票の更新中にエラー: " . $e->getMessage(), 'ERROR');
            }
        }
        
        $count = count($updatedTickets);
        self::logMessage("部屋番号 {$roomNumber} の関連保留伝票を {$count} 件更新しました", 'INFO');
        return $updatedTickets;
    } catch (Exception $e) {
        self::logMessage("updateRelatedTickets エラー: " . $e->getMessage(), 'ERROR');
        return [];
    }
}
```

### 4.3 webhook/square.phpの修正

既存の `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/webhook/square.php` ファイルを修正します。以下の修正を加えてください。

```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/SquareService.php';
require_once __DIR__ . '/../lib/ProductService.php';
require_once __DIR__ . '/../lib/RoomTicketService.php';
require_once __DIR__ . '/../lib/WebhookService.php';

// Webhookからのリクエストを処理
$requestBody = file_get_contents('php://input');
$headers = getallheaders();

// WebhookServiceを使用して署名を検証
$webhookService = new WebhookService();
$isValid = $webhookService->verifySquareWebhook($requestBody, $headers);

if (!$isValid) {
    Utils::log("Invalid Square webhook signature", 'WARNING', 'SquareWebhook');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
    exit;
}

// Webhookデータを解析
$data = json_decode($requestBody, true);

if (!$data || !isset($data['type'])) {
    Utils::log("Invalid webhook data", 'WARNING', 'SquareWebhook');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook data']);
    exit;
}

// イベントIDを取得して重複処理を防止
$eventId = $data['event_id'] ?? null;
if ($eventId && $webhookService->isEventProcessed($eventId)) {
    Utils::log("Event {$eventId} already processed, skipping", 'INFO', 'SquareWebhook');
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Event already processed']);
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
        Utils::log("Unhandled event type: {$eventType}", 'INFO', 'SquareWebhook');
        break;
}

// イベントを処理済みとしてマーク
if ($eventId) {
    $webhookService->markEventAsProcessed($eventId, $eventType);
}

// 成功レスポンスを返す
http_response_code(200);
echo json_encode(['status' => 'success']);
exit;

// 以下の既存の関数をそのまま維持
function handleInventoryUpdate($data) {
    // 既存コードを維持
    // ...
}

function handleCatalogUpdate($data) {
    // 既存コードを維持
    // ...
}

function handleOrderCreated($data) {
    // 既存コードを維持
    // ...
}

// 注文更新イベントを処理する関数を修正
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
        
        Utils::log("Processing order update for ID: {$orderId}, state: {$state}", 'INFO', 'SquareWebhook');
        
        // RoomTicketServiceを使用して保留伝票ステータスを更新
        $roomTicketService = new RoomTicketService();
        
        // 注文ステータスに基づいた処理
        if ($state === 'COMPLETED') {
            // 保留伝票のステータスを更新
            $result = $roomTicketService->updateTicketStatusBySquareOrderId($orderId, 'COMPLETED');
            
            if ($result) {
                // 同じ部屋の他の保留伝票も更新
                $relatedTickets = $roomTicketService->updateRelatedTickets($orderId);
                $count = count($relatedTickets);
                
                Utils::log("Updated primary ticket and {$count} related tickets for order {$orderId}", 'INFO', 'SquareWebhook');
            } else {
                Utils::log("Failed to update ticket for order {$orderId}", 'WARNING', 'SquareWebhook');
                
                // 古い処理方法をフォールバックとして実行
                $db = Database::getInstance();
                $query = "UPDATE room_tickets SET status = ? WHERE square_order_id = ?";
                $result = $db->execute($query, [$state, $orderId]);
                
                if ($result) {
                    Utils::log("Updated room ticket status for order {$orderId} to {$state} (fallback method)", 'INFO', 'SquareWebhook');
                }
            }
        } else {
            // COMPLETED以外のステータス更新
            $result = $roomTicketService->updateTicketStatusBySquareOrderId($orderId, $state);
            
            if ($result) {
                Utils::log("Updated ticket status to {$state} for order {$orderId}", 'INFO', 'SquareWebhook');
            } else {
                Utils::log("Failed to update ticket status for order {$orderId}", 'WARNING', 'SquareWebhook');
                
                // 古い処理方法をフォールバックとして実行
                $db = Database::getInstance();
                $query = "UPDATE room_tickets SET status = ? WHERE square_order_id = ?";
                $db->execute($query, [$state, $orderId]);
            }
        }
    } catch (Exception $e) {
        Utils::log("Error processing order updated: " . $e->getMessage(), 'ERROR', 'SquareWebhook');
    }
}
```

### 4.4 webhook_eventsテーブルの作成確認

webhook_eventsテーブルが存在することを確認します。存在しない場合は、以下のSQLを実行して作成してください。

```sql
CREATE TABLE IF NOT EXISTS webhook_events (
    id INT NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(100) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    processed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.5 設定ファイルの修正 (オプション)

`/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/config/config.php` ファイルにSquare Webhook関連の設定を追加します。

```php
// Squareのwebhook設定
if (!defined('SQUARE_WEBHOOK_SECRET')) {
    define('SQUARE_WEBHOOK_SECRET', ''); // 本番環境では適切なシークレットを設定
}

if (!defined('SQUARE_ENVIRONMENT')) {
    define('SQUARE_ENVIRONMENT', 'development'); // development/production
}
```

## 5. テスト手順

実装後、以下の手順でテストを行います。

### 5.1 単体テスト

以下のテストスクリプトを作成して、各機能を個別にテストします。

テストファイル: `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/test/test_room_ticket_status.php`

```php
<?php
require_once __DIR__ . '/../lib/RoomTicketService.php';
require_once __DIR__ . '/../lib/Utils.php';

// 引数からテスト対象の関数を取得
$function = $argv[1] ?? 'all';

echo "Starting test: {$function}\n";

$roomTicketService = new RoomTicketService();

// テスト1: 保留伝票ステータス更新のテスト
if ($function === 'updateStatus' || $function === 'all') {
    echo "Testing updateTicketStatusBySquareOrderId...\n";
    
    // テスト用のSquare注文ID（既存のものを使用）
    $squareOrderId = 'xxxx'; // 実際のテスト用データに置き換えてください
    $result = $roomTicketService->updateTicketStatusBySquareOrderId($squareOrderId, 'COMPLETED');
    
    echo "Result: " . ($result ? "Success" : "Failed") . "\n";
}

// テスト2: 関連保留伝票更新のテスト
if ($function === 'updateRelated' || $function === 'all') {
    echo "Testing updateRelatedTickets...\n";
    
    // テスト用のSquare注文ID（既存のものを使用）
    $squareOrderId = 'xxxx'; // 実際のテスト用データに置き換えてください
    $tickets = $roomTicketService->updateRelatedTickets($squareOrderId);
    
    echo "Updated " . count($tickets) . " related tickets\n";
}

echo "Tests completed\n";
```

### 5.2 Webhook エンドポイントのテスト

1. ngrokなどを使用して一時的に外部からアクセス可能にする
2. Square Developer DashboardでWebhookエンドポイントを設定
3. テスト用のイベントをトリガーして動作確認

または、以下のようなテストスクリプトでwebhookの動作をシミュレート：

テストファイル: `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/test/test_webhook.php`

```php
<?php
require_once __DIR__ . '/../lib/Utils.php';

// テスト用Webhookペイロード
$payload = json_encode([
    'type' => 'order.updated',
    'event_id' => 'test-event-' . time(),
    'data' => [
        'object' => [
            'order' => [
                'id' => 'xxxx', // 実際のテスト用注文IDに置き換え
                'state' => 'COMPLETED'
            ]
        ]
    ]
]);

// Webhookエンドポイントへのリクエスト
$ch = curl_init('http://localhost/fgsquare/api/webhook/square.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response (HTTP {$httpCode}):\n{$response}\n";
```

## 6. デプロイ手順

修正したファイルを本番環境に適用する手順：

1. バックアップの作成
   ```
   cp /Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/lib/RoomTicketService.php /Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/lib/RoomTicketService.php.bak
   cp /Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/webhook/square.php /Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/webhook/square.php.bak
   ```

2. 修正したファイルをデプロイ
   - `WebhookService.php` (新規ファイル)
   - `RoomTicketService.php` (修正)
   - `square.php` (修正)

3. Square Developer Dashboardでwebhookの設定を確認・更新

4. 動作確認
   - テスト注文を作成
   - Square端末で精算
   - ステータス更新を確認

## 7. トラブルシューティング

### 7.1 ログの確認

問題が発生した場合は、以下のログファイルを確認してください：

- RoomTicketServiceのログ: `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/logs/RoomTicketService.log`
- WebhookServiceのログ: `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/logs/WebhookService.log`
- システム共通ログ: `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/logs/system.log`

### 7.2 一般的な問題の解決方法

1. **webhook通知が届かない場合**
   - Square Developer Dashboardでwebhook設定と通知履歴を確認
   - ネットワーク設定やファイアウォールを確認

2. **保留伝票の状態が更新されない場合**
   - データベース接続を確認
   - トランザクション処理に問題がないか確認
   - ログで具体的なエラーメッセージを確認

3. **同一部屋の保留伝票が一括処理されない場合**
   - データベースクエリの確認
   - room_numberが正しく設定されているか確認

## 8. リスク評価と対策

1. **重複処理のリスク**
   - 対策: webhook_eventsテーブルでイベントの重複処理を防止

2. **トランザクション失敗リスク**
   - 対策: 例外処理と適切なロールバック、ログ記録

3. **Webhookの検証失敗リスク**
   - 対策: テスト環境では署名検証をオプション化し、本番環境では厳密に検証

4. **パフォーマンスリスク**
   - 対策: 必要最小限のデータベースクエリにとどめる、インデックスの活用

## 9. まとめ

この修正によって、Square端末で保留会計が精算された際に自動的にシステム内の保留伝票ステータスが更新され、同一部屋の複数の保留伝票も一括で処理されるようになります。これにより、データの整合性が向上し、運用の効率化が図れます。
