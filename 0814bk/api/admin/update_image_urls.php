<?php
/**
 * 商品画像URL一括更新API
 * 管理画面からのリクエストに応じて処理を実行
 */

require_once '../lib/init.php';

// 管理者認証チェック
$auth = new AdminAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '認証されていません。'
    ]);
    exit;
}

// タイムアウト設定を延長
set_time_limit(300); // 5分間

// 処理の開始
$productService = new ProductService();
$db = Database::getInstance();

try {
    // 更新対象のレコード数を取得
    $targetCount = $db->selectOne(
        "SELECT COUNT(*) as count FROM products 
         WHERE image_url IS NOT NULL 
         AND image_url != '' 
         AND image_url NOT LIKE 'http%'"
    );
    
    $totalCount = $targetCount['count'] ?? 0;
    
    if ($totalCount == 0) {
        echo json_encode([
            'success' => true,
            'message' => '更新対象の商品がありません。',
            'stats' => [
                'total' => 0,
                'processed' => 0,
                'updated' => 0,
                'errors' => 0
            ]
        ]);
        exit;
    }
    
    // 統計情報初期化
    $stats = [
        'total' => $totalCount,
        'processed' => 0,
        'updated' => 0,
        'errors' => 0
    ];
    
    // バッチサイズ
    $batchSize = 10;
    $offset = 0;
    
    while ($offset < $totalCount) {
        // 一度に処理する商品を取得
        $products = $db->select(
            "SELECT id, square_item_id, image_url 
             FROM products 
             WHERE image_url IS NOT NULL 
             AND image_url != '' 
             AND image_url NOT LIKE 'http%'
             LIMIT ? OFFSET ?",
            [$batchSize, $offset]
        );
        
        // バッチ処理
        foreach ($products as $product) {
            $stats['processed']++;
            
            // 個別商品の画像URL更新
            $result = $productService->updateSingleProductImageUrl($product['id']);
            
            if ($result) {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }
            
            // 1件ごとに少し待機（API制限対策）
            usleep(100000); // 0.1秒
        }
        
        // 次のバッチへ
        $offset += $batchSize;
        
        // バッチ間で少し待機
        if ($offset < $totalCount) {
            usleep(500000); // 0.5秒
        }
    }
    
    // 処理結果
    echo json_encode([
        'success' => true,
        'message' => "画像URL更新が完了しました。処理: {$stats['processed']}件, 更新: {$stats['updated']}件, エラー: {$stats['errors']}件",
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    // エラーをログに記録
    Utils::log("画像URL一括更新エラー: " . $e->getMessage(), 'ERROR', 'UpdateImageUrls');
    
    // レスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '画像URL更新中にエラーが発生しました: ' . $e->getMessage()
    ]);
} 