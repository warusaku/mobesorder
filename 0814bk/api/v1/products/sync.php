<?php
/**
 * 商品同期APIエンドポイント
 * Square APIから最新の商品情報を取得し、データベースに同期する
 */

require_once '../../lib/init.php';

// 処理時間の制限を拡大（5分）
set_time_limit(300);

// CORSヘッダーとJSONレスポンス形式の設定
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// APIキーによる認証
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$configApiKey = Config::get('api_key');

// レスポンスの基本構造
$response = [
    'success' => false,
    'message' => '',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

// ログ関数定義
function sync_log($message, $level = 'INFO') {
    // ログファイルパス
    $logDir = __DIR__ . '/../../../logs';
    $logFile = $logDir . '/sync_api.log';
    
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
        error_log("SYNC API: $message");
    }
}

// APIキー認証
if (empty($apiKey) || $apiKey !== $configApiKey) {
    $response['message'] = '認証エラー: 無効なAPIキーです';
    echo json_encode($response);
    exit;
}

// 商品同期実行
try {
    sync_log("商品同期処理を開始しました");
    
    // デバッグモード設定（オプション）
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        define('SYNC_DEBUG', true);
        sync_log("デバッグモードで実行しています");
    }
    
    // 同期処理の実行
    $productService = new ProductService();
    sync_log("商品同期処理メイン処理を実行中...");
    $syncResult = $productService->processProductSync();
    
    // 同期結果の確認
    if (isset($syncResult['success']) && $syncResult['success']) {
        $response['success'] = true;
        $response['message'] = '商品同期処理が完了しました';
        
        // productsが空の配列の場合は、categoriesと同じ構造の連想配列を設定
        if (isset($syncResult['stats']) && is_array($syncResult['stats'])) {
            // syncResultの統計情報を整形
            $productStats = [
                'added' => $syncResult['stats']['added'] ?? 0,
                'updated' => $syncResult['stats']['updated'] ?? 0,
                'skipped' => $syncResult['stats']['skipped'] ?? 0,
                'errors' => $syncResult['stats']['errors'] ?? 0
            ];
            
            // カテゴリ統計情報（もし存在すれば）
            $categoryStats = isset($syncResult['category_stats']) ? $syncResult['category_stats'] : [
                'created' => 0,
                'updated' => 0, 
                'skipped' => 0,
                'errors' => 0
            ];
            
            // 同期結果データの構造を統一
            $syncResult['stats'] = [
                'products' => $productStats,
                'categories' => $categoryStats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        $response['data'] = $syncResult;
        sync_log("商品同期処理が成功しました: " . json_encode($syncResult['stats']));
        
        // 画像URL更新処理
        // テストスクリプトで成功した方法で画像URLを取得
        try {
            sync_log("画像URL更新処理を開始...");
            
            // 更新が必要な画像URLの商品をクエリ
            $productsToUpdate = $productService->db->select("
                SELECT id, square_item_id, name, image_url 
                FROM products 
                WHERE is_active = 1 
                AND (image_url IS NULL OR image_url = '' OR image_url NOT LIKE 'http%')
                LIMIT 50
            ");
            
            $updateCount = 0;
            $errorCount = 0;
            
            // 各商品を個別に処理（一度に処理する数を増やす）
            foreach ($productsToUpdate as $product) {
                sync_log("商品ID: {$product['id']}, Square ID: {$product['square_item_id']}, 名前: {$product['name']} の画像処理中");
                
                try {
                    // テストスクリプトで成功した方法で画像URLを取得
                    $imageUrl = $productService->processImageUrl($product['square_item_id']);
                    
                    if (!empty($imageUrl)) {
                        // 画像URLを更新
                        $productService->db->execute(
                            "UPDATE products SET image_url = ? WHERE id = ?",
                            [$imageUrl, $product['id']]
                        );
                        
                        $updateCount++;
                        sync_log("商品ID: {$product['id']} の画像URL更新成功: {$imageUrl}");
                    } else {
                        $errorCount++;
                        sync_log("商品ID: {$product['id']} の画像URL取得失敗 - 画像IDが見つからないか、Squareの設定が必要かもしれません", "WARNING");
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    sync_log("商品ID: {$product['id']} の処理中にエラー: " . $e->getMessage(), "ERROR");
                    sync_log("スタックトレース: " . $e->getTraceAsString(), "DEBUG");
                }
                
                // APIリクエスト制限回避のための短い待機
                usleep(200000); // 0.2秒待機
            }
            
            // 結果をレスポンスに追加
            $response['data']['image_updates'] = [
                'processed' => count($productsToUpdate),
                'updated' => $updateCount,
                'errors' => $errorCount
            ];
            
            sync_log("画像URL更新処理完了: 処理={$response['data']['image_updates']['processed']}, 成功={$updateCount}, エラー={$errorCount}");
            
            // 更新対象が多い場合はユーザーに通知
            if (count($productsToUpdate) >= 50) {
                sync_log("注意: まだ画像URL未設定の商品が残っています。次回の同期で引き続き処理されます", "INFO");
            }
            
        } catch (Exception $imageError) {
            sync_log("画像URL更新中にエラーが発生: " . $imageError->getMessage(), "ERROR");
            sync_log("スタックトレース: " . $imageError->getTraceAsString(), "DEBUG");
            $response['data']['image_updates'] = [
                'error' => $imageError->getMessage()
            ];
        }
    } else {
        $response['message'] = '商品同期中にエラーが発生しました: ' . ($syncResult['message'] ?? '不明なエラー');
        $response['data'] = $syncResult;
        sync_log("商品同期処理が失敗: " . $response['message'], "ERROR");
    }
} catch (Exception $e) {
    $response['message'] = '例外が発生しました: ' . $e->getMessage();
    sync_log("同期処理全体でエラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), "ERROR");
}

// レスポンスを返す
sync_log("同期API処理完了: " . ($response['success'] ? '成功' : '失敗'));
echo json_encode($response); 