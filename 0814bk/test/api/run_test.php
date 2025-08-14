<?php
/**
 * RTSP_Reader Test Framework - Run Test API (Lolipop)
 * 
 * 単一テスト実行API
 */

// ヘッダー設定
header('Content-Type: application/json');

// インクルードパス設定
$includePath = dirname(__DIR__) . '/includes';
set_include_path(get_include_path() . PATH_SEPARATOR . $includePath);

// 必要なライブラリの読み込み
require_once 'test_logger.php';
require_once 'test_runner.php';

try {
    // POSTリクエストのみ受け付け
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTリクエストのみ受け付けています');
    }
    
    // リクエストボディの取得
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if (!$data) {
        throw new Exception('無効なJSONデータです');
    }
    
    // 必須パラメータのチェック
    if (!isset($data['module']) || !isset($data['test'])) {
        throw new Exception('モジュール名とテスト名は必須です');
    }
    
    $moduleName = $data['module'];
    $testName = $data['test'];
    $params = $data['params'] ?? [];
    
    // ロガーの初期化
    $logFile = dirname(__DIR__) . '/logs/test_' . date('Y-m-d') . '.log';
    $logger = new TestLogger($logFile);
    
    // テストランナーの初期化
    $testRunner = new TestRunner($logger);
    
    // モジュールの読み込み
    $modulePath = dirname(__DIR__) . '/modules/' . $moduleName . '.php';
    if (!file_exists($modulePath)) {
        throw new Exception("テストモジュールが見つかりません: {$moduleName}");
    }
    
    require_once $modulePath;
    
    // モジュールクラス名の特定
    $className = str_replace('_', '', ucwords($moduleName, '_')) . 'Module';
    
    // データベース接続
    $dbConnection = null;
    if (strpos($moduleName, 'db_') === 0 || strpos($moduleName, 'e2e_') === 0) {
        // データベースモジュールの場合はDB接続を初期化
        $dbConfig = [
            'host' => 'mysql323.phy.lolipop.lan',
            'dbname' => 'LAA1207717-rtspreader',
            'username' => 'LAA1207717',
            'password' => 'mijeos12345'
        ];
        
        try {
            $dsn = "mysql:host={$dbConfig['host']};charset=utf8mb4";
            $dbConnection = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $logger->error('データベース接続エラー', ['error' => $e->getMessage()]);
        }
    }
    
    // モジュールのインスタンス化
    $module = new $className($logger, $dbConnection);
    
    // テストモジュールの登録
    $testRunner->registerTestModule($moduleName, $module);
    
    // テスト実行
    $result = $testRunner->runTest($moduleName, $testName, $params);
    
    // 成功レスポンス
    echo json_encode([
        'status' => 'success',
        'result' => $result
    ]);
} catch (Exception $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 
 
 
 
 