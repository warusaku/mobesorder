<?php
/**
 * カタログ商品モード用注文サービスクラス
 * バージョン: 1.0.0
 * ファイル説明: カタログ商品モードでの注文処理を専門に扱うクラス
 */

require_once __DIR__ . '/OrderServiceInterface.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/SquareService.php';
require_once __DIR__ . '/ProductService.php';
require_once __DIR__ . '/LineService.php';
require_once __DIR__ . '/OrderWebhook.php';

class OrderService_Catalog implements OrderServiceInterface {
    private $db;
    private $squareService;
    private $lineService;
    private $orderWebhook;
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB 規約
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        self::logMessage("OrderService_Catalog::__construct - カタログ商品モード初期化開始", 'INFO');
        
        $this->db = Database::getInstance();
        $this->squareService = new SquareService();
        $this->lineService = new LineService();
        $this->orderWebhook = new OrderWebhook();
        
        self::logMessage("OrderService_Catalog::__construct - カタログ商品モード初期化完了", 'INFO');
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
        
        self::$logFile = $logDir . '/OrderService_Catalog.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成\n";
            file_put_contents(self::$logFile, $message);
            return;
        }
        
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            $keep = intval(self::$maxLogSize * 0.2);
            $content = file_get_contents(self::$logFile);
            $content = substr($content, -$keep);
            $rotateMsg = "[".date('Y-m-d H:i:s')."] [INFO] log rotated\n";
            file_put_contents(self::$logFile, $rotateMsg . $content);
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 引数の内容を文字列化する
     */
    private static function formatArgs($args) {
        if (is_array($args)) {
            $result = [];
            foreach ($args as $key => $value) {
                if (is_array($value)) {
                    if (count($value) > 5) {
                        $result[$key] = '[配列: ' . count($value) . '件]';
                    } else {
                        $result[$key] = self::formatArgs($value);
                    }
                } elseif (is_object($value)) {
                    $result[$key] = '[オブジェクト: ' . get_class($value) . ']';
                } else {
                    $result[$key] = $value;
                }
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } elseif (is_object($args)) {
            return '[オブジェクト: ' . get_class($args) . ']';
        } else {
            return json_encode($args, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * モード名を取得
     */
    public function getModeName() {
        return 'カタログ商品モード';
    }
    
    /**
     * 注文を作成（カタログ商品モード）
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '', $lineUserId = '') {
        $argsLog = [
            'roomNumber' => $roomNumber,
            'itemsCount' => is_array($items) ? count($items) : 'N/A',
            'guestName' => $guestName,
            'mode' => 'catalog'
        ];
        self::logMessage("createOrder[Catalog] 開始 - 引数: " . self::formatArgs($argsLog), 'INFO');
        
        try {
            // 商品データの正規化
            $normalizedItems = $this->normalizeItems($items);
            if (empty($normalizedItems)) {
                self::logMessage("有効な商品がありません", 'ERROR');
                return false;
            }
            
            // 商品情報の取得と金額計算
            $orderData = $this->prepareOrderData($normalizedItems);
            $subtotalAmount = $orderData['subtotal'];
            $taxRate = 0.1;
            $tax = round($subtotalAmount * $taxRate);
            $totalAmount = $subtotalAmount + $tax;
            
            self::logMessage("金額計算完了: 小計={$subtotalAmount}, 税額={$tax}, 合計={$totalAmount}", 'INFO');
            
            // セッション取得または作成
            $sessionData = $this->getOrCreateSession($roomNumber);
            $sessionId = $sessionData['id'];
            $squareItemId = $sessionData['square_item_id'] ?? null;
            
            // セッション累計（税抜）を計算
            $sessionSubtotal = $this->calculateSessionSubtotal($sessionId);
            $newSessionSubtotal = $sessionSubtotal + $subtotalAmount;
            
            self::logMessage("セッション累計: 既存={$sessionSubtotal}, 新規={$newSessionSubtotal}", 'INFO');
            
            // Square商品作成/更新
            $squareItemId = $this->squareService->createOrUpdateSessionProduct(
                $sessionId,
                $roomNumber,
                $newSessionSubtotal,
                $squareItemId
            );
            
            if (!$squareItemId) {
                self::logMessage("Square商品の作成/更新に失敗しました", 'ERROR');
                return false;
            }
            
            // セッションにSquare Item IDを保存
            if ($squareItemId !== ($sessionData['square_item_id'] ?? null)) {
                $this->db->execute(
                    "UPDATE order_sessions SET square_item_id = ? WHERE id = ?",
                    [$squareItemId, $sessionId]
                );
            }
            
            // データベースに注文を保存
            $orderId = $this->saveOrderToDatabase(
                $roomNumber,
                $guestName,
                $lineUserId,
                $subtotalAmount,
                $note,
                $sessionId,
                $orderData['items']
            );
            
            if (!$orderId) {
                self::logMessage("注文の保存に失敗しました", 'ERROR');
                return false;
            }
            
            // LINE通知送信
            $this->sendLineNotification($roomNumber, $orderId, $subtotalAmount);
            
            // 成功結果
            $result = [
                'id' => $orderId,
                'square_order_id' => null, // カタログ商品モードではSquare注文IDは作成しない
                'room_number' => $roomNumber,
                'total_amount' => $subtotalAmount,
                'status' => 'OPEN',
                'created_at' => date('Y-m-d H:i:s'),
                'order_session_id' => $sessionId,
                'square_item_id' => $squareItemId,
                'note' => $note
            ];
            
            // Webhookキュー
            try {
                $this->orderWebhook->queueOrderWebhook($result, $orderData['items']);
            } catch (Exception $whEx) {
                self::logMessage('Webhookキューで例外: ' . $whEx->getMessage(), 'ERROR');
            }
            
            self::logMessage("createOrder[Catalog] 完了 - OrderID: {$orderId}", 'INFO');
            return $result;
            
        } catch (Exception $e) {
            self::logMessage("createOrder[Catalog] エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 商品データの正規化
     */
    private function normalizeItems($items) {
        if (is_string($items)) {
            try {
                $items = json_decode($items, true);
            } catch (Exception $e) {
                self::logMessage("商品データのデコードに失敗: " . $e->getMessage(), 'ERROR');
                return [];
            }
        }
        
        if (!is_array($items)) {
            return [];
        }
        
        $productService = new ProductService();
        $normalizedItems = [];
        
        foreach ($items as $index => $item) {
            // 文字列の場合は追加でデコード
            if (is_string($item)) {
                try {
                    $item = json_decode($item, true);
                } catch (Exception $e) {
                    self::logMessage("アイテム[$index]のデコードに失敗", 'WARNING');
                    continue;
                }
            }
            
            // 商品情報取得
            $productData = null;
            if (isset($item['product_id']) && !empty($item['product_id'])) {
                $productData = $productService->getProduct($item['product_id']);
            } elseif (isset($item['square_item_id']) && !empty($item['square_item_id'])) {
                $productData = $productService->getProductBySquareId($item['square_item_id']);
            }
            
            // 商品データ構築
            $normalizedItem = [
                'quantity' => (int)($item['quantity'] ?? 1),
                'note' => $item['note'] ?? ''
            ];
            
            if ($productData) {
                $normalizedItem['name'] = $productData['name'];
                $normalizedItem['price'] = floatval($productData['price']);
                $normalizedItem['square_item_id'] = $productData['square_item_id'] ?? null;
            } else {
                // 商品情報が取得できない場合
                if (!isset($item['name']) || !isset($item['price'])) {
                    self::logMessage("商品情報が不完全: " . json_encode($item), 'WARNING');
                    continue;
                }
                $normalizedItem['name'] = $item['name'];
                $normalizedItem['price'] = floatval($item['price']);
                $normalizedItem['square_item_id'] = $item['square_item_id'] ?? null;
            }
            
            $normalizedItems[] = $normalizedItem;
        }
        
        return $normalizedItems;
    }
    
    /**
     * 注文データの準備
     */
    private function prepareOrderData($items) {
        $subtotal = 0;
        $orderItems = [];
        
        foreach ($items as $item) {
            $itemSubtotal = $item['price'] * $item['quantity'];
            $subtotal += $itemSubtotal;
            
            $orderItems[] = [
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $itemSubtotal,
                'note' => $item['note'],
                'square_item_id' => $item['square_item_id'] ?? null
            ];
        }
        
        return [
            'items' => $orderItems,
            'subtotal' => $subtotal
        ];
    }
    
    /**
     * セッション取得または作成
     */
    private function getOrCreateSession($roomNumber) {
        $session = $this->db->selectOne(
            "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
            [$roomNumber]
        );
        
        if (!$session) {
            // 新規セッション作成
            $sessionId = $this->generateSessionId();
            $this->db->insert("order_sessions", [
                'id' => $sessionId,
                'room_number' => $roomNumber,
                'is_active' => 1,
                'session_status' => 'active',
                'opened_at' => date('Y-m-d H:i:s')
            ]);
            
            // 新規セッション時：アクティブユーザーのみに設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
                [$sessionId, $roomNumber]
            );
            
            return ['id' => $sessionId, 'square_item_id' => null];
        } else {
            // 既存セッション取得時：セッションIDを持たないアクティブユーザーに設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND order_session_id IS NULL",
                [$session['id'], $roomNumber]
            );
            
            return $session;
        }
    }
    
    /**
     * セッション累計金額の計算
     */
    private function calculateSessionSubtotal($sessionId) {
        $result = $this->db->selectOne(
            "SELECT COALESCE(SUM(unit_price * quantity), 0) AS subtotal 
             FROM order_details 
             WHERE order_session_id = ?",
            [$sessionId]
        );
        
        return $result ? floatval($result['subtotal']) : 0;
    }
    
    /**
     * セッションID生成
     */
    private function generateSessionId() {
        $base = date('ymdHis');
        $msec = substr((string)round(microtime(true) * 1000), -3);
        $rand = str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);
        return $base . $msec . $rand; // 21桁
    }
    
    /**
     * 注文をデータベースに保存
     */
    private function saveOrderToDatabase($roomNumber, $guestName, $lineUserId, $subtotalAmount, $note, $sessionId, $orderItems) {
        $this->db->beginTransaction();
        
        try {
            // 注文ヘッダー保存
            $orderId = $this->db->insert("orders", [
                'square_order_id' => null,
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'line_user_id' => $lineUserId,
                'order_status' => 'OPEN',
                'total_amount' => $subtotalAmount,
                'memo' => $note,
                'order_datetime' => date('Y-m-d H:i:s'),
                'order_session_id' => $sessionId
            ]);
            
            // 注文詳細保存
            foreach ($orderItems as $item) {
                $this->db->execute(
                    "INSERT INTO order_details (
                        order_id, order_session_id, square_item_id, product_name, 
                        unit_price, quantity, subtotal, note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        $sessionId,
                        $item['square_item_id'] ?? 'item_' . uniqid(),
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        $item['subtotal'],
                        $item['note']
                    ]
                );
            }
            
            $this->db->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            self::logMessage("データベース保存エラー: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * LINE通知送信
     */
    private function sendLineNotification($roomNumber, $orderId, $totalAmount) {
        try {
            $lineUser = $this->db->selectOne(
                "SELECT line_user_id FROM line_room_links WHERE room_number = ? AND is_active = 1",
                [$roomNumber]
            );
            
            if ($lineUser) {
                $orderData = [
                    'id' => $orderId,
                    'room_number' => $roomNumber,
                    'total_amount' => $totalAmount
                ];
                
                $this->lineService->sendOrderCompletionNotice($lineUser['line_user_id'], $orderData);
                self::logMessage("LINE通知送信完了: UserID=" . substr($lineUser['line_user_id'], 0, 5) . "...", 'INFO');
            }
        } catch (Exception $e) {
            self::logMessage("LINE通知送信エラー: " . $e->getMessage(), 'WARNING');
            // LINE通知の失敗は致命的ではないので続行
        }
    }
} 