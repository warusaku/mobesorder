<?php
/**
 * 商品一覧を提供するAPIエンドポイント
 */

// 本番環境用設定 - エラーはログに記録し、画面には表示しない
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../../../logs/php_errors.log');

// カレントディレクトリを取得
$currentDir = dirname(__FILE__);
$rootPath = realpath(__DIR__ . '/../../../');

// エラーログ記録用関数
function logProductAPI($message, $level = 'INFO') {
    $logFile = dirname(__FILE__) . '/../../../logs/ProductAPI.log';
    $timestamp = date('Y-m-d H:i:s');
    $requestId = substr(md5(uniqid()), 0, 8);
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logMessage = "[$timestamp] [$level] [REQ:$requestId] [IP:$clientIp] $message\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // PHPのエラーログにもバックアップとして記録
    if (function_exists('error_log')) {
        error_log("ProductAPI: $logMessage");
    }
}

// リクエスト開始を記録
logProductAPI("======== 商品API開始 - " . $_SERVER['REQUEST_URI'] . " ========");

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 必要なライブラリを読み込み
try {
    logProductAPI("ライブラリ読み込み開始");

    // 設定ファイルの読み込み
    $configFile = $rootPath . '/api/config/config.php';
    if (file_exists($configFile)) {
        logProductAPI("設定ファイル読み込み: " . $configFile);
        require_once $configFile;
    } else {
        throw new Exception("設定ファイルが見つかりません: " . $configFile);
    }

    // ライブラリファイルの存在確認
    $requiredLibs = [
        'Database.php' => $rootPath . '/api/lib/Database.php',
        'Utils.php' => $rootPath . '/api/lib/Utils.php',
        'SquareService.php' => $rootPath . '/api/lib/SquareService.php',
        'ProductService.php' => $rootPath . '/api/lib/ProductService.php'
    ];
    
    foreach ($requiredLibs as $lib => $path) {
        logProductAPI("ライブラリファイル確認: " . $lib . " - パス: " . $path);
        if (!file_exists($path)) {
            throw new Exception("必要なライブラリファイルが見つかりません: " . $lib);
        }
    }

    // ライブラリをロード
    require_once $rootPath . '/api/lib/Database.php';
    require_once $rootPath . '/api/lib/Utils.php';
    require_once $rootPath . '/api/lib/SquareService.php';
    require_once $rootPath . '/api/lib/ProductService.php';
    
    logProductAPI("ライブラリ読み込み完了");

    // ProductServiceを初期化
    logProductAPI("ProductService初期化開始");
    try {
        $productService = new ProductService();
        logProductAPI("ProductService初期化完了");
    } catch (Exception $e) {
        logProductAPI("ProductService初期化エラー: " . $e->getMessage(), 'ERROR');
        logProductAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
        
        // エラー発生時もフォールバックデータを返す
        echo json_encode([
            'success' => true,
            'products' => [],
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true
        ]);
        exit;
    }

    // デバッグモードの場合は詳細情報を表示
    if (isset($_GET['debug']) && $_GET['debug'] === 'true' && defined('DEBUG_MODE') && DEBUG_MODE) {
        // デバッグモードが有効な場合のみ出力
        header('Content-Type: text/html; charset=UTF-8');
        echo "<h1>商品API デバッグ情報</h1>";
        echo "<h2>リクエスト情報</h2>";
        echo "<pre>";
        print_r($_GET);
        echo "</pre>";
        
        // カテゴリ情報も表示
        echo "<h2>カテゴリ情報</h2>";
        try {
            $categories = $productService->getCategories();
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>名前</th><th>表示順</th><th>アクティブ</th><th>ラストオーダー</th></tr>";
            foreach ($categories as $category) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($category['id']) . "</td>";
                echo "<td>" . htmlspecialchars($category['name']) . "</td>";
                echo "<td>" . (isset($category['sort_order']) ? $category['sort_order'] : 'N/A') . "</td>";
                echo "<td>" . (isset($category['is_active']) ? ($category['is_active'] ? 'はい' : 'いいえ') : 'N/A') . "</td>";
                echo "<td>" . (isset($category['last_order_time']) ? $category['last_order_time'] : 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "<p style='color:red'>カテゴリ取得エラー: " . $e->getMessage() . "</p>";
        }
        
        // カテゴリIDが指定されている場合は商品データも表示
        $categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
        
        if ($categoryId) {
            echo "<h2>カテゴリID: " . htmlspecialchars($categoryId) . " の商品データ</h2>";
            try {
                // 指定カテゴリの商品を取得
                $products = $productService->getProductsByCategoryId($categoryId);
                
                echo "<p>商品数: " . count($products) . "件</p>";
                echo "<pre>";
                print_r($products);
                echo "</pre>";
            } catch (Exception $e) {
                echo "<p style='color:red'>商品取得エラー: " . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        }
        
        exit; // 通常のJSON応答を返さない
    }

    try {
        // カテゴリIDがクエリパラメータで指定されているか確認
        $categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
        logProductAPI("リクエストパラメータ: category_id=" . ($categoryId ?? 'null'));
        
        if ($categoryId) {
            // 特定のカテゴリの商品を取得
            logProductAPI("カテゴリID:" . $categoryId . "の商品取得開始");
            
            // ProductServiceの関数を変更 - カテゴリIDに基づく商品取得関数を新たに呼び出す
            $products = $productService->getProductsByCategoryId($categoryId);
            $productCount = count($products);
            logProductAPI("商品取得完了: " . $productCount . "件");
            
            // レスポンスを生成して返す
            $response = [
                'success' => true,
                'products' => $products,
                'count' => $productCount,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            // すべての商品を取得
            logProductAPI("全商品取得開始");
            $products = $productService->getProducts();
            $productCount = count($products);
            logProductAPI("商品取得完了: " . $productCount . "件");
            
            // レスポンスを生成して返す
            $response = [
                'success' => true,
                'products' => $products,
                'count' => $productCount,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // レスポンスを返す
        $jsonResponse = json_encode($response);
        if ($jsonResponse === false) {
            throw new Exception("JSONエンコードエラー: " . json_last_error_msg());
        }
        
        logProductAPI("JSONレスポンス生成完了: " . strlen($jsonResponse) . "バイト");
        echo $jsonResponse;
        
    } catch (Exception $e) {
        // エラーログ
        logProductAPI("商品取得エラー: " . $e->getMessage(), 'ERROR');
        logProductAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
        
        // エラーレスポンスを返す
        echo json_encode([
            'success' => true,
            'products' => [],
            'error_message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true
        ]);
    }
} catch (Exception $e) {
    // 致命的なエラー（ライブラリの読み込み失敗など）
    $errorMsg = "致命的なエラー: " . $e->getMessage();
    logProductAPI($errorMsg, 'ERROR');
    logProductAPI("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
    
    // エラーレスポンスを返す
    echo json_encode([
        'success' => true,
        'products' => [],
        'critical_error' => $errorMsg,
        'timestamp' => date('Y-m-d H:i:s'),
        'is_fallback' => true
    ]);
}

// リクエスト終了を記録
logProductAPI("======== 商品API終了 ========"); 