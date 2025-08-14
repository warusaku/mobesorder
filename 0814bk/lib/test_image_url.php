<?php
/**
 * 画像URL取得テストスクリプト
 * SquareServiceとProductServiceの画像URL取得機能をテストします
 */

// 初期化ファイルの読み込み
require_once __DIR__ . '/init.php';

// タイムアウト設定
set_time_limit(300);

// テスト用関数
function test_log($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] [$level] $message" . PHP_EOL;
    
    // ログファイルにも記録
    $logFile = __DIR__ . '/../../logs/image_url_test.log';
    file_put_contents($logFile, "[$timestamp] [$level] $message" . PHP_EOL, FILE_APPEND);
}

// テスト実行
try {
    test_log("画像URL取得テスト開始", "INFO");
    
    // ProductServiceインスタンス作成
    $productService = new ProductService();
    
    // テスト用の商品IDを取得（最初の10件）
    test_log("テスト用商品ID取得中...", "INFO");
    
    $products = $productService->db->select("
        SELECT id, square_item_id, name, image_url 
        FROM products 
        WHERE is_active = 1
        LIMIT 10
    ");
    
    if (empty($products)) {
        test_log("テスト用商品がデータベースに見つかりません", "ERROR");
        exit(1);
    }
    
    test_log(count($products) . "件の商品を取得しました", "INFO");
    
    // 各商品の画像URLを取得
    $successCount = 0;
    $failCount = 0;
    
    foreach ($products as $product) {
        test_log("----------------------------------------", "INFO");
        test_log("商品ID: {$product['id']}, Square ID: {$product['square_item_id']}, 名前: {$product['name']}", "INFO");
        test_log("現在のimage_url: " . ($product['image_url'] ?: "未設定"), "INFO");
        
        // ProductServiceのprocessImageUrlメソッドで画像URLを取得
        $startTime = microtime(true);
        $imageUrl = $productService->processImageUrl($product['square_item_id']);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if (!empty($imageUrl)) {
            test_log("画像URL取得成功: {$imageUrl} ({$duration}ms)", "SUCCESS");
            $successCount++;
            
            // データベースを更新するオプション
            if (isset($_GET['update']) && $_GET['update'] === 'true') {
                $productService->db->execute(
                    "UPDATE products SET image_url = ? WHERE id = ?",
                    [$imageUrl, $product['id']]
                );
                test_log("データベース更新: image_url を設定しました", "INFO");
            }
        } else {
            test_log("画像URL取得失敗 ({$duration}ms)", "WARNING");
            $failCount++;
        }
    }
    
    test_log("----------------------------------------", "INFO");
    test_log("テスト結果サマリー:", "INFO");
    test_log("処理商品数: " . count($products) . "件", "INFO");
    test_log("成功: {$successCount}件", "INFO");
    test_log("失敗: {$failCount}件", "INFO");
    test_log("成功率: " . round(($successCount / count($products)) * 100, 1) . "%", "INFO");
    
    if (isset($_GET['update']) && $_GET['update'] === 'true') {
        test_log("データベース更新モードで実行されました", "INFO");
    } else {
        test_log("テストモードで実行されました。?update=true クエリパラメータを追加すると、画像URLがデータベースに保存されます", "INFO");
    }
    
    test_log("画像URL取得テスト完了", "INFO");
    
} catch (Exception $e) {
    test_log("テスト実行中にエラーが発生しました: " . $e->getMessage(), "ERROR");
    test_log("スタックトレース: " . $e->getTraceAsString(), "ERROR");
    exit(1);
} 