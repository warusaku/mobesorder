<?php
/**
 * Square API連携サービス 部屋チケット管理クラス
 * Version: 1.0.0
 * Description: 部屋用の保留伝票の作成と管理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';
require_once __DIR__ . '/SquareService_Utility.php';
require_once __DIR__ . '/SquareService_opt_Order.php';

use Square\Exceptions\ApiException;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\Money;
use Square\Models\UpdateOrderRequest;

class SquareService_opt_RoomTicket extends SquareService_Base {
    
    private $utilityService;
    private $orderService;
    
    // 最近作成されたチケットを一時的に保存する静的変数
    private static $recentlyCreatedTickets = [];
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct();
        $this->utilityService = new SquareService_Utility();
        $this->orderService = new SquareService_opt_Order();
    }
    
    /**
     * 部屋用の保留伝票を作成
     * 
     * @param string $roomNumber 部屋番号
     * @param string $guestName ゲスト名（オプション）
     * @param string $lineUserId LINE User ID（オプション）
     * @return array|false 成功時は保留伝票情報、失敗時はfalse
     */
    public function createRoomTicket($roomNumber, $guestName = '', $lineUserId = null) {
        try {
            $this->logger->logMessage("createRoomTicket 開始: roomNumber={$roomNumber}, guestName={$guestName}, lineUserId={$lineUserId}", 'INFO');
            
            if (empty($roomNumber)) {
                $this->logger->logMessage("部屋番号が指定されていません", 'ERROR');
                return false;
            }
            
            // よりシンプルな接続テスト - 完全なAPIリクエストではなくロケーションIDの検証のみ
            if (empty($this->locationId)) {
                $this->logger->logMessage("Square API locationId が設定されていません。設定を確認してください。", 'ERROR');
                Utils::log("Square API locationId が設定されていません", 'ERROR', 'SquareService');
                return false;
            }
            
            $this->logger->logMessage("Square API createRoomTicket 処理開始: locationId={$this->locationId}", 'INFO');
            
            // Square APIへの接続テストを先に実行
            $this->logger->logMessage("Square API接続テストを実行します", 'INFO');
            $testResult = $this->testConnection();
            if (!$testResult) {
                $this->logger->logMessage("Square API接続テストに失敗しました。接続設定を確認してください。", 'ERROR');
                Utils::log("Square API接続テスト失敗", 'ERROR', 'SquareService');
                
                // 証明書ファイルの存在確認
                $certPath = __DIR__ . '/../certificates/cacert.pem';
                if (file_exists($certPath)) {
                    $this->logger->logMessage("証明書ファイルは存在します：" . $certPath, 'INFO');
                    $certSize = filesize($certPath);
                    $this->logger->logMessage("証明書ファイルサイズ：" . $certSize . " バイト", 'INFO');
                } else {
                    $this->logger->logMessage("証明書ファイルが見つかりません：" . $certPath, 'ERROR');
                }
            } else {
                $this->logger->logMessage("Square API接続テスト成功: " . json_encode($testResult), 'INFO');
            }
            
            $orderApi = $this->client->getOrdersApi();
            
            // 部屋用の空の注文を作成
            $order = new Order($this->locationId);
            $order->setState('OPEN');
            $order->setReferenceId($roomNumber);
            
            // 空のライン項目配列を設定（Square API v30.0では必須）
            $order->setLineItems([]);
            
            // 空のフルフィルメント配列を設定（Square API v30.0では必要な場合がある）
            $order->setFulfillments([]);
            
            // LINE User IDの取得（パラメータから取得する）
            // パラメータで渡されていない場合はリクエストヘッダーから取得を試みる
            if (empty($lineUserId) && isset($_SERVER['HTTP_X_LINE_USER_ID'])) {
                $lineUserId = $_SERVER['HTTP_X_LINE_USER_ID'];
                $this->logger->logMessage("リクエストヘッダーからLINE User IDを取得: {$lineUserId}", 'INFO');
            }
            
            // メタデータを追加
            $metadata = [
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'order_source' => 'mobile_order',
                'is_room_ticket' => 'true',
                'note' => "Room {$roomNumber} Order"  // 注記情報をメタデータに含める
            ];
            
            // メタデータにguest_nameが含まれているか確認し、なければLINE情報から設定
            if (empty($metadata['guest_name'])) {
                // guest_name自動設定
                $this->utilityService->setupGuestNameFromLineUserId($metadata, $lineUserId, $roomNumber);
            }
            
            $this->logger->logMessage("注文メタデータを設定: " . json_encode($metadata), 'INFO');
            $order->setMetadata($metadata);
            
            // べき等性キーを日時を含めた形式にして一意性を高める
            $idempotencyKey = 'room_ticket_' . $roomNumber . '_' . uniqid('', true) . '_' . time();
            $this->logger->logMessage("べき等性キー: {$idempotencyKey}", 'INFO');
            
            // リクエストオブジェクトを作成
            $body = new CreateOrderRequest();
            $body->setOrder($order);
            $body->setIdempotencyKey($idempotencyKey);
            
            // APIリクエストの詳細をロギング（デバッグ用）
            $debugRequestInfo = json_encode([
                'location_id' => $this->locationId,
                'idempotency_key' => $idempotencyKey,
                'order_reference_id' => $roomNumber,
                'has_line_items' => is_array($order->getLineItems()),
                'has_fulfillments' => is_array($order->getFulfillments()),
                'has_money_fields' => false // 金額フィールドは設定しないためfalse
            ]);
            $this->logger->logMessage("リクエスト詳細情報: " . $debugRequestInfo, 'INFO');
            
            $this->logger->logMessage("Square API createOrder リクエスト準備完了", 'INFO');
            Utils::log("Square API createOrderリクエスト準備完了: room={$roomNumber}", 'DEBUG', 'SquareService');
            
            try {
                // リクエスト直前にメタデータの最終検証を追加
                $metadata = $order->getMetadata();
                if (is_array($metadata)) {
                    // 特にguest_nameフィールドを検証
                    if (empty($metadata['guest_name']) || $metadata['guest_name'] === '') {
                        // 空の場合は自動的にデフォルト値を設定
                        $metadata['guest_name'] = 'Guest_' . uniqid();
                        $this->logger->logMessage("警告: guest_name が空のため自動設定: " . $metadata['guest_name'], 'WARNING');
                        // 更新したメタデータを設定
                        $order->setMetadata($metadata);
                        
                        // リクエスト内容を更新
                        $body->setOrder($order);
                        
                        $this->logger->logMessage("メタデータ再検証後の更新: " . json_encode($metadata), 'INFO');
                    }
                }
                
                // リクエストの実行
                $this->logger->logMessage("Square API createOrderリクエスト送信開始", 'INFO');
                $response = $orderApi->createOrder($body);
                $this->logger->logMessage("Square API createOrderリクエスト送信完了", 'INFO');
                
                if ($response->isSuccess()) {
                    $result = $response->getResult();
                    $orderData = $result->getOrder();
                    
                    $orderId = $orderData->getId();
                    $this->logger->logMessage("Room Ticket Created for Room {$roomNumber}: {$orderId}", 'INFO');
                    
                    // 作成したチケット情報を静的変数に保存（同期問題対策）
                    self::$recentlyCreatedTickets[$roomNumber] = [
                        'square_order_id' => $orderId,
                        'room_number' => $roomNumber,
                        'status' => $orderData->getState(),
                        'created_at' => $orderData->getCreatedAt(),
                        'timestamp' => time() // 作成時のタイムスタンプを記録
                    ];
                    $this->logger->logMessage("チケット情報をメモリに保存: room={$roomNumber}, id={$orderId}", 'INFO');
                    
                    $ticketData = [
                        'square_order_id' => $orderId,
                        'room_number' => $roomNumber,
                        'status' => $orderData->getState(),
                        'created_at' => $orderData->getCreatedAt()
                    ];
                    
                    $this->logger->logMessage("作成されたチケットデータ: " . json_encode($ticketData), 'INFO');
                    return $ticketData;
                } else {
                    $errors = $response->getErrors();
                    $errorDetails = [];
                    
                    foreach ($errors as $error) {
                        $errorDetails[] = [
                            'category' => $error->getCategory(),
                            'code' => $error->getCode(),
                            'detail' => $error->getDetail(),
                            'field' => method_exists($error, 'getField') ? $error->getField() : null
                        ];
                        
                        // より詳細なエラー情報を個別にログに記録
                        $errorMsg = "Square API Error: " . 
                            "Category=" . $error->getCategory() . 
                            ", Code=" . $error->getCode() . 
                            ", Detail=" . $error->getDetail();
                        
                        if (method_exists($error, 'getField') && $error->getField()) {
                            $errorMsg .= ", Field=" . $error->getField();
                        }
                        
                        $this->logger->logMessage($errorMsg, 'ERROR');
                        Utils::log($errorMsg, 'ERROR', 'SquareService');
                        
                        // MISSING_REQUIRED_PARAMETERエラーの場合、追加情報を取得
                        if ($error->getCode() === 'MISSING_REQUIRED_PARAMETER') {
                            // 実行中の関数名とリクエスト内容を詳細にログ
                            $this->logger->logMessage("必須パラメータエラー詳細 - 関数: createRoomTicket, " . 
                                "Order内容: " . json_encode([
                                    'state' => $order->getState(),
                                    'reference_id' => $order->getReferenceId(),
                                    'line_items_count' => is_array($order->getLineItems()) ? count($order->getLineItems()) : 'null',
                                    'fulfillments_count' => is_array($order->getFulfillments()) ? count($order->getFulfillments()) : 'null',
                                    'has_money_fields' => false, // 金額フィールドは設定しない
                                    'location_id' => $this->locationId,
                                    'metadata' => $order->getMetadata()
                                ]), 'ERROR');
                        }
                    }
                    
                    $this->logger->logMessage("Square API チケット作成エラー: " . json_encode($errorDetails), 'ERROR');
                    Utils::log("Square API チケット作成エラー: " . json_encode($errorDetails), 'ERROR', 'SquareService');
                    return false;
                }
            } catch (ApiException $e) {
                $this->logger->logMessage("Square API Exception during createOrder: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
                Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
                
                // レスポンスが取得できる場合は内容をログに記録
                $responseBody = $e->getResponseBody();
                if ($responseBody) {
                    $bodyJson = json_encode($responseBody);
                    $this->logger->logMessage("API Error Response: " . $bodyJson, 'ERROR');
                    Utils::log("API Error Response Body: " . $bodyJson, 'ERROR', 'SquareService');
                }
                
                // PHPのエラー情報も記録
                $lastError = error_get_last();
                if ($lastError) {
                    $this->logger->logMessage("最後のPHPエラー: " . json_encode($lastError), 'ERROR');
                }
                
                return false;
            }
        } catch (Exception $e) {
            $this->logger->logMessage("一般的な例外 (createRoomTicket): " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("一般的な例外: " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        }
    }
    
    /**
     * 部屋に関連付けられた保留伝票を取得
     * 
     * @param string $roomNumber 部屋番号
     * @return array|false 成功時は保留伝票情報、失敗時はfalse
     */
    public function getRoomTicket($roomNumber) {
        try {
            $this->logger->logMessage("getRoomTicket開始: roomNumber={$roomNumber}", 'INFO');
            
            // 部屋番号に関連する注文を検索
            $orders = $this->orderService->searchOrdersByRoom($roomNumber);
            
            // 検索結果のログ詳細
            if (empty($orders)) {
                $this->logger->logMessage("部屋 {$roomNumber} の注文が見つかりませんでした", 'WARNING');
                
                // APIで見つからない場合、最近作成されたチケットを確認する（同期問題対策）
                if (isset(self::$recentlyCreatedTickets[$roomNumber])) {
                    $recentTicket = self::$recentlyCreatedTickets[$roomNumber];
                    
                    // 作成から60秒以内のチケットのみ使用（古いチケットは除外）
                    $timeDiff = time() - $recentTicket['timestamp'];
                    if ($timeDiff <= 60) { // 1分以内
                        $this->logger->logMessage("メモリ内のチケット情報を使用: id={$recentTicket['square_order_id']}, 経過時間={$timeDiff}秒", 'INFO');
                        
                        // 直接Orderを取得
                        $orderDetails = $this->orderService->getOrder($recentTicket['square_order_id']);
                        
                        if ($orderDetails) {
                            // 最新情報を取得できた場合
                            $this->logger->logMessage("メモリ内チケットの最新情報を取得: " . json_encode($orderDetails), 'INFO');
                            return $orderDetails;
                        } else {
                            // メモリ内データだけでチケット情報を構築
                            $this->logger->logMessage("メモリ内チケット情報で応答: " . json_encode($recentTicket), 'INFO');
                            return [
                                'square_order_id' => $recentTicket['square_order_id'],
                                'room_number' => $roomNumber,
                                'status' => $recentTicket['status'],
                                'total_amount' => 0, // メモリ内には金額情報がないため0
                                'created_at' => $recentTicket['created_at'],
                                'updated_at' => $recentTicket['created_at'],
                                'line_items' => [] // メモリ内には商品情報がないため空
                            ];
                        }
                    } else {
                        // 古いチケット情報は使用しない
                        $this->logger->logMessage("メモリ内のチケット情報が古いため使用しません: 経過時間={$timeDiff}秒", 'WARNING');
                    }
                }
                
                return false;
            }
            
            $this->logger->logMessage("searchOrdersByRoomから取得した注文数: " . count($orders), 'INFO');
            
            // 開いている注文がない場合はfalseを返す
            if (empty($orders)) {
                return false;
            }
            
            // メタデータで「is_room_ticket」が「true」の注文をフィルタリング
            foreach ($orders as $order) {
                try {
                    $this->logger->logMessage("注文詳細を取得: " . $order['square_order_id'], 'INFO');
                    $orderDetails = $this->orderService->getOrder($order['square_order_id']);
                    
                    if (!$orderDetails) {
                        $this->logger->logMessage("注文詳細が取得できませんでした: " . $order['square_order_id'], 'WARNING');
                        continue;
                    }
                    
                    $orderApi = $this->client->getOrdersApi();
                    $response = $orderApi->retrieveOrder($order['square_order_id']);
                    
                    if ($response->isSuccess()) {
                        $result = $response->getResult();
                        $orderData = $result->getOrder();
                        $metadata = $orderData->getMetadata();
                        
                        if (isset($metadata['is_room_ticket']) && $metadata['is_room_ticket'] === 'true') {
                            // 保留伝票の情報を返す
                            $ticket = [
                                'square_order_id' => $orderData->getId(),
                                'room_number' => $roomNumber,
                                'status' => $orderData->getState(),
                                'total_amount' => $this->utilityService->formatMoney($orderData->getTotalMoney()),
                                'created_at' => $orderData->getCreatedAt(),
                                'updated_at' => $orderData->getUpdatedAt(),
                                'line_items' => $this->utilityService->formatLineItems($orderData->getLineItems())
                            ];
                            $this->logger->logMessage("部屋 {$roomNumber} の有効なチケットを発見: " . $ticket['square_order_id'], 'INFO');
                            return $ticket;
                        }
                    } else {
                        $errors = $response->getErrors();
                        $this->logger->logMessage("注文詳細の取得中にエラー: " . json_encode($errors), 'ERROR');
                    }
                } catch (\Throwable $e) {
                    $this->logger->logMessage("注文処理中に例外発生: " . $e->getMessage(), 'ERROR');
                    // 次の注文を処理するために続行
                    continue;
                }
            }
            
            $this->logger->logMessage("部屋 {$roomNumber} に有効なチケットが見つかりませんでした", 'WARNING');
            return false;
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception in getRoomTicket: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        } catch (\Throwable $e) {
            $this->logger->logMessage("予期せぬエラー(getRoomTicket): " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("予期せぬエラー(getRoomTicket): " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        }
    }
    
    /**
     * 保留伝票に商品を追加
     * 
     * @param string $roomNumber 部屋番号
     * @param array $items 追加する商品の配列 [['square_item_id' => 'xxx', 'quantity' => 1, 'note' => '...'], ...]
     * @return array|false 成功時は更新された保留伝票情報、失敗時はfalse
     */
    public function addItemToRoomTicket($roomNumber, $items) {
        $this->logger->logMessage("addItemToRoomTicket 開始: roomNumber={$roomNumber}, items=" . 
                      (is_array($items) ? count($items) . "件" : "不正な形式"), 'INFO');
        
        try {
            // パラメータチェック
            if (empty($roomNumber)) {
                $this->logger->logMessage("部屋番号が指定されていません", 'ERROR');
                return false;
            }
            
            // itemsが文字列の場合はJSONデコード（配列全体が文字列として渡された場合）
            if (is_string($items)) {
                try {
                    $items = json_decode($items, true);
                    if (!is_array($items)) {
                        $this->logger->logMessage("商品データのJSONデコードに失敗しました: " . $items, 'ERROR');
                        return false;
                    }
                    $this->logger->logMessage("商品データ全体をJSONデコードしました: " . count($items) . "件", 'INFO');
                } catch (Exception $e) {
                    $this->logger->logMessage("商品データのJSONデコード中にエラー: " . $e->getMessage(), 'ERROR');
                    return false;
                }
            }
            
            // 配列でない場合はエラー
            if (!is_array($items)) {
                $this->logger->logMessage("商品データが配列ではありません: " . gettype($items), 'ERROR');
                return false;
            }
            
            // デバッグ: 項目ごとのデータ型を確認
            $this->logger->logMessage("商品データの型: " . json_encode(array_map(function($item) { 
                return is_array($item) ? 'array' : gettype($item); 
            }, $items)), 'INFO');
            
            // 正規化された商品アイテムの配列を作成
            $processedItems = [];
            
            foreach ($items as $index => $item) {
                // オリジナルのitemデータを保持
                $originalItem = $item;
                
                // 文字列の場合はデコード試行
                if (is_string($item)) {
                    try {
                        $decodedItem = json_decode($item, true);
                        
                        // JSONデコードが成功し、かつ配列である場合
                        if (is_array($decodedItem)) {
                            $item = $decodedItem;
                            
                            // square_item_idをロギング
                            if (isset($item['square_item_id'])) {
                                $this->logger->logMessage("アイテム[$index]をJSONデコードしました。square_item_id: " . $item['square_item_id'], 'INFO');
                            } else {
                                $this->logger->logMessage("アイテム[$index]をJSONデコードしましたが、square_item_idがありません", 'WARNING');
                            }
                        } else {
                            // デコードに失敗した場合は正規表現で抽出を試みる
                            if (preg_match('/square_item_id[\"\']?\s*:\s*[\"\']([^\"\']+)[\"\']/', $item, $matches)) {
                                $square_item_id = $matches[1];
                                $quantity = 1;
                                
                                // 数量も抽出を試みる
                                if (preg_match('/quantity[\"\']?\s*:\s*(\d+)/', $item, $qMatches)) {
                                    $quantity = (int)$qMatches[1];
                                }
                                
                                $item = [
                                    'square_item_id' => $square_item_id,
                                    'quantity' => $quantity,
                                    'note' => ''
                                ];
                                $this->logger->logMessage("アイテム[$index]を正規表現で抽出しました: square_item_id={$square_item_id}, quantity={$quantity}", 'INFO');
                            } else {
                                $this->logger->logMessage("アイテム[$index]の処理に失敗しました: " . $item, 'ERROR');
                                continue; // このアイテムはスキップ
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->logMessage("アイテム[$index]の処理中にエラー: " . $e->getMessage(), 'ERROR');
                        continue; // このアイテムはスキップ
                    }
                }
                
                // 配列でない場合はスキップ
                if (!is_array($item)) {
                    $this->logger->logMessage("アイテム[$index]が配列ではありません: " . gettype($item), 'ERROR');
                    continue;
                }
                
                // 必須フィールドのチェック - square_item_idまたはname+priceの組み合わせを許可
                $hasSquareId = isset($item['square_item_id']) && !empty($item['square_item_id']);
                $hasNameAndPrice = isset($item['name']) && !empty($item['name']) && isset($item['price']);
                
                if (!$hasSquareId && !$hasNameAndPrice) {
                    $this->logger->logMessage("アイテム[$index]に必要な識別情報がありません。square_item_idまたはname+priceの組み合わせが必要です", 'ERROR');
                    continue;
                }
                
                // 数量のチェック
                if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    $item['quantity'] = 1; // デフォルト値
                    $this->logger->logMessage("アイテム[$index]の数量が無効なため、デフォルト値(1)を使用します", 'WARNING');
                }
                
                // 備考のチェック
                if (!isset($item['note'])) {
                    $item['note'] = '';
                }
                
                // 処理済みアイテムとして追加（すべての情報を含める）
                $processedItem = [
                    'quantity' => (int)$item['quantity'],
                    'note' => $item['note']
                ];
                
                // square_item_idがある場合は追加
                if ($hasSquareId) {
                    $processedItem['square_item_id'] = $item['square_item_id'];
                }
                
                // nameとpriceがある場合は追加
                if ($hasNameAndPrice) {
                    $processedItem['name'] = $item['name'];
                    $processedItem['price'] = is_numeric($item['price']) ? floatval($item['price']) : 0;
                }
                
                // 文字列からデコードした場合、元の文字列にsquare_item_idが含まれているか確認
                if (!isset($processedItem['square_item_id']) && is_string($originalItem)) {
                    // 正規表現でsquare_item_idを抽出
                    if (preg_match('/square_item_id[\"\']?\s*:\s*[\"\']([^\"\']+)[\"\']/', $originalItem, $matches)) {
                        $processedItem['square_item_id'] = $matches[1];
                        $this->logger->logMessage("アイテム[$index]の元の文字列からsquare_item_idを抽出: " . $matches[1], 'INFO');
                    }
                }
                
                $processedItems[] = $processedItem;
            }
            
            // 処理可能なアイテムがない場合
            if (empty($processedItems)) {
                $this->logger->logMessage("処理可能な商品がありません", 'ERROR');
                return false;
            }
            
            $this->logger->logMessage("処理済み商品アイテム: " . json_encode($processedItems), 'INFO');
            
            // データ変換後にsquare_item_idが保持されているか確認
            foreach ($processedItems as $index => $item) {
                // 元のitemsからsquare_item_idを確認
                if (!isset($item['square_item_id']) && is_array($items[$index]) && isset($items[$index]['square_item_id'])) {
                    $processedItems[$index]['square_item_id'] = $items[$index]['square_item_id'];
                    $this->logger->logMessage("アイテム[$index]にsquare_item_idを復元しました: " . $items[$index]['square_item_id'], 'INFO');
                }
            }
            
            // 部屋の保留伝票を取得（リトライロジックを追加）
            $ticket = null;
            $maxRetries = 2; // 最大リトライ回数
            
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                if ($retry > 0) {
                    // リトライの場合は少し待機してから再試行
                    $waitTime = $retry * 1; // 1秒、2秒...と増加
                    $this->logger->logMessage("チケット取得リトライ #{$retry}: {$waitTime}秒待機", 'INFO');
                    sleep($waitTime);
                }
                
                $ticket = $this->getRoomTicket($roomNumber);
                
                if ($ticket) {
                    $this->logger->logMessage("チケット取得成功: " . $this->logger->formatArgs($ticket) . ($retry > 0 ? "（リトライ#{$retry}で成功）" : "（初回で成功）"), 'INFO');
                    break; // チケットが取得できたらループを抜ける
                } else if ($retry == $maxRetries) {
                    $this->logger->logMessage("すべてのリトライ({$maxRetries}回)失敗後もチケットが取得できませんでした", 'ERROR');
                }
            }
            
            // 保留伝票が存在しない場合は作成
            if (!$ticket) {
                $this->logger->logMessage("Room {$roomNumber} の保留伝票が存在しないため、新規作成します", 'INFO');
                $ticket = $this->createRoomTicket($roomNumber);
                
                if (!$ticket) {
                    $this->logger->logMessage("Failed to create room ticket for room {$roomNumber}", 'ERROR');
                    return false;
                }
                
                $this->logger->logMessage("Room {$roomNumber} の保留伝票を作成しました: " . $this->logger->formatArgs($ticket), 'INFO');
                
                // 作成したチケットのSquare側の情報を取得
                if (!isset($ticket['square_order_id'])) {
                    $this->logger->logMessage("作成したチケットにSquare注文IDがありません", 'ERROR');
                    return false;
                }
                
                // Square APIから情報を取得して追加
                $squareTicket = $this->orderService->getOrder($ticket['square_order_id']);
                if ($squareTicket) {
                    $ticket['square_data'] = $squareTicket;
                } else {
                    $this->logger->logMessage("Square APIから注文情報を取得できませんでした: " . $ticket['square_order_id'], 'ERROR');
                    // エラーログを記録するが処理は続行する
                }
            }
            
            // 保留伝票が存在する場合は、既存の注文に商品を追加
            $orderApi = $this->client->getOrdersApi();
            $orderId = $ticket['square_order_id'];
            $this->logger->logMessage("更新対象の注文ID: {$orderId}", 'INFO');
            
            // 注文バージョンを取得
            try {
                $response = $orderApi->retrieveOrder($orderId);
                
                if (!$response->isSuccess()) {
                    $errors = $response->getErrors();
                    $errorMsg = "Failed to retrieve order for update: " . json_encode($errors);
                    $this->logger->logMessage($errorMsg, 'ERROR');
                    Utils::log($errorMsg, 'ERROR', 'SquareService');
                    
                    // エラー詳細のログ
                    foreach ($errors as $error) {
                        $this->logger->logMessage("Square API Error: Category={$error->getCategory()}, Code={$error->getCode()}, Detail={$error->getDetail()}", 'ERROR');
                    }
                    
                    return false;
                }
                
                $result = $response->getResult();
                $orderData = $result->getOrder();
                
                if (!$orderData) {
                    $this->logger->logMessage("注文データがnullです。SquareのAPIは成功しましたが、データがありません。", 'ERROR');
                    return false;
                }
                
                $version = $orderData->getVersion();
                if ($version === null) {
                    $this->logger->logMessage("注文バージョンがnullです: orderId={$orderId}", 'ERROR');
                    return false;
                }
                
                $this->logger->logMessage("注文バージョン: {$version}", 'INFO');
                
                // 既存のライン項目を取得して保持
                $existingLineItems = $orderData->getLineItems() ?? [];
                $this->logger->logMessage("既存のライン項目数: " . count($existingLineItems), 'INFO');
                
                // 商品ラインアイテムを作成
                $lineItems = [];
                
                // 既存のライン項目を保持
                foreach ($existingLineItems as $existingItem) {
                    $lineItems[] = $existingItem;
                }
                
                // 新しいライン項目を追加
                foreach ($processedItems as $item) {
                    try {
                        if (!isset($item['square_item_id']) || empty($item['square_item_id'])) {
                            // カタログIDがない場合は名前と価格が必要
                            if (empty($item['name'])) {
                                $this->logger->logMessage("商品IDと名前の両方がありません: " . $this->logger->formatArgs($item), 'ERROR');
                                continue;
                            }
                        }
                        
                        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                        if ($quantity <= 0) {
                            $this->logger->logMessage("無効な数量: {$quantity}", 'ERROR');
                            $quantity = 1;
                        }
                        
                        $this->logger->logMessage("新しいOrderLineItemを作成開始", 'INFO');
                        
                        try {
                            $lineItem = new OrderLineItem($quantity);
                            $useNameAndPrice = false;
                            
                            // カタログオブジェクトIDがある場合はそれを使用
                            if (!empty($item['square_item_id'])) {
                                try {
                                    // カタログIDを使ってラインアイテムを作成
                                    $lineItem->setCatalogObjectId($item['square_item_id']);
                                    $this->logger->logMessage("カタログIDでラインアイテム作成: {$item['square_item_id']}", 'INFO');
                                } catch (Exception $e) {
                                    // カタログIDでエラーが発生した場合、名前と価格を使用するフォールバック
                                    $this->logger->logMessage("カタログID使用中にエラー発生: {$e->getMessage()}, 名前と価格を使用します", 'WARNING');
                                    $useNameAndPrice = true;
                                }
                            } else {
                                // カタログIDがない場合は名前と価格を直接指定
                                $useNameAndPrice = true;
                            }
                            
                            // 名前と価格で指定（カタログIDがない場合またはエラー発生時）
                            if ($useNameAndPrice) {
                                if (!empty($item['name'])) {
                                    $lineItem->setName($item['name']);
                                    
                                    // 価格情報を設定
                                    if (isset($item['price']) && is_numeric($item['price'])) {
                                        $money = new Money();
                                        // 価格はセント単位で指定（100円→10000）
                                        $money->setAmount((int)($item['price'] * 100));
                                        $money->setCurrency('JPY');
                                        
                                        $lineItem->setBasePriceMoney($money);
                                        $this->logger->logMessage("名前と価格でラインアイテム作成: 名前={$item['name']}, 価格={$item['price']}", 'INFO');
                                    } else {
                                        $this->logger->logMessage("商品に価格が指定されていないため、ゼロ価格で設定します", 'WARNING');
                                        $money = new Money();
                                        $money->setAmount(0);
                                        $money->setCurrency('JPY');
                                        $lineItem->setBasePriceMoney($money);
                                    }
                                } else {
                                    // 名前も設定できない場合は例外をスロー
                                    throw new Exception("カタログIDも商品名も指定されていません");
                                }
                            }
                            
                            if (!empty($item['note'])) {
                                $lineItem->setNote($item['note']);
                            }
                            
                            $lineItems[] = $lineItem;
                            $this->logger->logMessage("OrderLineItemを作成完了: " . (isset($item['square_item_id']) ? "ID={$item['square_item_id']}" : "名前={$item['name']}") . ", quantity={$quantity}", 'INFO');
                        } catch (Exception $e) {
                            $this->logger->logMessage("OrderLineItem作成中に例外発生: " . $e->getMessage() . "\nスタックトレース:\n" . $e->getTraceAsString(), 'ERROR');
                            Utils::log("OrderLineItem作成中に例外発生: " . $e->getMessage(), 'ERROR', 'SquareService');
                            
                            // 商品名と価格によるフォールバックをまだ試していない場合は試行
                            if (!empty($item['name']) && !isset($tried_fallback)) {
                                $tried_fallback = true;
                                $this->logger->logMessage("代替手段として商品名と価格を使用します: {$item['name']}", 'INFO');
                                
                                $lineItem = new OrderLineItem($quantity);
                                $lineItem->setName($item['name']);
                                
                                // 価格情報を設定
                                if (!empty($item['price']) && is_numeric($item['price'])) {
                                    $money = new Money();
                                    $money->setAmount((int)($item['price'] * 100));
                                    $money->setCurrency('JPY');
                                    
                                    $lineItem->setBasePriceMoney($money);
                                }
                                
                                if (!empty($item['note'])) {
                                    $lineItem->setNote($item['note']);
                                }
                                
                                $lineItems[] = $lineItem;
                                $this->logger->logMessage("フォールバック方法でOrderLineItemを作成完了", 'INFO');
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->logMessage("商品アイテム処理中に例外発生: " . $e->getMessage() . "\nスタックトレース:\n" . $e->getTraceAsString(), 'ERROR');
                        Utils::log("商品アイテム処理中に例外発生: " . $e->getMessage(), 'ERROR', 'SquareService');
                        continue;
                    }
                }
                
                // 処理可能な項目がない場合
                if (count($lineItems) === 0) {
                    $this->logger->logMessage("追加された新しい商品項目がありません", 'ERROR');
                    return false;
                }
                
                // 注文更新リクエストの作成
                try {
                    $updateOrderRequest = new UpdateOrderRequest();
                    $updateOrderRequest->setOrder($orderData);
                    $updateOrderRequest->setIdempotencyKey(uniqid('update_', true));
                    
                    $this->logger->logMessage("UpdateOrderRequestオブジェクト作成完了: idempotencyKey=" . $updateOrderRequest->getIdempotencyKey(), 'INFO');
                    
                    // Square APIで注文更新
                    $this->logger->logMessage("Square APIで注文更新開始: orderId={$orderId}", 'INFO');
                    $response = $orderApi->updateOrder($orderId, $updateOrderRequest);
                    
                    if ($response->isSuccess()) {
                        $this->logger->logMessage("Square注文更新成功: orderId={$orderId}", 'INFO');
                        
                        // 更新された注文情報を取得
                        $updatedOrder = $response->getResult()->getOrder();
                        
                        // 成功時は更新された注文情報を返す
                        $result = [
                            'square_order_id' => $updatedOrder->getId(),
                            'status' => $updatedOrder->getState(),
                            'updated_at' => $updatedOrder->getUpdatedAt()
                        ];
                        
                        $this->logger->logMessage("Room ticket updated successfully: " . json_encode($result), 'INFO');
                        return $result;
                    } else {
                        $errors = $response->getErrors();
                        $errorDetails = [];
                        
                        foreach ($errors as $error) {
                            $errorDetails[] = [
                                'category' => $error->getCategory(),
                                'code' => $error->getCode(),
                                'detail' => $error->getDetail()
                            ];
                        }
                        
                        $this->logger->logMessage("Square API注文更新エラー: " . json_encode($errorDetails), 'ERROR');
                        Utils::log("Square API注文更新エラー: " . json_encode($errorDetails), 'ERROR', 'SquareService');
                        return false;
                    }
                } catch (Exception $e) {
                    $this->logger->logMessage("注文更新リクエスト作成またはAPI呼び出し中に例外発生: " . $e->getMessage() . "\nスタックトレース:\n" . $e->getTraceAsString(), 'ERROR');
                    Utils::log("注文更新リクエスト中に例外発生: " . $e->getMessage(), 'ERROR', 'SquareService');
                    return false;
                }
            } catch (Exception $retrieveEx) {
                $this->logger->logMessage("注文取得エラー: " . $retrieveEx->getMessage() . "\n" . $retrieveEx->getTraceAsString(), 'ERROR');
                Utils::log("注文取得エラー: " . $retrieveEx->getMessage(), 'ERROR', 'SquareService');
                throw $retrieveEx;
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API例外: " . $e->getMessage() . "\nスタックトレース:\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("Square API例外: " . $e->getMessage(), 'ERROR', 'SquareService');
            
            // API ResponseBodyを取得できれば詳細ログに出力
            if ($e->getResponseBody()) {
                $this->logger->logMessage("Square API詳細レスポンス: " . json_encode($e->getResponseBody()), 'ERROR');
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->logMessage("予期せぬ例外: " . $e->getMessage() . "\nスタックトレース:\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("予期せぬ例外: " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        }
    }
} 