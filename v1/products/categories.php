<?php
/**
 * 商品カテゴリ一覧を提供するAPIエンドポイント
 */

// 本番環境用設定 - エラーはログに記録し、画面には表示しない
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../../../logs/php_errors.log');

// カレントディレクトリを出力（デバッグ用）
$currentDir = dirname(__FILE__);
$currentRealPath = realpath($currentDir);
$rootPath = realpath(__DIR__ . '/../../../');

// デバッグ情報の出力 - error_logに変更
error_log("デバッグ情報: カレントディレクトリ: " . $currentDir);
error_log("デバッグ情報: 現在のフルパス: " . $currentRealPath);
error_log("デバッグ情報: ルートパス: " . $rootPath);
error_log("デバッグ情報: ログファイルパス: " . $rootPath . '/logs/CategoryAPI.log');

// ログファイル設定（絶対パスに修正）
$logFile = $rootPath . '/logs/CategoryAPI.log';
$logRotationHours = 48; // ログローテーション（時間）

// ログファイルディレクトリの確認
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
    error_log("デバッグ情報: ログディレクトリを作成: $logDir");
}

// ログディレクトリのパーミッションをチェック
$dirWritable = is_writable($logDir);
error_log("デバッグ情報: ログディレクトリの書き込み権限: " . ($dirWritable ? "あり" : "なし"));

// 詳細なエラー情報キャプチャ用のカスタムエラーハンドラ
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorType = '';
    switch ($errno) {
        case E_ERROR: $errorType = 'E_ERROR'; break;
        case E_WARNING: $errorType = 'E_WARNING'; break;
        case E_PARSE: $errorType = 'E_PARSE'; break;
        case E_NOTICE: $errorType = 'E_NOTICE'; break;
        case E_CORE_ERROR: $errorType = 'E_CORE_ERROR'; break;
        case E_CORE_WARNING: $errorType = 'E_CORE_WARNING'; break;
        case E_COMPILE_ERROR: $errorType = 'E_COMPILE_ERROR'; break;
        case E_COMPILE_WARNING: $errorType = 'E_COMPILE_WARNING'; break;
        case E_USER_ERROR: $errorType = 'E_USER_ERROR'; break;
        case E_USER_WARNING: $errorType = 'E_USER_WARNING'; break;
        case E_USER_NOTICE: $errorType = 'E_USER_NOTICE'; break;
        case E_STRICT: $errorType = 'E_STRICT'; break;
        case E_RECOVERABLE_ERROR: $errorType = 'E_RECOVERABLE_ERROR'; break;
        case E_DEPRECATED: $errorType = 'E_DEPRECATED'; break;
        case E_USER_DEPRECATED: $errorType = 'E_USER_DEPRECATED'; break;
    }
    
    $message = "$errorType [$errno]: $errstr in $errfile on line $errline";
    error_log($message);
    
    // CategoryAPI.logにも記録
    global $logFile;
    if (function_exists('logCategoryAPI')) {
        logCategoryAPI("PHP Error: " . $message, 'ERROR');
    }
    
    // エラーを通常のエラーハンドラにも渡す
    return false;
}

// カスタムエラーハンドラを設定
set_error_handler("customErrorHandler");

// 例外ハンドラも設定
function customExceptionHandler($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage() . 
               " in " . $exception->getFile() . 
               " on line " . $exception->getLine() . 
               "\nStack trace: " . $exception->getTraceAsString();
    error_log($message);
    
    // CategoryAPI.logにも記録
    global $logFile;
    if (function_exists('logCategoryAPI')) {
        logCategoryAPI("Uncaught Exception: " . $exception->getMessage(), 'ERROR');
        logCategoryAPI("例外発生場所: " . $exception->getFile() . " on line " . $exception->getLine(), 'ERROR');
        logCategoryAPI("Stack trace: " . $exception->getTraceAsString(), 'ERROR');
    }
    
    // JSON形式でエラーレスポンスを返す
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode([
        'success' => true,
        'categories' => [
            ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'is_fallback' => true
    ]);
    
    exit(1);
}

// カスタム例外ハンドラを設定
set_exception_handler("customExceptionHandler");

// ログローテーションのチェック
if (file_exists($logFile)) {
    $fileTime = filemtime($logFile);
    $hoursDiff = (time() - $fileTime) / 3600;
    
    if ($hoursDiff > $logRotationHours) {
        $backupFile = $logFile . '.' . date('Y-m-d_H-i-s', $fileTime);
        @rename($logFile, $backupFile);
        
        // ログファイル作成開始をログに記録
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] [INFO] ログファイル作成開始 - ローテーション実行（前回ログ: $backupFile）\n";
        @file_put_contents($logFile, $message);
    }
}

// ログ記録関数
function logCategoryAPI($message, $level = 'INFO') {
    global $logFile, $dirWritable;
    $timestamp = date('Y-m-d H:i:s');
    $requestId = isset($_SERVER['REQUEST_ID']) ? $_SERVER['REQUEST_ID'] : substr(md5(uniqid()), 0, 8);
    $_SERVER['REQUEST_ID'] = $requestId; // 同一リクエスト内で一貫したIDを使用
    
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logMessage = "[$timestamp] [$level] [REQ:$requestId] [IP:$clientIp] $message\n";
    
    // ディレクトリが書き込み可能な場合のみファイルに書き込む
    $result = false;
    if ($dirWritable) {
        $result = @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    // PHPのエラーログにも記録（ファイル書き込みに失敗した場合のバックアップ）
    if (!$result && function_exists('error_log')) {
        error_log("CategoryAPI: $logMessage");
    }
    
    // 詳細なリクエスト情報（DEBUG時のみ）
    if ($level === 'DEBUG' || $level === 'ERROR') {
        $details = "[$timestamp] [$level] [REQ:$requestId] UserAgent: $userAgent\n";
        @file_put_contents($logFile, $details, FILE_APPEND);
        if (function_exists('error_log')) {
            error_log("CategoryAPI: $details");
        }
    }
}

// リクエスト開始のログ記録
$startTime = microtime(true);
logCategoryAPI("========== カテゴリAPIリクエスト開始 ==========");
logCategoryAPI("リクエストメソッド: " . $_SERVER['REQUEST_METHOD'] . ", パス: " . $_SERVER['REQUEST_URI']);
logCategoryAPI("ログファイルパス: " . $logFile);

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// デバッグモード
$debugMode = defined('DEBUG_MODE') && DEBUG_MODE;
logCategoryAPI("デバッグモード: " . ($debugMode ? "有効" : "無効"));

// 必要なライブラリを読み込み
try {
    logCategoryAPI("ライブラリ読み込み開始");
    
    // 設定ファイルのロード確認
    $configFound = false;
    $possibleConfigPaths = [
        $rootPath . '/config.php',
        $rootPath . '/api/config.php',
        $rootPath . '/api/config/config.php',
        $rootPath . '/api/lib/config.php'
    ];
    
    foreach ($possibleConfigPaths as $configPath) {
        logCategoryAPI("設定ファイルの確認: " . $configPath);
        if (file_exists($configPath)) {
            logCategoryAPI("設定ファイルが見つかりました: " . $configPath);
            require_once $configPath;
            $configFound = true;
            break;
        }
    }
    
    if (!$configFound) {
        logCategoryAPI("設定ファイルが見つかりませんでした。各候補パスが存在しません。", "ERROR");
        // 現在のディレクトリ構造を出力して診断に役立てる
        logCategoryAPI("APIディレクトリの内容: " . shell_exec("ls -la " . $rootPath . "/api"), "DEBUG");
        logCategoryAPI("config候補ディレクトリの内容: " . shell_exec("ls -la " . $rootPath . "/api/config"), "DEBUG");
        throw new Exception("設定ファイルが見つかりません。サーバー構成を確認してください。");
    }
    
    logCategoryAPI("データベース設定の確認");
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        logCategoryAPI("データベース設定が不完全です。設定を確認してください。", "ERROR");
        throw new Exception("データベース設定が不完全です。設定を確認してください。");
    }
    
    logCategoryAPI("データベース設定: HOST=" . (defined('DB_HOST') ? DB_HOST : 'undefined') . 
                 ", NAME=" . (defined('DB_NAME') ? DB_NAME : 'undefined'));
    
    // ライブラリファイルの存在確認
    $requiredLibs = [
        'Database.php' => $rootPath . '/api/lib/Database.php',
        'Utils.php' => $rootPath . '/api/lib/Utils.php',
        'ProductService.php' => $rootPath . '/api/lib/ProductService.php',
        'SquareService.php' => $rootPath . '/api/lib/SquareService.php'
    ];
    
    foreach ($requiredLibs as $lib => $path) {
        logCategoryAPI("ライブラリファイルの確認: " . $lib . " - パス: " . $path);
        if (!file_exists($path)) {
            logCategoryAPI("ライブラリファイルが見つかりません: " . $path, "ERROR");
            throw new Exception("必要なライブラリファイルが見つかりません: " . $lib);
        }
    }
    
    // ライブラリをロード
    require_once $rootPath . '/api/lib/Database.php';
    require_once $rootPath . '/api/lib/Utils.php';
    require_once $rootPath . '/api/lib/SquareService.php';
    require_once $rootPath . '/api/lib/ProductService.php';

    logCategoryAPI("ライブラリ読み込み完了");
    
    // データベース接続テスト
    logCategoryAPI("データベース接続テスト開始");
    try {
        $testDb = Database::getInstance();
        logCategoryAPI("データベース接続オブジェクト取得成功");
        
        $testQuery = "SELECT 1 AS connection_test";
        logCategoryAPI("接続テストクエリ実行: " . $testQuery);
        $testResult = $testDb->selectOne($testQuery);
        
        if ($testResult && isset($testResult['connection_test']) && $testResult['connection_test'] == 1) {
            logCategoryAPI("データベース接続テスト成功: " . json_encode($testResult));
        } else {
            logCategoryAPI("データベース接続テスト結果が期待通りではありません: " . json_encode($testResult), "WARNING");
        }
    } catch (Exception $dbTestError) {
        logCategoryAPI("データベース接続テストエラー: " . $dbTestError->getMessage(), "ERROR");
        logCategoryAPI("データベース接続テストエラースタックトレース: " . $dbTestError->getTraceAsString(), "ERROR");
        // エラーを記録するがスローはしない - 続行を試みる
    }
    
    // ログ出力
    if ($debugMode) {
        Utils::log("カテゴリAPIリクエスト開始", 'DEBUG', 'CategoryAPI');
    }

    // ProductServiceを初期化
    logCategoryAPI("ProductService初期化開始");
    try {
        // クラスの存在チェック
        if (!class_exists('ProductService')) {
            logCategoryAPI("ProductServiceクラスが存在しません", "ERROR");
            throw new Exception("ProductServiceクラスの定義が見つかりません");
        }
        
        logCategoryAPI("ProductServiceインスタンス生成開始");
        $productService = new ProductService();
        logCategoryAPI("ProductServiceインスタンス生成完了");
        
        // ProductServiceの各メソッドが存在するか確認
        if (!method_exists($productService, 'getCategories')) {
            logCategoryAPI("ProductService::getCategoriesメソッドが存在しません", "ERROR");
            throw new Exception("ProductServiceクラスに必要なメソッドがありません");
        }
        
        logCategoryAPI("ProductService初期化完了");
    } catch (Exception $e) {
        logCategoryAPI("ProductService初期化エラー: " . $e->getMessage(), 'ERROR');
        logCategoryAPI("エラーファイル: " . $e->getFile() . " on line " . $e->getLine(), 'ERROR');
        logCategoryAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
        error_log("ProductService初期化エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        // エラーが発生した場合でも最小限のデータを返す
        echo json_encode([
            'success' => true,
            'categories' => [
                ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true,
            'error' => 'ProductService initialization failed'
        ]);
        exit;
    }

    try {
        // カテゴリ一覧を取得（認証なしでアクセス可能に）
        logCategoryAPI("カテゴリ一覧取得処理開始");
        
        // メモリ使用量のログ記録
        $memoryBefore = memory_get_usage();
        logCategoryAPI("カテゴリ取得前メモリ使用量: " . number_format($memoryBefore / 1024 / 1024, 2) . " MB");
        
        try {
            $categories = $productService->getCategories();
            logCategoryAPI("カテゴリ一覧取得成功: " . count($categories) . "件");
            
            // メモリ使用量のログ記録
            $memoryAfter = memory_get_usage();
            logCategoryAPI("カテゴリ取得後メモリ使用量: " . number_format($memoryAfter / 1024 / 1024, 2) . " MB");
            logCategoryAPI("カテゴリ取得でのメモリ増加: " . number_format(($memoryAfter - $memoryBefore) / 1024 / 1024, 2) . " MB");
            
            // レスポンスを生成
            $response = [
                'success' => true,
                'categories' => $categories,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // レスポンスを返す前にサイズを確認
            $jsonResponse = json_encode($response);
            if ($jsonResponse === false) {
                logCategoryAPI("JSONエンコードエラー: " . json_last_error_msg(), 'ERROR');
                throw new Exception("JSONエンコードに失敗しました: " . json_last_error_msg());
            }
            
            logCategoryAPI("JSONレスポンス生成: " . strlen($jsonResponse) . "バイト");
            logCategoryAPI("最初の100文字: " . substr($jsonResponse, 0, 100));
            echo $jsonResponse;
            
            if ($debugMode) {
                Utils::log("カテゴリAPI成功レスポンス: " . count($categories) . "件", 'DEBUG', 'CategoryAPI');
            }
        } catch (Exception $innerE) {
            logCategoryAPI("カテゴリ取得処理中の例外: " . $innerE->getMessage(), 'ERROR');
            logCategoryAPI("例外発生場所: " . $innerE->getFile() . " on line " . $innerE->getLine(), 'ERROR');
            logCategoryAPI("内部例外スタックトレース: " . $innerE->getTraceAsString(), 'ERROR');
            
            // 発生場所から関連するコードを特定
            $errorFile = $innerE->getFile();
            $errorLine = $innerE->getLine();
            if (file_exists($errorFile)) {
                $fileContent = file($errorFile);
                if ($fileContent && isset($fileContent[$errorLine - 1])) {
                    $errorCode = trim($fileContent[$errorLine - 1]);
                    logCategoryAPI("エラー発生コード: " . $errorCode, 'ERROR');
                }
            }
            
            throw $innerE; // 外側のcatchブロックで処理
        }
    } catch (Exception $e) {
        // エラーログ
        logCategoryAPI("カテゴリAPI内部エラー: " . $e->getMessage(), 'ERROR');
        logCategoryAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
        if (function_exists('error_log')) {
            error_log("CategoryAPI内部エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        
        // エラー発生時もデフォルトカテゴリを返す
        echo json_encode([
            'success' => true,
            'categories' => [
                ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true,
            'error_message' => 'Error fetching categories: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    // 致命的なエラー（ライブラリの読み込み失敗など）
    $errorMsg = "カテゴリAPI致命的エラー: " . $e->getMessage();
    logCategoryAPI($errorMsg, 'ERROR');
    logCategoryAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
    
    if (function_exists('error_log')) {
        error_log($errorMsg . "\n" . $e->getTraceAsString());
    }
    
    // 致命的なエラー時もデフォルトカテゴリを返す
    echo json_encode([
        'success' => true,
        'categories' => [
            ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'is_fallback' => true,
        'critical_error' => $errorMsg
    ]);
}

// リクエスト終了のログ記録
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
logCategoryAPI("========== カテゴリAPIリクエスト終了 - 実行時間: " . $executionTime . "ms =========="); 