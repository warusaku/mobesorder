<?php
/**
 * Square API連携サービス 注文管理クラス
 * Version: 1.0.0
 * Description: 注文の作成、取得、検索を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';
require_once __DIR__ . '/SquareService_Utility.php';

use Square\Exceptions\ApiException;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\Money;

class SquareService_opt_Order extends SquareService_Base {
    
    private $utilityService;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct();
        $this->utilityService = new SquareService_Utility();
    }
    
    /**
     * 注文を作成
     * 
     * @param string $roomNumber 部屋番号
     * @param array $items 注文商品の配列 [['square_item_id' => 'xxx', 'quantity' => 1, 'note' => '...'], ...]
     * @param string $guestName ゲスト名（オプション）
     * @param string $note 注文全体の備考
     * @return array|false 成功時は注文情報、失敗時はfalse
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '') {
        $this->logger->logMessage("createOrder 開始: roomNumber={$roomNumber}, guestName={$guestName}, items=" . 
                      (is_array($items) ? count($items) . "件" : "不正な形式"), 'INFO');
        
        try {
            $orderApi = $this->client->getOrdersApi();
            $lineItems = [];
            
            // 処理前にアイテムの形式を検証
            if (!is_array($items)) {
                $this->logger->logMessage("items パラメータが配列ではありません: " . gettype($items), 'ERROR');
                return false;
            }
            
            // 商品項目の処理時にエラーが発生した場合のログを強化
            $this->logger->logMessage("商品項目の処理開始: " . count($items) . "件", 'INFO');
            
            foreach ($items as $index => $item) {
                try {
                    $lineItem = new OrderLineItem($item['quantity']);
                    
                    // カタログオブジェクトIDを使わず、常に名前と価格で商品を登録
                    if (!empty($item['name'])) {
                        $lineItem->setName($item['name']);
                        
                        // 価格情報を設定
                        if (!empty($item['price']) && is_numeric($item['price'])) {
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
                        // 名前が設定できない場合は商品 + インデックスで代用
                        $this->logger->logMessage("商品名が不足しているため代替名を使用: 商品" . ($index+1), 'WARNING');
                        $lineItem->setName("商品" . ($index+1));
                        
                        // 価格情報を設定
                        if (!empty($item['price']) && is_numeric($item['price'])) {
                            $money = new Money();
                            $money->setAmount((int)($item['price'] * 100));
                            $money->setCurrency('JPY');
                            $lineItem->setBasePriceMoney($money);
                        } else {
                            $money = new Money();
                            $money->setAmount(0);
                            $money->setCurrency('JPY');
                            $lineItem->setBasePriceMoney($money);
                        }
                    }
                    
                    if (!empty($item['note'])) {
                        $lineItem->setNote($item['note']);
                    }
                    
                    $lineItems[] = $lineItem;
                } catch (\Throwable $itemEx) {
                    $this->logger->logMessage("商品項目 $index の処理中にエラー: " . $itemEx->getMessage(), 'ERROR');
                    // 項目をスキップして処理を継続
                    continue;
                }
            }
            
            // 有効なライン項目がない場合はエラー
            if (empty($lineItems)) {
                $this->logger->logMessage("有効な商品項目がありません", 'ERROR');
                return false;
            }
            
            $order = new Order($this->locationId);
            $order->setLineItems($lineItems);
            $order->setState('OPEN');
            $order->setReferenceId($roomNumber);
            
            // メタデータを追加して検索性を向上
            $metadata = [
                'room_number' => $roomNumber,
                'guest_name' => $guestName ?? '',
                'order_source' => 'mobile_order'
            ];
            $order->setMetadata($metadata);
            
            if (!empty($note)) {
                $order->setNote($note);
            }
            
            $this->logger->logMessage("注文の作成準備完了: lineItems=" . count($lineItems) . "件", 'INFO');
            
            $body = new CreateOrderRequest();
            $body->setOrder($order);
            $body->setIdempotencyKey(uniqid('order_', true));
            
            $response = $orderApi->createOrder($body);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $orderData = $result->getOrder();
                
                $this->logger->logMessage("Square Order Created: " . $orderData->getId(), 'INFO');
                
                return [
                    'square_order_id' => $orderData->getId(),
                    'total_amount' => $this->utilityService->formatMoney($orderData->getTotalMoney()),
                    'status' => $orderData->getState()
                ];
            } else {
                $errors = $response->getErrors();
                $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                
                // 詳細なエラーログを出力
                foreach ($errors as $error) {
                    $this->logger->logMessage("Square API Error 詳細: カテゴリ=" . $error->getCategory() . 
                               ", コード=" . $error->getCode() . 
                               ", 詳細=" . $error->getDetail() .
                               (method_exists($error, 'getField') ? ", フィールド=" . $error->getField() : ""), 
                               'ERROR');
                }
                
                Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                return false;
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception in createOrder: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        } catch (\Throwable $e) {
            $this->logger->logMessage("予期せぬエラー(createOrder): " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("予期せぬエラー(createOrder): " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        }
    }
    
    /**
     * 注文情報を取得
     * 
     * @param string $orderId Square注文ID
     * @return array|false 成功時は注文情報、失敗時はfalse
     */
    public function getOrder($orderId) {
        try {
            $orderApi = $this->client->getOrdersApi();
            $response = $orderApi->retrieveOrder($orderId);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $orderData = $result->getOrder();
                
                return [
                    'square_order_id' => $orderData->getId(),
                    'total_amount' => $this->utilityService->formatMoney($orderData->getTotalMoney()),
                    'status' => $orderData->getState(),
                    'created_at' => $orderData->getCreatedAt(),
                    'updated_at' => $orderData->getUpdatedAt()
                ];
            } else {
                $errors = $response->getErrors();
                $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                return false;
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return false;
        }
    }
    
    /**
     * 部屋番号に関連する注文を検索
     * 
     * @param string $roomNumber 部屋番号
     * @return array 注文情報の配列
     */
    public function searchOrdersByRoom($roomNumber) {
        try {
            $this->logger->logMessage("searchOrdersByRoom開始: roomNumber={$roomNumber}", 'INFO');
            $orderApi = $this->client->getOrdersApi();
            
            // SearchOrdersRequestオブジェクトを作成
            $request = new \Square\Models\SearchOrdersRequest();
            
            // ロケーションIDを設定
            $request->setLocationIds([$this->locationId]);
            
            // クエリフィルターを作成
            $filter = new \Square\Models\SearchOrdersFilter();
            
            // ソースフィルターを設定
            $sourceFilter = new \Square\Models\SearchOrdersSourceFilter();
            $sourceFilter->setSourceNames(['mobile_order']);
            $filter->setSourceFilter($sourceFilter);
            
            // 状態フィルターを設定 - バージョン差異に対応
            $stateFilter = null;
            try {
                $this->logger->logMessage("StateFilter作成試行(新メソッド)", 'INFO');
                // 新しいバージョン方式を試す
                $stateFilter = new \Square\Models\SearchOrdersStateFilter(['OPEN']);
                $this->logger->logMessage("StateFilter作成成功(新メソッド)", 'INFO');
            } catch (\Throwable $e) {
                $this->logger->logMessage("StateFilter新メソッド失敗: " . $e->getMessage() . ", 旧メソッドに切り替え", 'WARNING');
                // 古いバージョン方式にフォールバック
                $stateFilter = new \Square\Models\SearchOrdersStateFilter();
                $stateFilter->setStates(['OPEN']);
                $this->logger->logMessage("StateFilter作成成功(旧メソッド)", 'INFO');
            }
            
            $filter->setStateFilter($stateFilter);
            
            // クエリオブジェクトを作成
            $query = new \Square\Models\SearchOrdersQuery();
            $query->setFilter($filter);
            
            // クエリをリクエストに設定
            $request->setQuery($query);
            
            $this->logger->logMessage("検索リクエスト作成完了: room_number={$roomNumber}", 'INFO');
            
            $response = $orderApi->searchOrders($request);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $orders = $result->getOrders() ?? [];
                
                // メタデータフィルターが使えない場合は、結果をフィルタリング
                $filteredOrders = [];
                foreach ($orders as $order) {
                    $metadata = $order->getMetadata();
                    if ($metadata && isset($metadata['room_number']) && $metadata['room_number'] === $roomNumber) {
                        $filteredOrders[] = $order;
                    } elseif ($order->getReferenceId() === $roomNumber) {
                        $filteredOrders[] = $order;
                    }
                }
                $orders = $filteredOrders;
                
                $formattedOrders = [];
                foreach ($orders as $order) {
                    $formattedOrders[] = [
                        'square_order_id' => $order->getId(),
                        'total_amount' => $this->utilityService->formatMoney($order->getTotalMoney()),
                        'status' => $order->getState(),
                        'created_at' => $order->getCreatedAt(),
                        'updated_at' => $order->getUpdatedAt()
                    ];
                }
                
                $this->logger->logMessage("検索結果: " . count($formattedOrders) . "件の注文を取得", 'INFO');
                return $formattedOrders;
            } else {
                $errors = $response->getErrors();
                $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                return [];
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return [];
        } catch (\Throwable $e) {
            $this->logger->logMessage("予期せぬエラー(searchOrdersByRoom): " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("予期せぬエラー(searchOrdersByRoom): " . $e->getMessage(), 'ERROR', 'SquareService');
            return [];
        }
    }
} 