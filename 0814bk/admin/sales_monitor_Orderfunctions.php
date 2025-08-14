<?php
/**
 * 注文操作関数クラス
 * バージョン: 1.0.0
 * ファイル説明: sales_monitor.phpの注文操作関連機能を分離したクラス
 */

class SalesMonitorOrderFunctions {
    
    private $db;
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB 規約
    
    /**
     * コンストラクタ
     */
    public function __construct($db) {
        $this->db = $db;
        self::initLogFile();
    }
    
    /**
     * ログファイルの初期化
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/sales_monitor.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            $content = file_get_contents(self::$logFile);
            // 最新20%を保持
            $keepSize = intval(self::$maxLogSize * 0.2);
            $content = substr($content, -$keepSize);
            file_put_contents(self::$logFile, $content);
        }
    }
    
    /**
     * ログメッセージを記録
     */
    public static function log($message, $level = 'INFO') {
        self::initLogFile();
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * 販売データを取得
     */
    public function fetchSalesData() {
        $salesData = [
            'orders' => [],
            'room_tickets' => [],
            'room_orders' => [],
            'room_totals' => [],
            'order_details' => [],
            'total_amount' => 0,
            'order_count' => 0,
            'active_rooms' => 0,
            'room_session_ids' => []
        ];
        
        $dataErrors = [];
        
        try {
            self::log("注文データ取得開始", "INFO");
            
            // オーダー情報取得
            $salesData = $this->fetchOrders($salesData, $dataErrors);
            
            // 注文詳細情報取得
            $salesData = $this->fetchOrderDetails($salesData, $dataErrors);
            
            // アクティブな部屋情報取得
            $salesData = $this->fetchActiveRooms($salesData, $dataErrors);
            
        } catch (Exception $e) {
            self::log("データ取得全体でのエラー: " . $e->getMessage(), "ERROR");
            $dataErrors[] = "データ取得中に致命的なエラーが発生しました: " . $e->getMessage();
        }
        
        return [
            'salesData' => $salesData,
            'dataErrors' => $dataErrors
        ];
    }
    
    /**
     * 注文データを取得
     */
    private function fetchOrders($salesData, &$dataErrors) {
        $query = "
            SELECT o.id, o.square_order_id, o.room_number, o.guest_name, o.order_status, 
                   o.total_amount, o.memo AS memo, o.order_datetime, o.checkout_datetime, 
                   o.order_session_id, o.created_at, o.updated_at
            FROM orders o
            WHERE o.order_status != 'CANCELED'
            ORDER BY o.room_number ASC, o.order_datetime DESC
        ";
        
        self::log("実行クエリ: " . $query, "DEBUG");
        
        try {
            $orders = $this->db->select($query);
            
            if ($orders) {
                self::log("注文データ取得成功: " . count($orders) . "件", "INFO");
                $salesData['orders'] = $orders;
                $salesData['order_count'] = count($orders);
                
                // 部屋ごとのデータをグループ化
                $roomOrders = [];
                $roomTotals = [];
                $roomSessionIds = [];
                $orderIds = [];
                
                foreach ($orders as $order) {
                    $roomNumber = $order['room_number'] ?? 'unknown';
                    $orderId = $order['id'] ?? 0;
                    
                    if (empty($roomNumber) || empty($orderId)) {
                        self::log("不正なオーダーデータをスキップ: " . json_encode($order), "WARNING");
                        continue;
                    }
                    
                    $orderIds[] = $orderId;
                    
                    if (!isset($roomOrders[$roomNumber])) {
                        $roomOrders[$roomNumber] = [];
                        $roomTotals[$roomNumber] = 0;
                    }
                    
                    $roomOrders[$roomNumber][] = $order;
                    $roomTotals[$roomNumber] += (float)($order['total_amount'] ?? 0);
                    
                    if (!isset($roomSessionIds[$roomNumber])) {
                        $roomSessionIds[$roomNumber] = $order['order_session_id'] ?? '';
                    }
                    
                    $salesData['total_amount'] += (float)($order['total_amount'] ?? 0);
                }
                
                $salesData['room_orders'] = $roomOrders;
                $salesData['room_totals'] = $roomTotals;
                $salesData['room_session_ids'] = $roomSessionIds;
                $salesData['order_ids'] = $orderIds;
                
                self::log("部屋ごとのデータグループ化完了: " . count($roomOrders) . "部屋", "INFO");
            } else {
                self::log("注文データが見つかりませんでした", "WARNING");
                $dataErrors[] = "注文データが見つかりませんでした。";
            }
        } catch (Exception $e) {
            self::log("注文データ取得エラー: " . $e->getMessage(), "ERROR");
            $dataErrors[] = "注文データの取得中にエラーが発生しました: " . $e->getMessage();
        }
        
        return $salesData;
    }
    
    /**
     * 注文詳細データを取得
     */
    private function fetchOrderDetails($salesData, &$dataErrors) {
        $orderIds = $salesData['order_ids'] ?? [];
        
        if (empty($orderIds)) {
            return $salesData;
        }
        
        try {
            self::log("注文詳細データ取得開始: オーダーID " . count($orderIds) . "件", "INFO");
            
            $orderIdsString = implode(',', array_map('intval', $orderIds));
            
            $query = "
                SELECT id, order_id, square_item_id, product_name, unit_price, quantity, subtotal, note 
                FROM order_details 
                WHERE order_id IN ($orderIdsString)
                ORDER BY order_id, id ASC
            ";
            
            self::log("実行クエリ: " . $query, "DEBUG");
            
            $orderDetailsStmt = $this->db->getConnection()->prepare($query);
            $orderDetailsStmt->execute();
            $orderDetails = $orderDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            self::log("注文詳細データ取得成功: " . count($orderDetails) . "件", "INFO");
            
            // 明細情報をグループ化
            $detailsByOrderId = [];
            foreach ($orderDetails as $detail) {
                $orderId = $detail['order_id'];
                
                // 単価が0の場合、productsテーブルから価格を取得
                if (empty($detail['unit_price']) || $detail['unit_price'] == 0) {
                    if (!empty($detail['square_item_id'])) {
                        $productStmt = $this->db->getConnection()->prepare("
                            SELECT price FROM products 
                            WHERE square_item_id = :square_item_id LIMIT 1
                        ");
                        $productStmt->execute(['square_item_id' => $detail['square_item_id']]);
                        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($product && !empty($product['price'])) {
                            $detail['unit_price'] = $product['price'];
                            $detail['subtotal'] = $detail['unit_price'] * $detail['quantity'];
                        }
                    }
                }
                
                // 小計が0の場合、再計算
                if (empty($detail['subtotal']) || $detail['subtotal'] == 0) {
                    $detail['subtotal'] = $detail['unit_price'] * $detail['quantity'];
                }
                
                if (!isset($detailsByOrderId[$orderId])) {
                    $detailsByOrderId[$orderId] = [];
                }
                $detailsByOrderId[$orderId][] = $detail;
            }
            
            $salesData['order_details'] = $detailsByOrderId;
            
        } catch (Exception $e) {
            self::log("注文詳細データ全体の処理エラー: " . $e->getMessage(), "ERROR");
            $dataErrors[] = "注文詳細データの処理中にエラーが発生しました: " . $e->getMessage();
        }
        
        return $salesData;
    }
    
    /**
     * アクティブな部屋情報を取得
     */
    private function fetchActiveRooms($salesData, &$dataErrors) {
        try {
            self::log("アクティブな部屋情報取得開始", "INFO");
            
            $query = "
                SELECT l.id, l.line_user_id, l.room_number, l.user_name, l.check_in_date, l.check_out_date, 
                       l.is_active, l.created_at, l.updated_at, r.description
                FROM line_room_links l
                JOIN roomdatasettings r ON l.room_number = r.room_number
                WHERE l.is_active = 1
                ORDER BY l.room_number ASC
            ";
            
            self::log("アクティブな部屋情報クエリ: " . $query, "DEBUG");
            
            $roomLinks = $this->db->select($query);
            
            if ($roomLinks) {
                self::log("アクティブな部屋情報取得成功: " . count($roomLinks) . "件", "INFO");
                $salesData['room_tickets'] = $roomLinks;
                $salesData['active_rooms'] = count($roomLinks);
                
                // アクティブ部屋だけに限定
                $activeRoomNumbers = array_column($roomLinks, 'room_number');
                if (!empty($salesData['room_orders'])) {
                    foreach ($salesData['room_orders'] as $rNum => $dummy) {
                        if (!in_array($rNum, $activeRoomNumbers, true)) {
                            unset($salesData['room_orders'][$rNum]);
                            unset($salesData['room_totals'][$rNum]);
                            unset($salesData['room_session_ids'][$rNum]);
                        }
                    }
                }
            } else {
                self::log("アクティブな部屋情報が見つかりませんでした", "INFO");
                $salesData['room_tickets'] = [];
                $salesData['active_rooms'] = 0;
            }
        } catch (Exception $e) {
            self::log("部屋情報取得エラー: " . $e->getMessage(), "ERROR");
            $dataErrors[] = "部屋情報の取得中にエラーが発生しました: " . $e->getMessage();
        }
        
        return $salesData;
    }
    
    /**
     * 新規注文をチェック
     */
    public function checkNewOrders($salesData, $webhookManager = null) {
        try {
            $lastOrderIdFile = __DIR__ . '/adminpagesetting/last_order_id.txt';
            $lastOrderId = 0;
            
            if (file_exists($lastOrderIdFile)) {
                $lastOrderId = (int)trim(file_get_contents($lastOrderIdFile));
            }
            
            if (!empty($salesData['orders'])) {
                $latestOrder = $salesData['orders'][0];
                $currentOrderId = (int)$latestOrder['id'];
                
                if ($currentOrderId > $lastOrderId && $lastOrderId > 0) {
                    self::log("新規注文を検出しました: ID {$currentOrderId}、前回ID {$lastOrderId}", "INFO");
                    
                    // webhookManager が渡されていれば通知
                    if ($webhookManager) {
                        try {
                            $orderProducts = $this->getOrderProducts($currentOrderId);
                            $latestOrder['products'] = $orderProducts;
                            
                            $result = $webhookManager->sendOrderNotification($latestOrder);
                            
                            if ($result['success']) {
                                self::log("Webhook通知を送信しました: " . json_encode($result), "INFO");
                            } else {
                                self::log("Webhook通知の送信に失敗しました: " . json_encode($result), "ERROR");
                            }
                        } catch (Exception $e) {
                            self::log("商品情報取得中にエラー: " . $e->getMessage(), "ERROR");
                        }
                    }
                }
                
                file_put_contents($lastOrderIdFile, $currentOrderId);
            }
        } catch (Exception $e) {
            self::log("新規注文チェック中にエラーが発生しました: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * 注文の商品情報を取得
     */
    private function getOrderProducts($orderId) {
        $products = [];
        
        try {
            $checkTableSql = "SHOW TABLES LIKE 'order_details'";
            $tableCheck = $this->db->select($checkTableSql);
            
            if ($tableCheck && count($tableCheck) > 0) {
                $sql = "SELECT * FROM order_details WHERE order_id = ?";
                $products = $this->db->select($sql, [$orderId]);
            }
        } catch (Exception $e) {
            self::log("商品情報取得エラー: " . $e->getMessage(), "ERROR");
        }
        
        return $products;
    }
}