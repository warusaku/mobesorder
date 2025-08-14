<?php
/**
 * 営業時間管理クラス
 * 営業時間設定の読み込みと時間判定を行う
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

class OpenCloseManager {
    private $rootPath;
    private $settingsFilePath;
    private $settings = null;
    private $logFile;
    private $maxLogSize = 307200; // 300KB
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->rootPath = realpath(__DIR__ . '/../../..');
        $this->settingsFilePath = $this->rootPath . '/admin/adminpagesetting/adminsetting.json';
        $this->logFile = $this->rootPath . '/logs/openclose_manager.log';
        
        // ログディレクトリの確認
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logMessage("OpenCloseManager初期化");
        $this->loadSettings();
    }
    
    /**
     * ログローテーション処理
     */
    private function checkLogRotation() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $fileSize = filesize($this->logFile);
        if ($fileSize > $this->maxLogSize) {
            // ファイルのサイズが上限を超えた場合、内容の20%を残して切り捨て
            $contents = file_get_contents($this->logFile);
            $keepSize = intval($this->maxLogSize * 0.2);
            $contents = substr($contents, -$keepSize);
            
            // 新しいログファイルを作成
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログローテーション実行: ファイルサイズ " . round($fileSize / 1024, 2) . "KB が上限の " . round($this->maxLogSize / 1024, 2) . "KB を超過\n";
            file_put_contents($this->logFile, $message . $contents);
        }
    }
    
    /**
     * ログメッセージを記録
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル (INFO, WARNING, ERROR)
     */
    private function logMessage($message, $level = 'INFO') {
        $this->checkLogRotation();
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 設定ファイルを読み込む
     * 
     * @return bool 読み込み成功したかどうか
     */
    private function loadSettings() {
        $this->logMessage("設定ファイル読み込み開始: " . $this->settingsFilePath);
        
        if (!file_exists($this->settingsFilePath)) {
            $this->logMessage("設定ファイルが存在しません", 'WARNING');
            return false;
        }
        
        $jsonContent = file_get_contents($this->settingsFilePath);
        if ($jsonContent === false) {
            $this->logMessage("設定ファイルの読み込みに失敗しました", 'ERROR');
            return false;
        }
        
        $settings = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMessage("JSONのデコードに失敗しました: " . json_last_error_msg(), 'ERROR');
            return false;
        }
        
        $this->settings = $settings;
        $this->logMessage("設定ファイルの読み込みが完了しました");
        return true;
    }
    
    /**
     * デフォルト営業時間設定を取得
     * 
     * @return array|null 営業時間設定
     */
    public function getDefaultOpeningHours() {
        if ($this->settings === null) {
            if (!$this->loadSettings()) {
                return null;
            }
        }
        
        if (!isset($this->settings['open_close'])) {
            $this->logMessage("open_close設定が見つかりません", 'WARNING');
            return null;
        }
        
        return $this->settings['open_close'];
    }
    
    /**
     * カテゴリIDに基づいて営業状態を判定
     * 
     * @param string $categoryId カテゴリID
     * @param object $db Database接続オブジェクト
     * @return bool 営業中かどうか
     */
    public function isCategoryOpen($categoryId, $db) {
        // カテゴリ情報を取得
        try {
            $category = $db->selectOne(
                "SELECT * FROM category_descripter WHERE category_id = ?",
                [$categoryId]
            );
            
            if (!$category) {
                $this->logMessage("カテゴリが見つかりません: " . $categoryId, 'WARNING');
                return false;
            }
            
            // カテゴリが無効な場合
            if ($category['is_active'] != 1) {
                return false;
            }
            
            // 現在の時刻を取得
            $now = new DateTime();
            $currentTime = $now->format('H:i:s');
            $this->logMessage("現在時刻: " . $currentTime . ", カテゴリ: " . $category['category_name']);
            
            // デフォルト営業時間を使用するか
            $useDefault = $category['default_order_time'] === null || $category['default_order_time'] == 1;
            
            if ($useDefault) {
                // デフォルト営業時間を使用
                return $this->checkDefaultOpeningHours($currentTime);
            } else {
                // カテゴリ固有の営業時間を使用
                return $this->checkCategoryOpeningHours($category, $currentTime);
            }
        } catch (Exception $e) {
            $this->logMessage("カテゴリの営業状態チェック中にエラーが発生: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * デフォルト営業時間に基づいて営業状態を判定
     * 
     * @param string $currentTime 現在時刻 (H:i:s)
     * @return bool 営業中かどうか
     */
    private function checkDefaultOpeningHours($currentTime) {
        $openingHours = $this->getDefaultOpeningHours();
        if (!$openingHours) {
            $this->logMessage("デフォルト営業時間設定が取得できません", 'WARNING');
            return true; // 設定がない場合は常に営業中とみなす
        }
        
        // 休業日チェック
        $daysOff = isset($openingHours['Days off']) ? $openingHours['Days off'] : [];
        $dayOfWeek = date('l'); // 曜日を英語で取得（Monday, Tuesdayなど）
        if (in_array($dayOfWeek, $daysOff)) {
            $this->logMessage("本日は休業日です: " . $dayOfWeek);
            return false;
        }
        
        // 営業時間チェック
        $defaultOpen = isset($openingHours['default_open']) ? $openingHours['default_open'] : '00:00';
        $defaultClose = isset($openingHours['default_close']) ? $openingHours['default_close'] : '00:00';
        
        // 数値化して比較（分単位）
        $openParts  = explode(':', $defaultOpen);
        $closeParts = explode(':', $defaultClose);
        $nowParts   = explode(':', $currentTime);

        if (count($openParts) < 2 || count($closeParts) < 2 || count($nowParts) < 2) {
            $this->logMessage("営業時間フォーマットが不正です", 'ERROR');
            return true;
        }

        $openMin  = intval($openParts[0]) * 60 + intval($openParts[1]);
        $closeMin = intval($closeParts[0]) * 60 + intval($closeParts[1]);
        $nowMin   = intval($nowParts[0]) * 60 + intval($nowParts[1]);

        // 24時間営業
        if ($openMin === $closeMin) {
            return true;
        }

        // 同日内
        if ($openMin < $closeMin) {
            return ($nowMin >= $openMin && $nowMin < $closeMin);
        }

        // 日またぎ (open > close)
        return ($nowMin >= $openMin || $nowMin < $closeMin);
    }
    
    /**
     * カテゴリ固有の営業時間に基づいて営業状態を判定
     * 
     * @param array $category カテゴリ情報
     * @param string $currentTime 現在時刻 (H:i:s)
     * @return bool 営業中かどうか
     */
    private function checkCategoryOpeningHours($category, $currentTime) {
        // カテゴリのラストオーダー時間と営業開始時間をチェック
        $lastOrderTime = $category['last_order_time'];
        $openOrderTime = $category['open_order_time'];
        
        if (!$lastOrderTime && !$openOrderTime) {
            $this->logMessage("カテゴリの営業時間設定がありません: " . $category['category_name']);
            return true; // 設定がない場合は常に営業中
        }
        
        // 現在の時刻オブジェクト
        $nowTime = DateTime::createFromFormat('H:i:s', $currentTime);
        if (!$nowTime) {
            $this->logMessage("現在時刻のパースに失敗しました: " . $currentTime, 'ERROR');
            return true; // エラーの場合は営業中と仮定
        }
        $nowTimeStr = $nowTime->format('H:i');
        
        // 分単位に変換
        $nowParts = explode(':', $currentTime);
        $nowMin = intval($nowParts[0])*60 + intval($nowParts[1]);

        $openMin = null;
        if ($openOrderTime) {
            $p = explode(':', $openOrderTime);
            $openMin = intval($p[0])*60 + intval($p[1]);
        }
        $closeMin = null;
        if ($lastOrderTime) {
            $p = explode(':', $lastOrderTime);
            $closeMin = intval($p[0])*60 + intval($p[1]);
        }

        // 営業開始判定
        if ($openMin !== null) {
            if ($nowMin < $openMin) {
                $this->logMessage("営業開始時間前です: $nowMin < $openMin", 'INFO');
                return false;
            }
        }

        // 終了判定 （日またぎ考慮）
        if ($closeMin !== null) {
            if ($openMin !== null && $openMin > $closeMin) {
                // 日またぎパターン
                if (!($nowMin >= $openMin || $nowMin < $closeMin)) {
                    $this->logMessage("ラストオーダー時間後です（日またぎ）", 'INFO');
                    return false;
                }
            } else {
                // 通常
                if ($nowMin >= $closeMin) {
                    $this->logMessage("ラストオーダー時間後です: $nowMin >= $closeMin", 'INFO');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * 次の営業開始時間を取得
     * 
     * @return string|null 次の営業開始時間 (HH:mm形式) またはnull
     */
    public function getNextOpeningTime() {
        $openingHours = $this->getDefaultOpeningHours();
        if (!$openingHours) {
            return null;
        }
        
        $defaultOpen = isset($openingHours['default_open']) ? $openingHours['default_open'] : '00:00';
        return $defaultOpen;
    }
    
    /**
     * 全カテゴリの営業状態を取得
     * 
     * @param object $db Database接続オブジェクト
     * @return array カテゴリID => 営業状態(boolean)の連想配列
     */
    public function getAllCategoriesOpenStatus($db) {
        try {
            $categories = $db->select(
                "SELECT * FROM category_descripter WHERE is_active = 1"
            );
            
            $result = [];
            foreach ($categories as $category) {
                $categoryId = $category['category_id'];
                $isOpen = $this->isCategoryOpen($categoryId, $db);
                $result[$categoryId] = $isOpen;
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logMessage("全カテゴリの営業状態取得中にエラーが発生: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}

// =============================
// エンドポイント処理
// =============================
// 直接このファイルが呼び出された場合のみ実行（ライブラリとして読み込まれた場合は無視）
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // CORS とレスポンスヘッダー
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    // 設定ファイルとDBライブラリを読み込み
    $rootDir = realpath(__DIR__ . '/../../..'); // fgsquare ディレクトリ
    $configPath = $rootDir . '/api/config/config.php';
    $dbLibPath  = $rootDir . '/api/lib/Database.php';

    if (!file_exists($configPath) || !file_exists($dbLibPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'サーバ設定が不完全です'
        ]);
        exit;
    }

    require_once $configPath;
    require_once $dbLibPath;

    // DBインスタンス取得
    try {
        $db = Database::getInstance();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'データベース接続に失敗しました'
        ]);
        exit;
    }

    // OpenCloseManager インスタンス
    $ocm = new OpenCloseManager();

    // パラメータ取得
    $categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
    $action     = isset($_GET['action']) ? $_GET['action'] : null;

    try {
        if ($action === 'next_open') {
            $next = $ocm->getNextOpeningTime();
            echo json_encode([
                'success'        => true,
                'next_open_time' => $next
            ]);
            exit;
        }

        if ($categoryId) {
            $isOpen = $ocm->isCategoryOpen($categoryId, $db);
            echo json_encode([
                'success' => true,
                'is_open' => $isOpen
            ]);
            exit;
        }

        // パラメータが不足している場合
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => '必要なパラメータが不足しています'
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => '処理中にエラーが発生しました: ' . $e->getMessage()
        ]);
        exit;
    }
} 