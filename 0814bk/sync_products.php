<?php
/**
 * Square商品カタログ同期スクリプト
 * 
 * このスクリプトはSquare APIから商品情報を取得し、
 * データベースに同期するためのコマンドラインツールです。
 * 
 * 使用方法: php sync_products.php [--verbose]
 * --verbose: 詳細な出力を表示
 */

// ウェブからのアクセスを拒否
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('このスクリプトはコマンドラインからのみ実行できます。');
}

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルを読み込む
require_once 'api/config/config.php';
require_once 'api/lib/Database.php';
require_once 'api/lib/Utils.php';
require_once 'api/lib/SquareService.php';
require_once 'api/lib/ProductService.php';

// コマンドライン引数の解析
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

// 実行開始メッセージ
echo "Square商品同期を開始しています...\n";
$startTime = microtime(true);

try {
    // ProductServiceのインスタンスを作成
    $productService = new ProductService();
    
    // 同期処理を実行
    $result = $productService->syncProductsFromSquare();
    
    // 処理時間の計算
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // 結果を表示
    echo "同期完了 ({$executionTime}秒)\n";
    echo "追加: {$result['added']}件\n";
    echo "更新: {$result['updated']}件\n";
    echo "エラー: {$result['errors']}件\n";
    
    if ($verbose) {
        // 詳細モードの場合、データベースから商品を取得して表示
        echo "\n現在のデータベース内の商品一覧:\n";
        echo "----------------------------------------\n";
        
        $products = $productService->getProducts(false);
        foreach ($products as $index => $product) {
            echo ($index + 1) . ". {$product['name']} (ID: {$product['id']}, Square ID: {$product['square_item_id']})\n";
            echo "   価格: {$product['price']}円, 在庫: {$product['stock_quantity']}個\n";
            
            if ($index >= 9 && count($products) > 10) {
                echo "... 他 " . (count($products) - 10) . "件\n";
                break;
            }
        }
    }
    
    exit(0); // 成功
} catch (Exception $e) {
    // エラーが発生した場合
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    
    // スタックトレースを表示（詳細モードのみ）
    if ($verbose) {
        echo "\nスタックトレース:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1); // エラー終了
} 