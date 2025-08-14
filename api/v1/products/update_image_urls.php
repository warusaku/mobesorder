<?php
/**
 * 画像URL一括更新APIエンドポイント
 * 既存の商品の画像URLを更新するAPI
 */

require_once '../../lib/init.php';

// 処理時間の制限を拡大（5分）
set_time_limit(300);

// CORSヘッダーとJSONレスポンス形式の設定
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// APIキーによる認証
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$configApiKey = Config::get('api_key');

// 処理するアイテム数の設定
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit <= 0 || $limit > 100) {
    $limit = 20; // デフォルト値または範囲外の場合
}

// レスポンスの基本構造
$response = [
    'success' => false,
    'message' => '',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

// ログ関数定義
function image_log($message, $level = 'INFO') {
    // ログファイルパス
    $logDir = __DIR__ . '/../../../logs';
    $logFile = $logDir . '/image_url_api.log';
    
    // ディレクトリがなければ作成
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログメッセージフォーマット
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    // ファイルに書き込み
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // PHP標準エラーログにも記録
    if ($level == 'ERROR') {
        error_log("IMAGE URL API: $message");
    }
}

// APIキー認証
if (empty($apiKey) || $apiKey !== $configApiKey) {
    $response['message'] = '認証エラー: 無効なAPIキーです';
    echo json_encode($response);
    exit;
}

// 画像URL更新実行
try {
    image_log("画像URL更新処理を開始しました - 最大{$limit}件を処理");
    
    // デバッグモード設定（オプション）
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        define('DEBUG_MODE', true);
        image_log("デバッグモードで実行しています");
    }
    
    // ProductServiceの初期化
    $productService = new ProductService();
    
    // 実行モード（特定商品ID or カテゴリIDによるフィルタリング）
    $mode = 'all'; // デフォルトは全商品
    $targetId = null;
    
    if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
        $mode = 'product';
        $targetId = $_GET['product_id'];
        image_log("商品ID {$targetId} のみを処理します");
    } elseif (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
        $mode = 'category';
        $targetId = $_GET['category_id'];
        image_log("カテゴリID {$targetId} の商品を処理します");
    }
    
    // クエリ構築
    $query = "
        SELECT id, square_item_id, name, image_url, category 
        FROM products 
        WHERE is_active = 1 
        AND (image_url IS NULL OR image_url = '' OR image_url NOT LIKE 'http%')
    ";
    
    $params = [];
    
    if ($mode === 'product') {
        $query .= " AND id = ?";
        $params[] = $targetId;
    } elseif ($mode === 'category') {
        $query .= " AND category = ?";
        $params[] = $targetId;
    }
    
    $query .= " ORDER BY id LIMIT ?";
    $params[] = $limit;
    
    // 商品を取得
    $productsToUpdate = $productService->db->select($query, $params);
    
    $updateCount = 0;
    $errorCount = 0;
    
    // 各商品を処理
    foreach ($productsToUpdate as $product) {
        image_log("商品ID: {$product['id']}, Square ID: {$product['square_item_id']}, 名前: {$product['name']} の画像処理中");
        
        try {
            // 画像URL取得処理
            $startTime = microtime(true);
            $imageUrl = $productService->processImageUrl($product['square_item_id']);
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒
            
            if (!empty($imageUrl)) {
                // 画像URLを更新
                $productService->db->execute(
                    "UPDATE products SET image_url = ? WHERE id = ?",
                    [$imageUrl, $product['id']]
                );
                
                $updateCount++;
                image_log("商品ID: {$product['id']} の画像URL更新成功: {$imageUrl} (処理時間: {$processingTime}ms)");
            } else {
                $errorCount++;
                image_log("商品ID: {$product['id']} の画像URL取得失敗 - Square商品に画像が設定されていない可能性があります (処理時間: {$processingTime}ms)", "WARNING");
            }
        } catch (Exception $e) {
            $errorCount++;
            image_log("商品ID: {$product['id']} の処理中にエラー: " . $e->getMessage(), "ERROR");
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                image_log("スタックトレース: " . $e->getTraceAsString(), "DEBUG");
            }
        }
        
        // APIリクエスト制限回避のための短い待機
        usleep(300000); // 0.3秒待機
    }
    
    // レスポンス設定
    $response['success'] = true;
    $response['message'] = '画像URL更新処理が完了しました';
    $response['data'] = [
        'processed' => count($productsToUpdate),
        'updated' => $updateCount,
        'errors' => $errorCount,
        'mode' => $mode,
        'target_id' => $targetId
    ];
    
    // 残りの未処理商品数も取得
    $remainingCount = $productService->db->selectOne(
        "SELECT COUNT(*) as count FROM products 
         WHERE is_active = 1 
         AND (image_url IS NULL OR image_url = '' OR image_url NOT LIKE 'http%')",
        []
    );
    
    $response['data']['remaining'] = $remainingCount['count'] ?? 0;
    
    image_log("画像URL更新処理完了: 処理={$response['data']['processed']}, 成功={$updateCount}, エラー={$errorCount}, 残り={$response['data']['remaining']}");
    
} catch (Exception $e) {
    $response['message'] = '例外が発生しました: ' . $e->getMessage();
    image_log("画像URL更新処理全体でエラー: " . $e->getMessage(), "ERROR");
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        image_log("スタックトレース: " . $e->getTraceAsString(), "DEBUG");
    }
}

// レスポンスを返す
echo json_encode($response); 