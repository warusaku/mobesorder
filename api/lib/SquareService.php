<?php
/**
 * Square API連携サービス ファサードクラス
 * Version: 2.0.0
 * Description: 各機能クラスを統合し、統一されたインターフェースを提供するファサードクラス
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

// 基底クラス
require_once __DIR__ . '/SquareService_Base.php';
require_once __DIR__ . '/SquareService_Logger.php';
require_once __DIR__ . '/SquareService_Utility.php';

// カタログモード
require_once __DIR__ . '/SquareService_cat_Catalog.php';
require_once __DIR__ . '/SquareService_cat_Inventory.php';
require_once __DIR__ . '/SquareService_cat_Category.php';

// オープンチケットモード
require_once __DIR__ . '/SquareService_opt_Order.php';
require_once __DIR__ . '/SquareService_opt_RoomTicket.php';

// その他機能
require_once __DIR__ . '/SquareService_Payment.php';
require_once __DIR__ . '/SquareService_Webhook.php';
require_once __DIR__ . '/SquareService_Session.php';

use Square\SquareClient;
use Square\Environment;

/**
 * Square API連携サービスクラス（ファサード）
 */
class SquareService {
    
    // 各機能サービスのインスタンス
    private $catalogService;
    private $inventoryService;
    private $categoryService;
    private $orderService;
    private $roomTicketService;
    private $paymentService;
    private $webhookService;
    private $sessionService;
    private $utilityService;
    private $logger;
    
    // Square API接続情報
    private $client;
    private $locationId;
    
    // 互換性のための静的変数
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB（ルールに基づく）
    private static $recentlyCreatedTickets = [];
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ロガーの初期化
        $this->logger = new SquareService_Logger();
        
        // Square API接続の初期化
        $environment = SQUARE_ENVIRONMENT === 'production' 
            ? Environment::PRODUCTION 
            : Environment::SANDBOX;
            
        $this->client = new SquareClient([
            'accessToken' => SQUARE_ACCESS_TOKEN,
            'environment' => $environment,
            'timeout' => 10,
            'connectTimeout' => 3,
            'curlOptions' => [
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO => __DIR__ . '/../certificates/cacert.pem',
                CURLOPT_VERBOSE => true
            ]
        ]);
        
        $this->locationId = SQUARE_LOCATION_ID;
        
        // 各サービスの初期化（遅延読み込みのため、必要時に初期化）
        $this->utilityService = new SquareService_Utility();
    }
    
    /**
     * カタログサービスの取得（遅延読み込み）
     */
    private function getCatalogService() {
        if ($this->catalogService === null) {
            $this->catalogService = new SquareService_cat_Catalog();
        }
        return $this->catalogService;
    }
    
    /**
     * 在庫サービスの取得（遅延読み込み）
     */
    private function getInventoryService() {
        if ($this->inventoryService === null) {
            $this->inventoryService = new SquareService_cat_Inventory();
        }
        return $this->inventoryService;
    }
    
    /**
     * カテゴリサービスの取得（遅延読み込み）
     */
    private function getCategoryService() {
        if ($this->categoryService === null) {
            $this->categoryService = new SquareService_cat_Category();
        }
        return $this->categoryService;
    }
    
    /**
     * 注文サービスの取得（遅延読み込み）
     */
    private function getOrderService() {
        if ($this->orderService === null) {
            $this->orderService = new SquareService_opt_Order();
        }
        return $this->orderService;
    }
    
    /**
     * 部屋チケットサービスの取得（遅延読み込み）
     */
    private function getRoomTicketService() {
        if ($this->roomTicketService === null) {
            $this->roomTicketService = new SquareService_opt_RoomTicket();
        }
        return $this->roomTicketService;
    }
    
    /**
     * 決済サービスの取得（遅延読み込み）
     */
    private function getPaymentService() {
        if ($this->paymentService === null) {
            $this->paymentService = new SquareService_Payment();
        }
        return $this->paymentService;
    }
    
    /**
     * Webhookサービスの取得（遅延読み込み）
     */
    private function getWebhookService() {
        if ($this->webhookService === null) {
            $this->webhookService = new SquareService_Webhook();
        }
        return $this->webhookService;
    }
    
    /**
     * セッションサービスの取得（遅延読み込み）
     */
    private function getSessionService() {
        if ($this->sessionService === null) {
            $this->sessionService = new SquareService_Session();
        }
        return $this->sessionService;
    }
    
    // ========================================
    // カタログモード関連メソッド
    // ========================================
    
    /**
     * 商品カタログを取得
     */
    public function getItems($returnRawObjects = false, $maxResults = 200) {
        return $this->getCatalogService()->getItems($returnRawObjects, $maxResults);
    }
    
    /**
     * 画像IDから画像オブジェクトを取得
     */
    public function getImageById($imageId) {
        return $this->getCatalogService()->getImageById($imageId);
    }
    
    /**
     * 商品の在庫数を取得
     */
    public function getInventoryCounts($catalogItemIds) {
        return $this->getInventoryService()->getInventoryCounts($catalogItemIds);
    }
    
    /**
     * 商品の在庫情報を取得（互換性のために維持）
     */
    public function getInventory($catalogItemIds) {
        return $this->getInventoryService()->getInventory($catalogItemIds);
    }
    
    /**
     * カテゴリ一覧を取得
     */
    public function getCategories() {
        return $this->getCategoryService()->getCategories();
    }
    
    // ========================================
    // オープンチケットモード関連メソッド
    // ========================================
    
    /**
     * 注文を作成
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '') {
        return $this->getOrderService()->createOrder($roomNumber, $items, $guestName, $note);
    }
    
    /**
     * 注文情報を取得
     */
    public function getOrder($orderId) {
        return $this->getOrderService()->getOrder($orderId);
    }
    
    /**
     * 部屋番号に関連する注文を検索
     */
    public function searchOrdersByRoom($roomNumber) {
        return $this->getOrderService()->searchOrdersByRoom($roomNumber);
    }
    
    /**
     * 部屋用の保留伝票を作成
     */
    public function createRoomTicket($roomNumber, $guestName = '', $lineUserId = null) {
        return $this->getRoomTicketService()->createRoomTicket($roomNumber, $guestName, $lineUserId);
    }
    
    /**
     * 部屋に関連付けられた保留伝票を取得
     */
    public function getRoomTicket($roomNumber) {
        return $this->getRoomTicketService()->getRoomTicket($roomNumber);
    }
    
    /**
     * 保留伝票に商品を追加
     */
    public function addItemToRoomTicket($roomNumber, $items) {
        return $this->getRoomTicketService()->addItemToRoomTicket($roomNumber, $items);
    }
    
    // ========================================
    // 決済関連メソッド
    // ========================================
    
    /**
     * 決済処理を実行
     */
    public function processPayment($orderId, $amount, $sourceId) {
        return $this->getPaymentService()->processPayment($orderId, $amount, $sourceId);
    }
    
    /**
     * Session 注文を現金支払いとして即時決済する
     */
    public function createSessionCashPayment($orderId) {
        return $this->getPaymentService()->createSessionCashPayment($orderId);
    }
    
    // ========================================
    // Webhook関連メソッド
    // ========================================
    
    /**
     * Webhookの署名を検証
     */
    public function validateWebhookSignature($signatureHeader, $requestBody) {
        return $this->getWebhookService()->validateWebhookSignature($signatureHeader, $requestBody);
    }
    
    /**
     * Webhook送信（内部使用）
     */
    private static function sendWebhookEvent($eventType, $payload) {
        $instance = new self();
        return $instance->getWebhookService()->sendWebhookEvent($eventType, $payload);
    }
    
    // ========================================
    // セッション商品関連メソッド
    // ========================================
    
    /**
     * セッション用の合計金額商品を作成 / 更新
     */
    public function createOrUpdateSessionProduct($sessionId, $roomNumber, $totalAmount, $existingItemId = null) {
        return $this->getSessionService()->createOrUpdateSessionProduct($sessionId, $roomNumber, $totalAmount, $existingItemId);
    }
    
    /**
     * ダミー商品を無効化 (非公開) にする
     */
    public function disableSessionProduct($squareItemId) {
        return $this->getSessionService()->disableSessionProduct($squareItemId);
    }
    
    /**
     * セッション用ダミー商品を含む Square 注文を作成
     */
    public function createSessionOrder($squareItemId, $quantity = 1, $sessionId = '') {
        return $this->getSessionService()->createSessionOrder($squareItemId, $quantity, $sessionId);
    }
    
    // ========================================
    // ユーティリティメソッド
    // ========================================
    
    /**
     * Square APIへの接続テスト
     */
    public function testConnection() {
        // 基底クラスから直接実行するため、一時的なインスタンスを作成
        $baseService = new class extends SquareService_Base {
            public function __construct() {
                parent::__construct();
            }
        };
        return $baseService->testConnection();
    }
    
    /**
     * Square API クライアントを取得
     */
    public function getSquareClient() {
        return $this->client;
    }
    
    /**
     * Money型をフォーマットして返す（プライベートメソッドの互換性）
     */
    private function formatMoney($money) {
        return $this->utilityService->formatMoney($money);
    }
    
    /**
     * 注文の商品リストをフォーマット（プライベートメソッドの互換性）
     */
    private function formatLineItems($lineItems) {
        return $this->utilityService->formatLineItems($lineItems);
    }
    
    /**
     * LINE User IDからguest_name情報を設定するメソッド（プライベートメソッドの互換性）
     */
    private function setupGuestNameFromLineUserId(&$metadata, $lineUserId, $roomNumber) {
        return $this->utilityService->setupGuestNameFromLineUserId($metadata, $lineUserId, $roomNumber);
    }
    
    /**
     * 最後のエラーメッセージを取得（プライベートメソッドの互換性）
     */
    private function getLastErrorMessage() {
        return $this->logger->getLastErrorMessage();
    }
    
    /**
     * 設定情報を取得（静的メソッドの互換性）
     */
    private static function getSquareSettings() {
        return SquareService_Base::getSquareSettings();
    }
    
    // ========================================
    // 互換性のための静的メソッド
    // ========================================
    
    /**
     * ログファイルの初期化（互換性のため）
     */
    private static function initLogFile() {
        // SquareService_Loggerで処理されるため、何もしない
    }
    
    /**
     * ログローテーションのチェックと実行（互換性のため）
     */
    private static function checkLogRotation() {
        // SquareService_Loggerで処理されるため、何もしない
    }
    
    /**
     * ログメッセージをファイルに書き込む（互換性のため）
     */
    private static function logMessage($message, $level = 'INFO') {
        $logger = SquareService_Logger::getInstance();
        $logger->logMessage($message, $level);
    }
    
    /**
     * 引数の内容を文字列化する（互換性のため）
     */
    private static function formatArgs($args) {
        $logger = SquareService_Logger::getInstance();
        return $logger->formatArgs($args);
    }
} 