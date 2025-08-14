<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/SquareService.php';
require_once __DIR__ . '/ProductService.php';
require_once __DIR__ . '/LineService.php';
require_once __DIR__ . '/RoomTicketService.php';

/**
 * 注文管理サービスクラス
 */
class OrderService {
    private $db;
    private $squareService;
    private $lineService;
    private $roomTicketService;
    private static $logFile = null;
    private static $maxLogSize = 500 * 1024; // 500KB
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        self::logMessage("OrderService::__construct - 注文サービス初期化開始", 'INFO');
        
        $this->db = Database::getInstance();
        $this->squareService = new SquareService();
        $this->lineService = new LineService();
        $this->roomTicketService = new RoomTicketService();
        
        // 依存サービスの初期化チェック
        if (!$this->squareService) {
            self::logMessage("SquareServiceの初期化に失敗しました", 'ERROR');
            $this->squareService = new SquareService(); // 再試行
        }
        
        if (!$this->roomTicketService) {
            self::logMessage("RoomTicketServiceの初期化に失敗しました", 'ERROR');
            $this->roomTicketService = new RoomTicketService(); // 再試行
        }
        
        self::logMessage("OrderService::__construct - 注文サービス初期化完了", 'INFO');
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
        
        self::$logFile = $logDir . '/OrderService.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            // ログファイルが存在しない場合は作成する
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成\n";
            file_put_contents(self::$logFile, $message);
            return;
        }
        
        // ファイルサイズを確認
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            // 古いログファイルの名前を変更
            $backupFile = self::$logFile . '.' . date('Y-m-d_H-i-s');
            rename(self::$logFile, $backupFile);
            
            // 新しいログファイルを作成
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログローテーション実行 - 前回ログ: $backupFile ($fileSize bytes)\n";
            file_put_contents(self::$logFile, $message);
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル (INFO, WARNING, ERROR)
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        // ログファイルへの書き込み
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 引数の内容を文字列化する
     * 
     * @param mixed $args 引数
     * @return string 文字列化された引数
     */
    private static function formatArgs($args) {
        if (is_array($args)) {
            // 配列の場合は再帰的に処理
            $result = [];
            foreach ($args as $key => $value) {
                if (is_array($value)) {
                    // 配列が大きすぎる場合は要約
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
     * 注文を作成
     * 
     * @param string $roomNumber 部屋番号
     * @param array|string $items 注文商品の配列 [['product_id' => 'xxx', 'square_item_id' => 'xxx', 'quantity' => 1, 'note' => '...'], ...]
     * @param string $guestName ゲスト名
     * @param string $note 注文全体の備考
     * @param string $lineUserId LINE User ID
     * @return array|false 成功時は注文情報、失敗時はfalse
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '', $lineUserId = '') {
        // 引数のログ記録
        $argsLog = [
            'roomNumber' => $roomNumber,
            'roomNumberType' => gettype($roomNumber),
            'items' => (is_array($items) && count($items) > 5) ? '[配列: ' . count($items) . '件]' : $items,
            'guestName' => $guestName,
            'note' => $note,
            'lineUserId' => $lineUserId ? substr($lineUserId, 0, 5) . '...' : 'なし' // LINE User IDをログに記録
        ];
        self::logMessage("createOrder 開始 - 引数: " . self::formatArgs($argsLog), 'INFO');
        self::logMessage("部屋番号の詳細: '{$roomNumber}' (型: " . gettype($roomNumber) . ")", 'INFO');
        
        try {
            // デバッグログ
            Utils::log("注文作成開始: room={$roomNumber}, items=" . json_encode($items), 'INFO', 'OrderService');
            
            // テストモードの判定
            $isTestMode = false;
            if (defined('TEST_MODE') && TEST_MODE === true) {
                $isTestMode = true;
                Utils::log("テストモードで実行中", 'DEBUG', 'OrderService');
            }
            
            // クライアントIPアドレスがテスト環境のものかチェック
            if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
                $isTestMode = true;
                Utils::log("ローカル環境で実行中", 'DEBUG', 'OrderService');
            }
            
            // URLパスに「test」が含まれる場合もテストモードと判断
            if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/test/') !== false) {
                $isTestMode = true;
                Utils::log("テストパスで実行中: " . $_SERVER['REQUEST_URI'], 'DEBUG', 'OrderService');
            }

            // 商品データの解析・処理
            $processedItems = [];
            
            // 文字列として渡された場合はJSONデコード
            if (is_string($items)) {
                self::logMessage("商品データは文字列で渡されました。JSONデコードを試みます", 'INFO');
                try {
                    $decodedItems = json_decode($items, true);
                    if (is_array($decodedItems)) {
                        $items = $decodedItems;
                        self::logMessage("商品データをJSONデコードしました: " . count($items) . "件", 'INFO');
                    } else {
                        self::logMessage("商品データのJSONデコードに失敗しました", 'ERROR');
                        return false;
                    }
                } catch (Exception $e) {
                    self::logMessage("商品データのJSONデコード中にエラー: " . $e->getMessage(), 'ERROR');
                    return false;
                }
            }
            
            // 商品配列のチェック
            if (!is_array($items)) {
                self::logMessage("商品データが無効です: " . gettype($items), 'ERROR');
                return false;
            }

            // 注文商品の検証と準備
            $orderItems = [];
            $totalAmount = 0;
            $productService = new ProductService();
            
            // 各アイテムを正規化
            foreach ($items as $index => $item) {
                // 文字列の場合はさらにデコードを試みる
                if (is_string($item)) {
                    self::logMessage("アイテム[$index]は文字列です。デコードを試みます: $item", 'INFO');
                    try {
                        $decodedItem = json_decode($item, true);
                        if (is_array($decodedItem)) {
                            $item = $decodedItem;
                            self::logMessage("アイテム[$index]をデコードしました: " . json_encode($item), 'INFO');
                        } else {
                            // 文字列のままの場合、正規表現で抽出を試みる
                            if (preg_match('/product_id[\"\']?\s*:\s*[\"\']?([^\"\'}\s,]+)/', $item, $matches)) {
                                $product_id = $matches[1];
                                $quantity = 1;
                                
                                // 数量も抽出を試みる
                                if (preg_match('/quantity[\"\']?\s*:\s*(\d+)/', $item, $qMatches)) {
                                    $quantity = (int)$qMatches[1];
                                }
                                
                                $item = [
                                    'product_id' => $product_id,
                                    'quantity' => $quantity,
                                    'note' => ''
                                ];
                                self::logMessage("アイテム[$index]を正規表現で抽出しました: " . json_encode($item), 'INFO');
                            } else if (preg_match('/square_item_id[\"\']?\s*:\s*[\"\']?([^\"\'}\s,]+)/', $item, $matches)) {
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
                                self::logMessage("アイテム[$index]を正規表現で抽出しました: " . json_encode($item), 'INFO');
                            } else {
                                self::logMessage("アイテム[$index]のデコードに失敗しました", 'ERROR');
                                continue;
                            }
                        }
                    } catch (Exception $jsonEx) {
                        self::logMessage("アイテム[$index]のJSON解析エラー: " . $jsonEx->getMessage(), 'ERROR');
                        continue;
                    }
                }
                
                // 商品情報を取得するために必要なIDがあるかチェック
                $productData = [];
                
                // IDによる商品情報取得の試み
                if (isset($item['product_id']) && !empty($item['product_id'])) {
                    self::logMessage("product_id={$item['product_id']}から商品情報を取得します", 'INFO');
                    $product = $productService->getProduct($item['product_id']);
                    
                    if ($product) {
                        $productData = $product;
                        self::logMessage("商品ID {$item['product_id']} から情報取得成功", 'INFO');
                    } else {
                        self::logMessage("商品ID {$item['product_id']} から情報取得失敗", 'WARNING');
                }
                } else if (isset($item['square_item_id']) && !empty($item['square_item_id'])) {
                    self::logMessage("square_item_id={$item['square_item_id']}から商品情報を取得します", 'INFO');
                    $product = $productService->getProductBySquareId($item['square_item_id']);
                    
                    if ($product) {
                        $productData = $product;
                        self::logMessage("Square ID {$item['square_item_id']} から情報取得成功", 'INFO');
                    } else {
                        self::logMessage("Square ID {$item['square_item_id']} から情報取得失敗", 'WARNING');
                    }
                }
                
                // 商品情報が取得できなかった場合、名前と価格の直接指定が必要
                if (empty($productData)) {
                    if (!isset($item['name']) || empty($item['name'])) {
                        self::logMessage("商品情報を取得できず、名前も指定されていません: " . json_encode($item), 'ERROR');
                        continue;
                    }
                    
                    // 必要最低限の情報で商品データを構築
                    $productData = [
                        'name' => $item['name'],
                        'price' => isset($item['price']) && is_numeric($item['price']) ? floatval($item['price']) : 0,
                    ];
                    self::logMessage("データベースから商品情報を取得できなかったため、クライアント情報を使用: " . json_encode($productData), 'INFO');
                }
                
                // オーダー項目データを構築
                $orderItem = [
                    'quantity' => (int)($item['quantity'] ?? 1)
                ];
                
                // 常に名前と価格を使用する（カタログオブジェクトIDは使用しない）
                $orderItem['name'] = $productData['name'] ?? $item['name'] ?? 'Unknown Product';
                
                // 価格を設定
                if (isset($item['price']) && is_numeric($item['price']) && $item['price'] > 0) {
                    // クライアント指定価格を優先
                    $orderItem['price'] = floatval($item['price']);
                } elseif (isset($productData['price'])) {
                    // データベース価格をフォールバックとして使用
                    $orderItem['price'] = floatval($productData['price']);
                } else {
                    // デフォルト価格
                    $orderItem['price'] = 0;
                }
                
                // 備考を設定
                if (!empty($item['note'])) {
                    $orderItem['note'] = $item['note'];
                }
                
                // 小計を計算
                $subtotal = $orderItem['price'] * $orderItem['quantity'];
                $totalAmount += $subtotal;
                
                // 処理済みアイテムに追加
                $orderItems[] = $orderItem;
                
                self::logMessage("商品追加: 名前={$orderItem['name']}, 単価={$orderItem['price']}, 数量={$orderItem['quantity']}, 小計={$subtotal}", 'INFO');
                Utils::log("商品追加: {$orderItem['name']}, 単価: {$orderItem['price']}, 数量: {$orderItem['quantity']}, 小計: {$subtotal}", 'DEBUG', 'OrderService');
            }
            
            // 商品がない場合はエラー
            if (empty($orderItems)) {
                self::logMessage("注文商品が見つかりません", 'ERROR');
                return false;
            }
            
            // 消費税を追加（10%）
            $tax = round($totalAmount * 0.1);
            $totalAmount += $tax;
            self::logMessage("合計金額計算: 小計={$totalAmount}円, 税額={$tax}円, 合計={$totalAmount}円", 'INFO');
            Utils::log("合計金額: {$totalAmount}円（税込）", 'DEBUG', 'OrderService');
            
            // テストモードではSquare連携をスキップしてローカルDBのみに保存する
            if ($isTestMode) {
                Utils::log("テストモードのためSquare連携をスキップしてローカルDBのみに保存します", 'DEBUG', 'OrderService');
                $dummySquareOrderId = 'test_order_' . uniqid();
                
                // データベースに注文を保存
                $this->db->beginTransaction();
                
                try {
                    // 注文ヘッダーを保存
                    $orderId = $this->db->insert(
                        "orders",
                        [
                            'square_order_id' => $dummySquareOrderId,
                            'room_number' => $roomNumber,
                            'guest_name' => $guestName,
                            'line_user_id' => $lineUserId,
                            'order_status' => 'TEST_MODE',
                            'total_amount' => $totalAmount,
                            'note' => $note . ' [テストモード]',
                            'order_datetime' => date('Y-m-d H:i:s')
                        ]
                    );
                    
                    // 注文詳細を保存
                    foreach ($orderItems as $item) {
                        $this->db->execute(
                            "INSERT INTO order_details (
                                order_id, square_item_id, product_name, unit_price,
                                quantity, subtotal, note
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [
                                $orderId,
                                $item['square_item_id'] ?? 'item_' . uniqid(),
                                $item['name'],
                                $item['price'],
                                $item['quantity'],
                                $item['price'] * $item['quantity'],
                                ($item['note'] ?? '') . ' [テストモード]'
                            ]
                        );
                    }
                    
                    $this->db->commit();
                    
                    Utils::log("テストモードでの注文作成成功: ID={$orderId}", 'INFO', 'OrderService');
                    
                    return [
                        'id' => $orderId,
                        'square_order_id' => $dummySquareOrderId,
                        'room_number' => $roomNumber,
                        'total_amount' => $totalAmount,
                        'status' => 'TEST_MODE',
                        'is_test' => true
                    ];
                } catch (Exception $localEx) {
                    $this->db->rollback();
                    Utils::log("テストモードでの注文作成失敗: " . $localEx->getMessage(), 'ERROR', 'OrderService');
                    throw $localEx;
                }
            }
            
            // 本番モードの場合: RoomTicketServiceを使用して保留伝票に追加
            $roomTicketService = new RoomTicketService();
            self::logMessage("RoomTicketServiceを使用して保留伝票に商品を追加します: " . count($orderItems) . "件", 'INFO');
            
            // 保留伝票に商品を追加
            $updatedTicket = $roomTicketService->addItemToRoomTicket($roomNumber, $orderItems);
            
            if (!$updatedTicket) {
                self::logMessage("保留伝票への商品追加に失敗しました", 'ERROR');
                Utils::log("Failed to add items to room ticket for room {$roomNumber}", 'ERROR', 'OrderService');
                
                // RoomTicketServiceのログを確認
                if (file_exists(__DIR__ . '/../../logs/RoomTicketService.log')) {
                    $lastLogLines = shell_exec('tail -50 ' . __DIR__ . '/../../logs/RoomTicketService.log');
                    self::logMessage("RoomTicketService の最新ログ: \n" . $lastLogLines, 'INFO');
                }
                
                // SquareServiceのログも確認
                if (file_exists(__DIR__ . '/../../logs/SquareService.log')) {
                    $lastLogLines = shell_exec('tail -50 ' . __DIR__ . '/../../logs/SquareService.log');
                    self::logMessage("SquareService の最新ログ: \n" . $lastLogLines, 'INFO');
                    }
                    
                // リトライ
                self::logMessage("保留伝票への商品追加を再試行します", 'INFO');
                sleep(2); // 少し待機
                $updatedTicket = $roomTicketService->addItemToRoomTicket($roomNumber, $orderItems);
                
                if (!$updatedTicket) {
                    self::logMessage("保留伝票への商品追加の再試行も失敗しました", 'ERROR');
                    return false;
                }
                
                self::logMessage("保留伝票への商品追加の再試行が成功しました", 'INFO');
            }
            
            // データベースに注文を保存
            $this->db->beginTransaction();
            
            try {
                // 注文ヘッダーを保存
                $orderId = $this->db->insert(
                    "orders",
                    [
                        'square_order_id' => $updatedTicket['square_order_id'],
                        'room_number' => $roomNumber,
                        'guest_name' => $guestName,
                        'line_user_id' => $lineUserId,
                        'order_status' => 'OPEN',
                        'total_amount' => $totalAmount,
                        'note' => $note,
                        'order_datetime' => date('Y-m-d H:i:s')
                    ]
                );
                
                // 注文詳細を保存
                foreach ($orderItems as $item) {
                    $this->db->execute(
                        "INSERT INTO order_details (
                            order_id, square_item_id, product_name, unit_price,
                            quantity, subtotal, note
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $orderId,
                            $item['square_item_id'] ?? 'item_' . uniqid(),
                            $item['name'],
                            $item['price'],
                            $item['quantity'],
                            $item['price'] * $item['quantity'],
                            $item['note'] ?? ''
                        ]
                    );
                }
                
                $this->db->commit();
                
                // LINE通知を送信（ユーザーが紐付けられている場合）
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
                    
                    try {
                        $this->lineService->sendOrderCompletionNotice($lineUser['line_user_id'], $orderData);
                    } catch (Exception $lineEx) {
                        Utils::log("LINE通知送信失敗: " . $lineEx->getMessage(), 'WARNING', 'OrderService');
                        // LINE通知の失敗は致命的ではないので続行
                    }
                }
                
                // 成功結果を返す
                $orderResult = [
                    'id' => $orderId,
                    'square_order_id' => $updatedTicket['square_order_id'],
                    'room_number' => $roomNumber,
                    'total_amount' => $totalAmount,
                    'status' => 'OPEN',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                self::logMessage("createOrder 完了 - 結果: " . self::formatArgs($orderResult), 'INFO');
                Utils::log("Order created: $orderId for room $roomNumber", 'INFO', 'OrderService');
                return $orderResult;
            } catch (Exception $e) {
                $this->db->rollback();
                self::logMessage("注文処理中の例外: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
                Utils::log("Order creation failed: " . $e->getMessage(), 'ERROR', 'OrderService');
                return false;
            }
        } catch (Exception $e) {
            self::logMessage("createOrder エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("Order creation exception: " . $e->getMessage(), 'ERROR', 'OrderService');
            return false;
        }
    }
    
    /**
     * 注文を取得
     * 
     * @param int $orderId 注文ID
     * @return array|null 注文情報、または存在しない場合はnull
     */
    public function getOrder($orderId) {
        // 注文ヘッダーを取得
        $order = $this->db->selectOne(
            "SELECT * FROM orders WHERE id = ?",
            [$orderId]
        );
        
        if (!$order) {
            return null;
        }
        
        // 注文詳細を取得
        $orderDetails = $this->db->select(
            "SELECT * FROM order_details WHERE order_id = ?",
            [$orderId]
        );
        
        $order['items'] = $orderDetails;
        
        return $order;
    }
    
    /**
     * 部屋番号に関連付けられた注文を取得
     * 
     * @param string $roomNumber 部屋番号
     * @param bool $activeOnly アクティブな注文のみ取得する場合はtrue
     * @return array 注文情報の配列
     */
    public function getOrdersByRoom($roomNumber, $activeOnly = false) {
        $query = "SELECT * FROM orders WHERE room_number = ?";
        $params = [$roomNumber];
        
        if ($activeOnly) {
            $query .= " AND order_status NOT IN ('COMPLETED', 'CANCELLED')";
        }
        
        $query .= " ORDER BY order_datetime DESC";
        
        return $this->db->select($query, $params);
    }
    
    /**
     * 注文ステータスを更新
     * 
     * @param int $orderId 注文ID
     * @param string $status 新しいステータス ('OPEN', 'COMPLETED', 'CANCELED')
     * @return bool 成功した場合はtrue
     */
    public function updateOrderStatus($orderId, $status) {
        $validStatuses = ['OPEN', 'COMPLETED', 'CANCELED'];
        
        if (!in_array($status, $validStatuses)) {
            Utils::log("Invalid order status: $status", 'WARNING', 'OrderService');
            return false;
        }
        
        $result = $this->db->execute(
            "UPDATE orders SET order_status = ? WHERE id = ?",
            [$status, $orderId]
        );
        
        if ($result) {
            Utils::log("Order $orderId status updated to $status", 'INFO', 'OrderService');
            return true;
        }
        
        return false;
    }
    
    /**
     * チェックアウト時に注文を完了処理
     * 
     * @param string $roomNumber 部屋番号
     * @return bool 成功した場合はtrue
     */
    public function completeOrdersOnCheckout($roomNumber) {
        $result = $this->db->execute(
            "UPDATE orders 
             SET order_status = 'COMPLETED', checkout_datetime = NOW() 
             WHERE room_number = ? AND order_status = 'OPEN'",
            [$roomNumber]
        );
        
        if ($result) {
            Utils::log("All open orders for room $roomNumber marked as completed", 'INFO', 'OrderService');
            return true;
        }
        
        return false;
    }
} 