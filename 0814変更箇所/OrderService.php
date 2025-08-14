<?php
/**
 * 注文管理サービスクラス（ハンドラ）
 * バージョン: 2.0.0
 * ファイル説明: カタログ商品モードとOpenTicketモードを振り分けるハンドラクラス
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/OrderServiceInterface.php';
require_once __DIR__ . '/OrderService_Catalog.php';
require_once __DIR__ . '/OrderService_Openticket.php';
require_once __DIR__ . '/OrderWebhook.php';

class OrderService {
    private $db;
    private $implementation;
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB 規約
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        self::logMessage("OrderService::__construct - 注文サービス初期化開始", 'INFO');
        
        $this->db = Database::getInstance();
        
        // 設定に基づいて適切な実装を選択
        $openTicketMode = self::isSquareOpenTicketEnabled();
        
        if ($openTicketMode) {
            self::logMessage("OpenTicketモードで動作します", 'INFO');
            $this->implementation = new OrderService_Openticket();
        } else {
            self::logMessage("カタログ商品モードで動作します", 'INFO');
            $this->implementation = new OrderService_Catalog();
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
     * 注文を作成（実装クラスに委譲）
     * 
     * @param string $roomNumber 部屋番号
     * @param array|string $items 注文商品の配列
     * @param string $guestName ゲスト名
     * @param string $note 注文全体の備考
     * @param string $lineUserId LINE User ID
     * @return array|false 成功時は注文情報、失敗時はfalse
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '', $lineUserId = '') {
        self::logMessage("createOrder - モード: " . $this->implementation->getModeName(), 'INFO');
        
        // テストモードの処理（互換性のため維持）
        if ($this->isTestMode()) {
            return $this->handleTestModeOrder($roomNumber, $items, $guestName, $note, $lineUserId);
        }
        
        // 実装クラスに委譲
        return $this->implementation->createOrder($roomNumber, $items, $guestName, $note, $lineUserId);
    }
    
    /**
     * テストモードかどうかを判定
     */
    private function isTestMode() {
        // TEST_MODE定数のチェック
        if (defined('TEST_MODE') && TEST_MODE === true) {
            return true;
        }
        
        // ローカル環境のチェック
        if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            return true;
        }
        
        // テストパスのチェック
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/test/') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * テストモードでの注文処理
     */
    private function handleTestModeOrder($roomNumber, $items, $guestName, $note, $lineUserId) {
        self::logMessage("テストモードでの注文処理を実行", 'INFO');
        
        // 商品データの正規化（簡易版）
        if (is_string($items)) {
            try {
                $items = json_decode($items, true);
            } catch (Exception $e) {
                self::logMessage("テストモード: 商品データのデコードに失敗", 'ERROR');
                return false;
            }
        }
        
        if (!is_array($items) || empty($items)) {
            self::logMessage("テストモード: 有効な商品がありません", 'ERROR');
            return false;
        }
        
        // 金額計算
        $subtotalAmount = 0;
        $orderItems = [];
        
        foreach ($items as $item) {
            if (is_string($item)) {
                try {
                    $item = json_decode($item, true);
                } catch (Exception $e) {
                    continue;
                }
            }
            
            $price = floatval($item['price'] ?? 0);
            $quantity = intval($item['quantity'] ?? 1);
            $subtotalAmount += $price * $quantity;
            
            $orderItems[] = [
                'name' => $item['name'] ?? 'テスト商品',
                'price' => $price,
                'quantity' => $quantity
            ];
        }
        
        $dummySquareOrderId = 'test_order_' . uniqid();
        
        // データベースに保存
        $this->db->beginTransaction();
        
        try {
            $orderId = $this->db->insert("orders", [
                'square_order_id' => $dummySquareOrderId,
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'line_user_id' => $lineUserId,
                'order_status' => 'TEST_MODE',
                'total_amount' => $subtotalAmount,
                'note' => $note . ' [テストモード]',
                'order_datetime' => date('Y-m-d H:i:s')
            ]);
            
            foreach ($orderItems as $item) {
                $this->db->execute(
                    "INSERT INTO order_details (
                        order_id, order_session_id, square_item_id, product_name, 
                        unit_price, quantity, subtotal, note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        null,
                        'item_' . uniqid(),
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        $item['price'] * $item['quantity'],
                        '[テストモード]'
                    ]
                );
            }
            
            $this->db->commit();
            
            self::logMessage("テストモードでの注文作成成功: ID={$orderId}", 'INFO');
            
            return [
                'id' => $orderId,
                'square_order_id' => $dummySquareOrderId,
                'room_number' => $roomNumber,
                'total_amount' => $subtotalAmount,
                'status' => 'TEST_MODE',
                'is_test' => true,
                'note' => $note . ' [テストモード]'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            self::logMessage("テストモードでの注文作成失敗: " . $e->getMessage(), 'ERROR');
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
        self::logMessage("getOrder - OrderID: {$orderId}", 'INFO');
        
        // 注文ヘッダーを取得
        $order = $this->db->selectOne(
            "SELECT * FROM orders WHERE id = ?",
            [$orderId]
        );
        
        if (!$order) {
            self::logMessage("注文が見つかりません: OrderID={$orderId}", 'WARNING');
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
        self::logMessage("getOrdersByRoom - Room: {$roomNumber}, ActiveOnly: " . ($activeOnly ? 'true' : 'false'), 'INFO');
        
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
        self::logMessage("updateOrderStatus - OrderID: {$orderId}, Status: {$status}", 'INFO');
        
        $validStatuses = ['OPEN', 'COMPLETED', 'CANCELED'];
        
        if (!in_array($status, $validStatuses)) {
            self::logMessage("無効なステータス: {$status}", 'WARNING');
            return false;
        }
        
        $result = $this->db->execute(
            "UPDATE orders SET order_status = ? WHERE id = ?",
            [$status, $orderId]
        );
        
        if ($result) {
            self::logMessage("注文ステータスを更新しました: OrderID={$orderId}, Status={$status}", 'INFO');
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
        self::logMessage("completeOrdersOnCheckout - Room: {$roomNumber}", 'INFO');
        
        $result = $this->db->execute(
            "UPDATE orders 
             SET order_status = 'COMPLETED', checkout_datetime = NOW() 
             WHERE room_number = ? AND order_status = 'OPEN'",
            [$roomNumber]
        );
        
        if ($result) {
            self::logMessage("部屋 {$roomNumber} の全ての注文を完了にしました", 'INFO');
            return true;
        }
        
        return false;
    }
    
    /**
     * Square設定読み込み
     */
    private static function getSquareSettings() {
        static $settings = null;
        if ($settings !== null) return $settings;
        
        // adminsetting_registrer.php 経由で取得（規約遵守）
        $regPath = realpath(__DIR__ . '/../../admin/adminsetting_registrer.php');
        self::logMessage("adminsetting_registrer.php を読み込み: $regPath", 'INFO');
        
        if (!$regPath || !file_exists($regPath)) {
            self::logMessage('adminsetting_registrer.php が見つかりません', 'ERROR');
            return $settings = [];
        }
        
        // settingsFilePath を事前にグローバルにセット
        if (!isset($GLOBALS['settingsFilePath']) || empty($GLOBALS['settingsFilePath'])) {
            $GLOBALS['settingsFilePath'] = dirname($regPath) . '/adminpagesetting/adminsetting.json';
        }
        
        // logFile も同様にグローバルへ
        if (!isset($GLOBALS['logFile']) || empty($GLOBALS['logFile'])) {
            $rootPath = realpath(dirname($regPath) . '/..');
            $logPath = ($rootPath ?: __DIR__ . '/../../') . '/logs/adminsetting_registrer.log';
            $GLOBALS['logFile'] = $logPath;
        }
        
        if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
            define('ADMIN_SETTING_INTERNAL_CALL', true);
        }
        
        include_once $regPath;
        
        if (function_exists('loadSettings')) {
            $all = loadSettings();
            if (is_array($all) && isset($all['square_settings'])) {
                return $settings = $all['square_settings'];
            }
            self::logMessage('square_settings セクションが見つかりません', 'WARNING');
        } else {
            self::logMessage('loadSettings 関数が定義されていません', 'ERROR');
        }
        
        return $settings = [];
    }
    
    /**
     * OpenTicketモードが有効かどうかを判定
     */
    public static function isSquareOpenTicketEnabled() {
        $set = self::getSquareSettings();
        return isset($set['open_ticket']) ? filter_var($set['open_ticket'], FILTER_VALIDATE_BOOLEAN) : true;
    }
    
    /**
     * セッション終了時のWebhook通知
     */
    public static function sendSessionCloseWebhook($sessionId, $closeType = 'Completed') {
        self::logMessage("sendSessionCloseWebhook - SessionID: {$sessionId}, CloseType: {$closeType}", 'INFO');
        
        $webhook = new OrderWebhook();
        $webhook->sendSessionCloseWebhook($sessionId, $closeType);
    }
    
    /**
     * 現在の動作モードを取得
     */
    public function getCurrentMode() {
        return $this->implementation->getModeName();
    }
} 