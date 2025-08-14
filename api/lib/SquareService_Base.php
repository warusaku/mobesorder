<?php
/**
 * Square API連携サービス基底クラス
 * Version: 1.0.0
 * Description: Square API接続とクライアント管理を担当する基底クラス
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/SquareService_Logger.php';

use Square\SquareClient;
use Square\Environment;

abstract class SquareService_Base {
    protected $client;
    protected $locationId;
    protected $logger;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ロガーの初期化
        $this->logger = new SquareService_Logger();
        $this->logger->logMessage("SquareService_Base::__construct - Square API接続初期化開始", 'INFO');
        
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
        
        // API接続情報をログに記録
        $this->logger->logMessage("Square API設定情報: accessToken=" . substr(SQUARE_ACCESS_TOKEN, 0, 5) . "..., " .
                      "environment=" . $environment . ", " .
                      "timeout=10, connectTimeout=3, " .
                      "locationId=" . SQUARE_LOCATION_ID, 'INFO');
        
        $this->logger->logMessage("SquareService_Base::__construct - Square API接続初期化完了 (環境: {$environment})", 'INFO');
    }
    
    /**
     * Square API クライアントを取得
     * 
     * @return SquareClient Square API クライアントインスタンス
     */
    public function getSquareClient() {
        return $this->client;
    }
    
    /**
     * ロケーションIDを取得
     * 
     * @return string ロケーションID
     */
    public function getLocationId() {
        return $this->locationId;
    }
    
    /**
     * Square APIへの接続テスト
     * 
     * @return array|bool 成功時は場所情報を含む配列、失敗時はfalse
     * @throws Exception 接続に失敗した場合
     */
    public function testConnection() {
        try {
            $this->logger->logMessage("Square API接続テスト開始", 'INFO');
            
            // 一時的にタイムアウトを短く設定したクライアントを作成
            $tempClient = new SquareClient([
                'accessToken' => SQUARE_ACCESS_TOKEN,
                'environment' => SQUARE_ENVIRONMENT === 'production' ? Environment::PRODUCTION : Environment::SANDBOX,
                'timeout' => 5,
                'connectTimeout' => 3,
                'curlOptions' => [
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CAINFO => __DIR__ . '/../certificates/cacert.pem',
                    CURLOPT_VERBOSE => true
                ]
            ]);
            
            $locationsApi = $tempClient->getLocationsApi();
            
            $this->logger->logMessage("API接続テスト - リクエスト送信開始", 'INFO');
            $apiStartTime = microtime(true);
            
            try {
                $result = $locationsApi->listLocations();
                $apiEndTime = microtime(true);
                $apiCallTime = round(($apiEndTime - $apiStartTime) * 1000);
                
                $this->logger->logMessage("API接続テスト - リクエスト完了 ({$apiCallTime}ms)", 'INFO');
                
                if ($result->isSuccess()) {
                    $locations = $result->getResult()->getLocations();
                    
                    if (empty($locations)) {
                        $this->logger->logMessage("API接続テスト - 接続成功だが店舗情報なし", 'WARNING');
                        return [
                            'success' => true,
                            'message' => 'Connection successful but no locations found'
                        ];
                    }
                    
                    $this->logger->logMessage("API接続テスト - 成功 ({$apiCallTime}ms)", 'INFO');
                    return [
                        'success' => true,
                        'message' => 'Connection successful',
                        'response_time_ms' => $apiCallTime
                    ];
                } else {
                    $errors = $result->getErrors();
                    $errorMessage = "API Error: " . json_encode($errors);
                    $this->logger->logMessage("API接続テスト - APIエラー: {$errorMessage}", 'ERROR');
                    return false;
                }
            } catch (\Square\Exceptions\ApiException $e) {
                $apiEndTime = microtime(true);
                $apiCallTime = round(($apiEndTime - $apiStartTime) * 1000);
                
                $errorMessage = "Square API Exception during connection test: " . $e->getMessage();
                $this->logger->logMessage("API接続テスト - 例外発生 ({$apiCallTime}ms): {$errorMessage}", 'ERROR');
                
                if (method_exists($e, 'getResponseBody')) {
                    $responseBody = $e->getResponseBody();
                    if ($responseBody) {
                        $this->logger->logMessage("API Error Response Body: " . json_encode($responseBody), 'ERROR');
                    }
                }
                
                return false;
            }
        } catch (Exception $e) {
            $errorMessage = "一般例外 (testConnection): " . $e->getMessage();
            $this->logger->logMessage($errorMessage, 'ERROR');
            return false;
        }
    }
    
    /**
     * 設定情報を取得
     * 
     * @return array 設定情報
     */
    protected static function getSquareSettings() {
        static $settings = null;
        if ($settings !== null) return $settings;
        
        $regPath = realpath(__DIR__ . '/../../admin/adminsetting_registrer.php');
        if (!$regPath || !file_exists($regPath)) return $settings = [];
        
        if (!isset($GLOBALS['settingsFilePath']) || empty($GLOBALS['settingsFilePath'])) {
            $GLOBALS['settingsFilePath'] = dirname($regPath) . '/adminpagesetting/adminsetting.json';
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
        }
        
        return $settings = [];
    }
} 