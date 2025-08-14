<?php
/**
 * 管理者設定ファイル読み書きエンドポイント
 * adminsetting.jsonを単一責任として管理する
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
$adminPath = __DIR__;
$settingsFilePath = $adminPath . '/adminpagesetting/adminsetting.json';
$logFile = $rootPath . '/logs/adminsetting_registrer.log';
$maxLogSize = 307200; // 300KB

// ログディレクトリの存在確認と作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ログローテーション処理
if (!function_exists('checkLogRotation')) {
    function checkLogRotation() {
        global $logFile, $maxLogSize;
        
        if (!file_exists($logFile)) {
            return;
        }
        
        $fileSize = filesize($logFile);
        if ($fileSize > $maxLogSize) {
            // ファイルのサイズが上限を超えた場合、内容の20%を残して切り捨て
            $contents = file_get_contents($logFile);
            $keepSize = intval($maxLogSize * 0.2);
            $contents = substr($contents, -$keepSize);
            
            // 新しいログファイルを作成
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログローテーション実行: ファイルサイズ " . round($fileSize / 1024, 2) . "KB が上限の " . round($maxLogSize / 1024, 2) . "KB を超過\n";
            file_put_contents($logFile, $message . $contents);
        }
    }
}

// ログ関数
if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        global $logFile;
        
        checkLogRotation();
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

// レスポンスをJSON形式で返す関数
function respondJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 設定ファイルを読み込む
function loadSettings() {
    global $settingsFilePath;
    
    logMessage("設定ファイル読み込み開始: " . $settingsFilePath);
    
    if (!file_exists($settingsFilePath)) {
        logMessage("設定ファイルが存在しません。新規作成します。", 'WARNING');
        $defaultSettings = [
            "product_display_util" => [
                "directlink_baseURL" => "https://test-mijeos.but.jp/fgsquare/order"
            ],
            "open_close" => [
                "default_open" => "10:00",
                "default_close" => "22:00",
                "interval" => [],
                "Days off" => [],
                "Restrict individual" => "true"
            ]
        ];
        
        // ディレクトリが存在しない場合は作成
        $dir = dirname($settingsFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                logMessage("ディレクトリの作成に失敗しました: " . $dir, 'ERROR');
                return false;
            }
        }
        
        // デフォルト設定を保存
        $result = file_put_contents($settingsFilePath, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($result === false) {
            logMessage("デフォルト設定の保存に失敗しました", 'ERROR');
            return false;
        }
        
        logMessage("デフォルト設定を作成しました");
        return $defaultSettings;
    }
    
    // 既存のファイルを読み込み
    $jsonContent = file_get_contents($settingsFilePath);
    if ($jsonContent === false) {
        logMessage("設定ファイルの読み込みに失敗しました", 'ERROR');
        return false;
    }
    
    $settings = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSONのデコードに失敗しました: " . json_last_error_msg(), 'ERROR');
        return false;
    }
    
    logMessage("設定ファイルの読み込みが完了しました");
    return $settings;
}

// 設定ファイルを保存する
function saveSettings($settings) {
    global $settingsFilePath;
    
    logMessage("設定ファイル保存開始");
    
    // ディレクトリが存在しない場合は作成
    $dir = dirname($settingsFilePath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            logMessage("ディレクトリの作成に失敗しました: " . $dir, 'ERROR');
            return false;
        }
    }
    
    // 設定を保存
    $result = file_put_contents($settingsFilePath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result === false) {
        logMessage("設定ファイルの保存に失敗しました", 'ERROR');
        return false;
    }
    
    logMessage("設定ファイルを保存しました");
    return true;
}

// 内部呼び出しの場合（設定取得専用として include された場合）はスキップ
if(!(defined('ADMIN_SETTING_INTERNAL_CALL') && ADMIN_SETTING_INTERNAL_CALL)){
    logMessage("リクエスト受信: " . $_SERVER['REQUEST_METHOD']);

    // リクエストメソッドに応じた処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // 設定を取得
            $settings = loadSettings();
            if ($settings === false) {
                respondJson(['success' => false, 'message' => '設定の読み込みに失敗しました'], 500);
            }
            
            // セクションが指定されていればそのセクションのみ返す
            if (isset($_GET['section'])) {
                $section = $_GET['section'];
                logMessage("セクション取得: " . $section);
                
                if (isset($settings[$section])) {
                    respondJson(['success' => true, 'settings' => $settings[$section]]);
                } else {
                    logMessage("指定されたセクションが見つかりません: " . $section, 'WARNING');
                    respondJson(['success' => false, 'message' => '指定されたセクションが見つかりません'], 404);
                }
            }
            
            // 全ての設定を返す
            respondJson(['success' => true, 'settings' => $settings]);
            break;
            
        case 'POST':
            // 設定を更新
            $postData = file_get_contents('php://input');
            $inputSettings = json_decode($postData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || empty($inputSettings)) {
                logMessage("無効なJSONデータを受信しました: " . json_last_error_msg(), 'ERROR');
                respondJson(['success' => false, 'message' => '無効なJSONデータです'], 400);
            }
            
            // 現在の設定を読み込み
            $currentSettings = loadSettings();
            if ($currentSettings === false) {
                respondJson(['success' => false, 'message' => '現在の設定の読み込みに失敗しました'], 500);
            }
            
            // セクションが指定されていればそのセクションのみ更新
            if (isset($_GET['section'])) {
                $section = $_GET['section'];
                logMessage("セクション更新: " . $section);
                
                // 指定されたセクションの更新
                $currentSettings[$section] = $inputSettings;
            } else {
                // 全体を置き換え
                $currentSettings = $inputSettings;
            }
            
            // 設定を保存
            if (saveSettings($currentSettings)) {
                respondJson(['success' => true, 'message' => '設定を保存しました']);
            } else {
                respondJson(['success' => false, 'message' => '設定の保存に失敗しました'], 500);
            }
            break;
            
        default:
            logMessage("サポートされていないメソッド: " . $_SERVER['REQUEST_METHOD'], 'WARNING');
            respondJson(['success' => false, 'message' => 'サポートされていないメソッドです'], 405);
            break;
    }
} 